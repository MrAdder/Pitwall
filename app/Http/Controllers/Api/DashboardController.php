<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Devices\ComplianceState;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\SyncStateResource;
use App\Models\AuditLog;
use App\Models\EntraUser;
use App\Models\ManagedDevice;
use App\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operational overview for the current tenant.
 *
 * Answers "what needs my attention right now?", so every figure here is either
 * something to act on or the context needed to judge it. Counts that nobody
 * would act on are deliberately absent.
 *
 * Everything is read from the local projection, which is why the response
 * carries synchronisation state: a dashboard that does not say how old its
 * numbers are invites someone to act on figures from three hours ago.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): JsonResponse
    {
        $deviceCounts = ManagedDevice::query()
            ->selectRaw('compliance_state, count(*) as total')
            ->groupBy('compliance_state')
            ->pluck('total', 'compliance_state');

        $totalDevices = (int) $deviceCounts->sum();

        $needsAttention = collect(ComplianceState::cases())
            ->filter(fn (ComplianceState $state): bool => $state->needsAttention())
            ->sum(fn (ComplianceState $state): int => (int) $deviceCounts->get($state->value, 0));

        return response()->json([
            'data' => [
                'users' => [
                    'total' => EntraUser::count(),
                    'enabled' => EntraUser::where('account_enabled', true)->count(),
                    'disabled' => EntraUser::where('account_enabled', false)->count(),

                    // Null means the authentication methods report has not been
                    // synchronised or is unavailable on this tenant. It is
                    // reported separately from "not registered" so the UI never
                    // presents unknown as a clean bill of health.
                    'mfa_registration_unknown' => EntraUser::whereNull('is_mfa_registered')->count(),
                    'mfa_not_registered' => EntraUser::where('is_mfa_registered', false)->count(),
                ],

                'devices' => [
                    'total' => $totalDevices,
                    'compliant' => (int) $deviceCounts->get(ComplianceState::Compliant->value, 0),
                    'non_compliant' => (int) $deviceCounts->get(ComplianceState::NonCompliant->value, 0),
                    'unknown' => (int) $deviceCounts->get(ComplianceState::Unknown->value, 0),
                    'needs_attention' => $needsAttention,

                    // Devices reporting a last-known state rather than a
                    // current one. Easy to miss, and the reason a compliance
                    // percentage can look better than reality.
                    'not_checked_in_14_days' => ManagedDevice::notCheckedInFor(14)->count(),
                    'unencrypted' => ManagedDevice::where('is_encrypted', false)->count(),
                ],

                'recent_activity' => AuditLogResource::collection(
                    AuditLog::with('actor')->latest('created_at')->limit(10)->get(),
                )->toArray($request),

                'sync' => SyncStateResource::collection(
                    SyncState::all(),
                )->toArray($request),

                'tenant' => [
                    'status' => $context->tenant()->status->value,
                    'connection_error' => $context->tenant()->connection_error_message,
                ],
            ],
        ]);
    }
}
