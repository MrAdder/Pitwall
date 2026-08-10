<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListEntraUsersRequest;
use App\Http\Resources\EntraGroupResource;
use App\Http\Resources\EntraUserResource;
use App\Http\Resources\ManagedDeviceResource;
use App\Models\EntraUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reading Entra ID users from the local projection.
 *
 * Reads never touch Graph. A user list that made a live Graph call per page
 * view would be slow, would burn the tenant's throttling budget, and would
 * make search across 40,000 users impossible. Freshness is communicated
 * instead, via the sync state on the dashboard and the per-record synced_at.
 */
final class EntraUserController extends Controller
{
    public function index(ListEntraUsersRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $users = EntraUser::query()
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->search($term))
            ->when(
                array_key_exists('enabled', $filters),
                fn ($query) => $query->where('account_enabled', $filters['enabled']),
            )
            ->when($filters['department'] ?? null, fn ($query, string $d) => $query->where('department', $d))
            ->orderBy($filters['sort'] ?? 'display_name', $filters['direction'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return EntraUserResource::collection($users);
    }

    /**
     * A user's full operational picture in one response.
     *
     * Groups, devices and licences are included rather than left to follow-up
     * requests, because the point of the page is to answer "what is going on
     * with this person" without five round trips.
     */
    public function show(Request $request, EntraUser $user): JsonResponse
    {
        $user->load(['groups', 'devices']);

        return response()->json([
            'data' => [
                ...(new EntraUserResource($user))->toArray($request),

                'groups' => EntraGroupResource::collection($user->groups)->toArray($request),
                'devices' => ManagedDeviceResource::collection($user->devices)->toArray($request),

                'licenses' => [
                    'count' => $user->assigned_license_count,
                    'sku_ids' => $user->assigned_license_skus ?? [],
                ],

                'sign_in' => [
                    'last_interactive_at' => $user->last_sign_in_at?->toIso8601String(),
                    'last_non_interactive_at' => $user->last_non_interactive_sign_in_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
