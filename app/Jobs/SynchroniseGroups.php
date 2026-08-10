<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncResource;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Jobs\Concerns\SynchronisesTenantResource;
use App\Models\EntraGroup;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Refreshes the cached projection of a tenant's Entra ID groups.
 *
 * Group membership is synchronised separately by {@see SynchroniseGroupMembers}
 * because it is an order of magnitude more expensive and does not need to run
 * at the same cadence.
 */
final class SynchroniseGroups implements ShouldQueue
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
        return SyncResource::Groups;
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
            (new WithoutOverlapping('sync:groups:'.$this->syncTenant->id))
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
            ->groups($tenant)
            ->delta($state->delta_link);

        $created = 0;
        $updated = 0;
        $deleted = 0;

        foreach ($result['items'] as $item) {
            $microsoftId = Arr::get($item, 'id');

            if (! is_string($microsoftId) || $microsoftId === '') {
                continue;
            }

            if (isset($item['@removed'])) {
                $deleted += EntraGroup::where('microsoft_id', $microsoftId)->delete();

                continue;
            }

            $group = EntraGroup::withTrashed()->firstOrNew(['microsoft_id' => $microsoftId]);
            $existed = $group->exists;

            $group->fill($this->attributesFrom($item));

            if ($group->trashed()) {
                $group->deleted_at = null;
            }

            $group->save();

            $existed ? $updated++ : $created++;
        }

        $state->delta_link = $result['delta_link'];
        $state->save();

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributesFrom(array $item): array
    {
        $map = [
            'display_name' => 'displayName',
            'description' => 'description',
            'mail' => 'mail',
            'mail_nickname' => 'mailNickname',
            'mail_enabled' => 'mailEnabled',
            'security_enabled' => 'securityEnabled',
            'group_types' => 'groupTypes',
            'membership_rule' => 'membershipRule',
            'membership_rule_processing_state' => 'membershipRuleProcessingState',
            'on_premises_sync_enabled' => 'onPremisesSyncEnabled',
        ];

        $attributes = ['synced_at' => now()];

        // A delta response for a changed group carries only the changed
        // properties, so absent keys are left alone rather than nulled.
        foreach ($map as $column => $graphProperty) {
            if (array_key_exists($graphProperty, $item)) {
                $attributes[$column] = $item[$graphProperty];
            }
        }

        if (array_key_exists('createdDateTime', $item) && $item['createdDateTime'] !== null) {
            $attributes['created_date_time'] = Carbon::parse((string) $item['createdDateTime']);
        }

        return $attributes;
    }
}
