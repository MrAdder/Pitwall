<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Intune;

use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\GraphClient\GraphRequestOptions;
use App\Integrations\MicrosoftGraph\GraphPermission;
use Generator;

/**
 * Microsoft Graph `/deviceManagement/managedDevices`.
 *
 * @see https://learn.microsoft.com/graph/api/resources/intune-devices-manageddevice
 */
final readonly class ManagedDevicesResource
{
    /** @var list<string> */
    public const SelectProperties = [
        'id',
        'deviceName',
        'managedDeviceOwnerType',
        'operatingSystem',
        'osVersion',
        'complianceState',
        'complianceGracePeriodExpirationDateTime',
        'lastSyncDateTime',
        'enrolledDateTime',
        'manufacturer',
        'model',
        'serialNumber',
        'userId',
        'userPrincipalName',
        'azureADDeviceId',
        'isEncrypted',
        'isSupervised',
        'jailBroken',
        'managementAgent',
        'deviceEnrollmentType',
        'deviceRegistrationState',
        'totalStorageSpaceInBytes',
        'freeStorageSpaceInBytes',
        'wiFiMacAddress',
        'ethernetMacAddress',
    ];

    public function __construct(
        private GraphClient $client,
    ) {}

    /**
     * Every managed device in the tenant.
     *
     * There is no delta endpoint for managedDevices, so device synchronisation
     * is a full enumeration each run. See docs/architecture.md.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function list(): Generator
    {
        return $this->client->paginate('deviceManagement/managedDevices', [
            '$select' => implode(',', self::SelectProperties),
            '$top' => config('graph.page_size.managed_devices'),
        ], GraphRequestOptions::requiring(GraphPermission::ManagedDevicesRead));
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $deviceId): array
    {
        return $this->client->get("deviceManagement/managedDevices/{$deviceId}", [
            '$select' => implode(',', self::SelectProperties),
        ], GraphRequestOptions::requiring(GraphPermission::ManagedDevicesRead))->entity();
    }

    /**
     * Ask the device to check in with Intune.
     *
     * Asynchronous and best effort: Graph accepting the call means the request
     * was queued, not that the device has checked in. A powered-off laptop
     * receives nothing. The UI reflects that wording.
     *
     * @see https://learn.microsoft.com/graph/api/intune-devices-manageddevice-syncdevice
     */
    public function sync(string $deviceId): void
    {
        $this->client->post(
            "deviceManagement/managedDevices/{$deviceId}/syncDevice",
            options: GraphRequestOptions::requiring(GraphPermission::ManagedDevicesReadWrite),
        );
    }

    /**
     * Remove company data and unenrol the device, leaving personal data alone.
     *
     * Destructive and effectively irreversible: the device must be re-enrolled
     * afterwards. Only reachable through the safe-change confirmation flow.
     *
     * @see https://learn.microsoft.com/graph/api/intune-devices-manageddevice-retire
     */
    public function retire(string $deviceId): void
    {
        $this->client->post(
            "deviceManagement/managedDevices/{$deviceId}/retire",
            options: GraphRequestOptions::requiring(GraphPermission::ManagedDevicesPrivileged),
        );
    }

    /**
     * Factory reset the device.
     *
     * The most destructive action the platform can take. Data on the device is
     * not recoverable. Only reachable through the safe-change confirmation
     * flow, and only for roles holding devices.wipe.
     *
     * @param  bool  $keepEnrollmentData  Leave the device enrolled in Intune after the wipe.
     * @param  bool  $keepUserData  Windows only; ignored by other platforms.
     *
     * @see https://learn.microsoft.com/graph/api/intune-devices-manageddevice-wipe
     */
    public function wipe(string $deviceId, bool $keepEnrollmentData = false, bool $keepUserData = false): void
    {
        $this->client->post("deviceManagement/managedDevices/{$deviceId}/wipe", [
            'keepEnrollmentData' => $keepEnrollmentData,
            'keepUserData' => $keepUserData,
        ], GraphRequestOptions::requiring(GraphPermission::ManagedDevicesPrivileged));
    }
}
