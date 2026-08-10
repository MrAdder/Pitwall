<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncResource;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphResourceNotFound;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Jobs\Concerns\SynchronisesTenantResource;
use App\Models\EntraGroup;
use App\Models\EntraGroupMembership;
use App\Models\EntraUser;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;

/**
 * Refreshes user membership for a tenant's groups.
 *
 * This is the most expensive sync in the platform: one Graph call per group,
 * minimum. It runs on its own schedule for that reason, and depends on groups
 * and users having been synchronised first, since membership rows reference
 * both.
 */
final class SynchroniseGroupMembers implements ShouldQueue
{
    use Queueable, SynchronisesTenantResource;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(
        private readonly Tenant $syncTenant,
    ) {
        $this->onQueue('sync');
    }

    public function resource(): SyncResource
    {
        return SyncResource::GroupMembers;
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
            (new WithoutOverlapping('sync:group-members:'.$this->syncTenant->id))
                ->releaseAfter(120)
                ->expireAfter(3600),
        ];
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    protected function synchronise(Tenant $tenant, SyncState $state): array
    {
        $graph = app(GraphClientFactory::class)->groups($tenant);

        $userIdsByMicrosoftId = EntraUser::query()->pluck('id', 'microsoft_id')->all();

        $created = 0;
        $deleted = 0;

        EntraGroup::query()
            ->select(['id', 'microsoft_id', 'member_count'])
            ->chunkById(200, function ($groups) use ($graph, $userIdsByMicrosoftId, &$created, &$deleted): void {
                foreach ($groups as $group) {
                    try {
                        $memberUserIds = $this->memberUserIdsFor($graph, $group->microsoft_id, $userIdsByMicrosoftId);
                    } catch (GraphResourceNotFound) {
                        // Deleted between the group sync and now. The next
                        // group delta will remove it; skip it this run.
                        continue;
                    }

                    $created += $this->syncMemberships($group, $memberUserIds);
                    $deleted += $this->removeStaleMemberships($group, $memberUserIds);

                    $group->forceFill(['member_count' => count($memberUserIds)])->save();
                }
            });

        return ['created' => $created, 'updated' => 0, 'deleted' => $deleted];
    }

    /**
     * Local user ids for a group's user members, keyed by Microsoft id.
     *
     * Members we have never seen in the user sync are skipped rather than
     * created here: this job is about edges, and inventing user rows from a
     * membership payload would produce records with almost no attributes.
     *
     * @param  array<string, string>  $userIdsByMicrosoftId
     * @return array<string, string> Microsoft id => local id
     */
    private function memberUserIdsFor(object $graph, string $groupMicrosoftId, array $userIdsByMicrosoftId): array
    {
        $members = [];

        foreach ($graph->userMembers($groupMicrosoftId) as $member) {
            $memberMicrosoftId = Arr::get($member, 'id');

            if (! is_string($memberMicrosoftId)) {
                continue;
            }

            if (isset($userIdsByMicrosoftId[$memberMicrosoftId])) {
                $members[$memberMicrosoftId] = $userIdsByMicrosoftId[$memberMicrosoftId];
            }
        }

        return $members;
    }

    /**
     * @param  array<string, string>  $memberUserIds
     */
    private function syncMemberships(EntraGroup $group, array $memberUserIds): int
    {
        $created = 0;

        foreach ($memberUserIds as $userMicrosoftId => $userId) {
            $membership = EntraGroupMembership::firstOrNew([
                'entra_group_id' => $group->id,
                'entra_user_id' => $userId,
            ]);

            if (! $membership->exists) {
                $created++;
            }

            $membership->fill([
                'group_microsoft_id' => $group->microsoft_id,
                'user_microsoft_id' => $userMicrosoftId,
                'synced_at' => now(),
            ])->save();
        }

        return $created;
    }

    /**
     * @param  array<string, string>  $memberUserIds
     */
    private function removeStaleMemberships(EntraGroup $group, array $memberUserIds): int
    {
        $query = EntraGroupMembership::where('entra_group_id', $group->id);

        foreach (array_chunk(array_values($memberUserIds), 1000) as $chunk) {
            $query->whereNotIn('entra_user_id', $chunk);
        }

        return $query->delete();
    }
}
