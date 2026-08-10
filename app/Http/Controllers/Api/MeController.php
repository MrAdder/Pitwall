<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who the caller is, and which tenants they may work in.
 *
 * The SPA calls this on boot. It is the only endpoint that is not
 * tenant-scoped, because it is what tells the client which tenants exist for
 * it in the first place.
 */
final class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('memberships.tenant');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,

                // Only tenants this user is a member of. There is no listing
                // of tenants on the platform as a whole.
                'tenants' => TenantResource::collection(
                    $user->memberships->map->tenant->filter()->values(),
                )->toArray($request),
            ],
        ]);
    }
}
