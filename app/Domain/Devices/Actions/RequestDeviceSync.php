<?php

declare(strict_types=1);

namespace App\Domain\Devices\Actions;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\AuditResult;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Models\ManagedDevice;

/**
 * Asks a device to check in with Intune.
 *
 * Non-destructive and the usual first step when a device looks out of date, so
 * it sits behind devices.sync rather than the privileged permissions.
 *
 * The outcome is recorded as pending, not success: Graph accepting the call
 * means Intune queued a notification, not that the device received it. A laptop
 * in a bag gets nothing until it is opened.
 */
final readonly class RequestDeviceSync
{
    public function __construct(
        private GraphClientFactory $graph,
        private AuditLogger $audit,
    ) {}

    public function handle(ManagedDevice $device): void
    {
        $entry = new AuditEntry(
            action: AuditAction::DeviceSyncRequested,
            result: AuditResult::Pending,
            resourceType: 'device',
            resourceId: $device->id,
            resourceMicrosoftId: $device->microsoft_id,
            resourceLabel: $device->device_name,
            context: ['last_check_in' => $device->last_sync_date_time?->toIso8601String()],
        );

        $this->audit->around($entry, function () use ($device): void {
            $this->graph->managedDevices()->sync($device->microsoft_id);
        });
    }
}
