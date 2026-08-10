<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\AuditResult;
use App\Domain\Sync\SyncResource;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncStateResource;
use App\Jobs\SynchroniseAuthenticationMethods;
use App\Jobs\SynchroniseGroupMembers;
use App\Jobs\SynchroniseGroups;
use App\Jobs\SynchroniseManagedDevices;
use App\Jobs\SynchroniseUsers;
use App\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Synchronisation status, and on-demand refreshes.
 */
final class SyncController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SyncStateResource::collection(SyncState::all());
    }

    /**
     * Request an immediate synchronisation.
     *
     * Queued rather than run inline: a full device enumeration takes minutes
     * and must not be tied to an HTTP request. The per-job WithoutOverlapping
     * middleware means an impatient administrator clicking repeatedly cannot
     * stack duplicate runs.
     */
    public function store(Request $request, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'resource' => ['required', Rule::enum(SyncResource::class)],
        ]);

        $resource = SyncResource::from($validated['resource']);
        $tenant = $context->tenant();

        $job = match ($resource) {
            SyncResource::Users => SynchroniseUsers::class,
            SyncResource::Groups => SynchroniseGroups::class,
            SyncResource::GroupMembers => SynchroniseGroupMembers::class,
            SyncResource::ManagedDevices => SynchroniseManagedDevices::class,
            SyncResource::AuthenticationMethods => SynchroniseAuthenticationMethods::class,
        };

        $job::dispatch($tenant);

        $audit->log(new AuditEntry(
            action: AuditAction::SyncRequested,
            result: AuditResult::Pending,
            resourceType: 'sync',
            resourceId: $resource->value,
            resourceLabel: $resource->label(),
        ));

        return response()->json([
            'message' => sprintf('%s synchronisation has been queued.', $resource->label()),
        ], 202);
    }
}
