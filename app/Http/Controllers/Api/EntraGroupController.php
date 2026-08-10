<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntraGroupResource;
use App\Http\Resources\EntraUserResource;
use App\Models\EntraGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EntraGroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $groups = EntraGroup::query()
            ->when($validated['search'] ?? null, fn ($query, string $term) => $query->search($term))
            ->orderBy('display_name')
            ->paginate($validated['per_page'] ?? 50)
            ->withQueryString();

        return EntraGroupResource::collection($groups);
    }

    public function show(Request $request, EntraGroup $group): JsonResponse
    {
        return response()->json([
            'data' => (new EntraGroupResource($group))->toArray($request),
        ]);
    }

    /**
     * User members of the group.
     *
     * Paginated separately from the group itself: a group can hold tens of
     * thousands of people, and the group page should render before they are
     * all counted.
     */
    public function members(Request $request, EntraGroup $group): AnonymousResourceCollection
    {
        $members = $group->members()
            ->orderBy('display_name')
            ->paginate((int) $request->integer('per_page', 50))
            ->withQueryString();

        return EntraUserResource::collection($members);
    }
}
