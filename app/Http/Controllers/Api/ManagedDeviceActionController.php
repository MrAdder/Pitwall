<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Devices\Actions\RequestDeviceSync;
use App\Http\Controllers\Controller;
use App\Integrations\MicrosoftGraph\Intune\ManagedDevicesResource;
use App\Models\ManagedDevice;
use Illuminate\Http\JsonResponse;

/**
 * Actions taken against an Intune managed device.
 *
 * Only the non-destructive sync action is implemented for the MVP. Retire and
 * wipe are intentionally not exposed yet: the Graph calls exist in
 * {@see ManagedDevicesResource}, but
 * an irreversible action must not ship before the safe-change preview and
 * confirmation flow it is supposed to sit behind. See docs/roadmap.md.
 */
final class ManagedDeviceActionController extends Controller
{
    /**
     * Ask the device to check in with Intune.
     */
    public function sync(ManagedDevice $device, RequestDeviceSync $action): JsonResponse
    {
        $action->handle($device);

        return response()->json([
            'message' => 'A check-in has been requested. Intune will contact the device when it is next online; this is not immediate.',
        ], 202);
    }
}
