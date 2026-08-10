<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncResource;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Jobs\Concerns\SynchronisesTenantResource;
use App\Models\EntraUser;
use App\Models\ManagedDevice;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes the cached projection of a tenant's Intune managed devices.
 *
 * Graph has no delta endpoint for managedDevices, so this enumerates the full
 * collection each run. Devices absent from the enumeration are soft-deleted:
 * they were unenrolled or wiped, and should stop appearing in operational
 * views while remaining resolvable from historical audit entries.
 */
final class SynchroniseManagedDevices implements ShouldQueue
{
    use Queueable, SynchronisesTenantResource;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(
        private readonly Tenant $syncTenant,
    ) {
        $this->onQueue('sync');
    }

    public function resource(): SyncResource
    {
        return SyncResource::ManagedDevices;
    }

    protected function tenant(): Tenant
    {
        return $this->syncTenant;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync:devices:'.$this->syncTenant->id))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    protected function synchronise(Tenant $tenant, SyncState $state): array
    {
        $graph = app(GraphClientFactory::class)->managedDevices($tenant);

        // Entra object id => local user id, so each device can be linked to
        // its primary user without a query per device.
        $userIdsByMicrosoftId = EntraUser::query()
            ->pluck('id', 'microsoft_id')
            ->all();

        $created = 0;
        $updated = 0;
        $seen = [];

        foreach ($graph->list() as $item) {
            $microsoftId = Arr::get($item, 'id');

            if (! is_string($microsoftId) || $microsoftId === '') {
                continue;
            }

            $seen[] = $microsoftId;

            $device = ManagedDevice::withTrashed()
                ->firstOrNew(['microsoft_id' => $microsoftId]);

            $existed = $device->exists;

            $device->fill($this->attributesFrom($item, $userIdsByMicrosoftId));

            if ($device->trashed()) {
                $device->deleted_at = null;
            }

            $device->save();

            $existed ? $updated++ : $created++;
        }

        $deleted = $this->softDeleteMissing($seen);

        $this->refreshUserDeviceCounts();

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $userIdsByMicrosoftId
     * @return array<string, mixed>
     */
    private function attributesFrom(array $item, array $userIdsByMicrosoftId): array
    {
        $userMicrosoftId = Arr::get($item, 'userId') ?: null;

        return [
            'device_name' => Arr::get($item, 'deviceName'),
            'manufacturer' => Arr::get($item, 'manufacturer'),
            'model' => Arr::get($item, 'model'),
            'serial_number' => Arr::get($item, 'serialNumber'),

            'operating_system' => Arr::get($item, 'operatingSystem'),
            'os_version' => Arr::get($item, 'osVersion'),

            'compliance_state' => Arr::get($item, 'complianceState'),
            'compliance_grace_period_expires_at' => $this->date(Arr::get($item, 'complianceGracePeriodExpirationDateTime')),

            'owner_type' => Arr::get($item, 'managedDeviceOwnerType'),
            'management_agent' => Arr::get($item, 'managementAgent'),
            'enrollment_type' => Arr::get($item, 'deviceEnrollmentType'),
            'registration_state' => Arr::get($item, 'deviceRegistrationState'),

            'is_encrypted' => Arr::get($item, 'isEncrypted'),
            'is_supervised' => Arr::get($item, 'isSupervised'),
            'jail_broken' => $this->jailBroken(Arr::get($item, 'jailBroken')),

            'azure_ad_device_id' => Arr::get($item, 'azureADDeviceId'),
            'user_microsoft_id' => $userMicrosoftId,
            'user_principal_name' => Arr::get($item, 'userPrincipalName'),
            'entra_user_id' => $userMicrosoftId === null
                ? null
                : ($userIdsByMicrosoftId[$userMicrosoftId] ?? null),

            'last_sync_date_time' => $this->date(Arr::get($item, 'lastSyncDateTime')),
            'enrolled_date_time' => $this->date(Arr::get($item, 'enrolledDateTime')),

            'total_storage_bytes' => $this->bytes(Arr::get($item, 'totalStorageSpaceInBytes')),
            'free_storage_bytes' => $this->bytes(Arr::get($item, 'freeStorageSpaceInBytes')),

            'wifi_mac_address' => Arr::get($item, 'wiFiMacAddress'),
            'ethernet_mac_address' => Arr::get($item, 'ethernetMacAddress'),

            'synced_at' => now(),
        ];
    }

    /**
     * Devices no longer returned by Intune have been unenrolled.
     *
     * @param  list<string>  $seenMicrosoftIds
     */
    private function softDeleteMissing(array $seenMicrosoftIds): int
    {
        if ($seenMicrosoftIds === []) {
            // Intune returned nothing at all. That is legitimate for a tenant
            // with no enrolled devices, but it is also what a silently empty
            // response looks like, so removing every cached device on the
            // strength of it is not a trade worth making. Leave them and say so.
            Log::info('Device sync returned no devices; leaving cached devices untouched.', [
                'tenant_id' => $this->syncTenant->id,
            ]);

            return 0;
        }

        $query = ManagedDevice::query();

        // Chunked because the id list becomes an IN clause, and a large tenant
        // would otherwise produce a statement no database wants to parse.
        // Chaining the chunks with AND is equivalent to excluding the union.
        foreach (array_chunk($seenMicrosoftIds, 1000) as $chunk) {
            $query->whereNotIn('microsoft_id', $chunk);
        }

        return $query->delete();
    }

    /**
     * Keep the per-user device count on entra_users in step, so user lists can
     * show it without a subquery per row.
     */
    private function refreshUserDeviceCounts(): void
    {
        $counts = ManagedDevice::query()
            ->whereNotNull('entra_user_id')
            ->selectRaw('entra_user_id, count(*) as total')
            ->groupBy('entra_user_id')
            ->pluck('total', 'entra_user_id');

        EntraUser::query()->where('managed_device_count', '>', 0)->update(['managed_device_count' => 0]);

        foreach ($counts->chunk(500) as $chunk) {
            foreach ($chunk as $userId => $total) {
                EntraUser::whereKey($userId)->update(['managed_device_count' => $total]);
            }
        }
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = Carbon::parse($value);

        // Intune returns this sentinel for "never", which would otherwise be
        // displayed as a real date in the year 1.
        return $date->year <= 1 ? null : $date;
    }

    private function bytes(mixed $value): ?int
    {
        return is_numeric($value) && $value >= 0 ? (int) $value : null;
    }

    /**
     * Graph returns this as the string "True"/"False"/"Unknown", not a boolean.
     */
    private function jailBroken(mixed $value): ?bool
    {
        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }
}
