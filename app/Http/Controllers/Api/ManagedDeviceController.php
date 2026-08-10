<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListManagedDevicesRequest;
use App\Http\Resources\EntraUserResource;
use App\Http\Resources\ManagedDeviceResource;
use App\Models\ManagedDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ManagedDeviceController extends Controller
{
    public function index(ListManagedDevicesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $devices = ManagedDevice::query()
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->search($term))
            ->when($filters['compliance_state'] ?? null, fn ($query, string $s) => $query->where('compliance_state', $s))
            ->when($filters['operating_system'] ?? null, fn ($query, string $os) => $query->where('operating_system', $os))
            ->when($filters['stale_days'] ?? null, fn ($query, int $days) => $query->notCheckedInFor($days))
            ->orderBy($filters['sort'] ?? 'device_name', $filters['direction'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return ManagedDeviceResource::collection($devices);
    }

    public function show(Request $request, ManagedDevice $device): JsonResponse
    {
        $device->load('primaryUser');

        return response()->json([
            'data' => [
                ...(new ManagedDeviceResource($device))->toArray($request),

                'primary_user_detail' => $device->primaryUser === null
                    ? null
                    : (new EntraUserResource($device->primaryUser))->toArray($request),

                'hardware' => [
                    'manufacturer' => $device->manufacturer,
                    'model' => $device->model,
                    'serial_number' => $device->serial_number,
                    'wifi_mac_address' => $device->wifi_mac_address,
                    'ethernet_mac_address' => $device->ethernet_mac_address,
                ],

                'management' => [
                    'agent' => $device->management_agent,
                    'enrollment_type' => $device->enrollment_type,
                    'registration_state' => $device->registration_state,
                    'owner_type' => $device->owner_type,
                    'is_supervised' => $device->is_supervised,
                    'jail_broken' => $device->jail_broken,
                ],
            ],
        ]);
    }
}
