<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Access\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\EntraGroupResource;
use App\Http\Resources\EntraUserResource;
use App\Http\Resources\ManagedDeviceResource;
use App\Models\EntraGroup;
use App\Models\EntraUser;
use App\Models\ManagedDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-service search for the current tenant.
 *
 * One box that finds a person, their machines and their groups is the single
 * biggest time saving over the Microsoft portals, where each of those lives in
 * a different console.
 *
 * Served entirely from the local projection, and tenant-scoped by the global
 * scope like everything else. Each section is omitted unless the caller holds
 * the matching read permission, so search cannot become a way around RBAC.
 */
final class SearchController extends Controller
{
    /** Results per section. Deliberately small: this is a jump-to, not a report. */
    private const SectionLimit = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $term = $validated['q'];

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'query' => $term,

                'users' => $this->when($user, Permission::UsersRead, fn (): array => EntraUserResource::collection(
                    EntraUser::search($term)->orderBy('display_name')->limit(self::SectionLimit)->get(),
                )->toArray($request)),

                'devices' => $this->when($user, Permission::DevicesRead, fn (): array => ManagedDeviceResource::collection(
                    ManagedDevice::search($term)->orderBy('device_name')->limit(self::SectionLimit)->get(),
                )->toArray($request)),

                'groups' => $this->when($user, Permission::GroupsRead, fn (): array => EntraGroupResource::collection(
                    EntraGroup::search($term)->orderBy('display_name')->limit(self::SectionLimit)->get(),
                )->toArray($request)),
            ],
        ]);
    }

    /**
     * @param  callable(): array<int, mixed>  $results
     * @return array<int, mixed>
     */
    private function when(User $user, Permission $permission, callable $results): array
    {
        return $user->can($permission->value) ? $results() : [];
    }
}
