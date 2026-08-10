<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncResource;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Jobs\Concerns\SynchronisesTenantResource;
use App\Models\EntraUser;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Refreshes the cached projection of a tenant's Entra ID users.
 *
 * Uses Graph's delta endpoint so a routine run transfers only what changed.
 * The first run for a tenant, or any run after a delta link is lost, falls
 * back to enumerating the whole directory.
 */
final class SynchroniseUsers implements ShouldQueue
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
        return SyncResource::Users;
    }

    protected function tenant(): Tenant
    {
        return $this->syncTenant;
    }

    /**
     * Prevents two runs for the same tenant overlapping and writing over each
     * other's delta link.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync:users:'.$this->syncTenant->id))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    protected function synchronise(Tenant $tenant, SyncState $state): array
    {
        $result = app(GraphClientFactory::class)
            ->users($tenant)
            ->delta($state->delta_link);

        $created = 0;
        $updated = 0;
        $deleted = 0;

        foreach ($result['items'] as $item) {
            $microsoftId = Arr::get($item, 'id');

            if (! is_string($microsoftId) || $microsoftId === '') {
                continue;
            }

            // Delta marks removals with an @removed annotation rather than
            // omitting the object.
            if (isset($item['@removed'])) {
                $deleted += EntraUser::where('microsoft_id', $microsoftId)->delete();

                continue;
            }

            $user = EntraUser::withTrashed()
                ->firstOrNew(['microsoft_id' => $microsoftId]);

            $existed = $user->exists;

            $user->fill($this->attributesFrom($item));

            if ($user->trashed()) {
                // The object came back: an administrator restored a deleted
                // user in Entra, or it was only soft-removed on our side.
                $user->deleted_at = null;
            }

            $user->save();

            $existed ? $updated++ : $created++;
        }

        $state->delta_link = $result['delta_link'];
        $state->save();

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }

    /**
     * Map a Graph user object to our columns.
     *
     * Only properties we asked for are mapped. A delta response for an updated
     * object contains just the changed properties, so anything absent is left
     * as it was rather than being overwritten with null.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributesFrom(array $item): array
    {
        $map = [
            'user_principal_name' => 'userPrincipalName',
            'display_name' => 'displayName',
            'given_name' => 'givenName',
            'surname' => 'surname',
            'mail' => 'mail',
            'job_title' => 'jobTitle',
            'department' => 'department',
            'office_location' => 'officeLocation',
            'mobile_phone' => 'mobilePhone',
            'employee_id' => 'employeeId',
            'usage_location' => 'usageLocation',
            'user_type' => 'userType',
            'account_enabled' => 'accountEnabled',
            'on_premises_sync_enabled' => 'onPremisesSyncEnabled',
        ];

        $attributes = ['synced_at' => now()];

        foreach ($map as $column => $graphProperty) {
            if (array_key_exists($graphProperty, $item)) {
                $attributes[$column] = $item[$graphProperty];
            }
        }

        if (array_key_exists('createdDateTime', $item) && $item['createdDateTime'] !== null) {
            $attributes['created_date_time'] = Carbon::parse((string) $item['createdDateTime']);
        }

        if (array_key_exists('assignedLicenses', $item)) {
            $licenses = is_array($item['assignedLicenses']) ? $item['assignedLicenses'] : [];

            $attributes['assigned_license_count'] = count($licenses);

            // Graph returns SKU ids here, not the part numbers administrators
            // recognise. Resolving those requires subscribedSkus, which is a
            // separate sync; until then we store the ids we were given rather
            // than inventing friendly names.
            $attributes['assigned_license_skus'] = array_values(array_filter(
                array_map(static fn (array $l): ?string => $l['skuId'] ?? null, $licenses),
            ));
        }

        return $attributes;
    }
}
