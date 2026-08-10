import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '@/services/api';
import { useSession, useTenant } from '@/hooks/useSession';
import { PageHeader, relativeTime } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/States';
import { StatusBadge, complianceTone } from '@/components/StatusBadge';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import type { Envelope, ManagedDevice } from '@/types/api';

interface DeviceDetail extends ManagedDevice {
    hardware: {
        manufacturer: string | null;
        model: string | null;
        serial_number: string | null;
        wifi_mac_address: string | null;
        ethernet_mac_address: string | null;
    };
    management: {
        agent: string | null;
        enrollment_type: string | null;
        registration_state: string | null;
        owner_type: string | null;
        is_supervised: boolean | null;
        jail_broken: boolean | null;
    };
}

export function DeviceDetailPage() {
    const tenant = useTenant();
    const { can } = useSession();
    const { deviceId } = useParams();

    const [confirming, setConfirming] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);

    const { data: device, isLoading, error, refetch } = useQuery({
        queryKey: ['device', tenant.slug, deviceId],
        queryFn: async () =>
            (await api.get<Envelope<DeviceDetail>>(`/tenants/${tenant.slug}/devices/${deviceId}`)).data.data,
    });

    const sync = useMutation({
        mutationFn: async () =>
            (await api.post<{ message: string }>(`/tenants/${tenant.slug}/devices/${deviceId}/sync`)).data,
        onSuccess: (response) => {
            setNotice(response.message);
            setActionError(null);
            setConfirming(false);
        },
        onError: (mutationError) => {
            setActionError(errorMessage(mutationError));
            setConfirming(false);
        },
    });

    if (isLoading) return <LoadingState label="Loading device" />;
    if (error) return <ErrorState error={error} onRetry={() => void refetch()} />;
    if (!device) return null;

    return (
        <>
            <PageHeader
                title={device.device_name ?? 'Device'}
                description={[device.hardware.manufacturer, device.hardware.model].filter(Boolean).join(' ')}
                syncedAt={device.synced_at}
                actions={
                    can('devices.sync') && (
                        <button
                            type="button"
                            onClick={() => setConfirming(true)}
                            className="rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-surface hover:bg-brand-strong"
                        >
                            Request check-in
                        </button>
                    )
                }
            />

            {device.is_stale && (
                <p className="mx-6 mt-4 rounded-md border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                    This device last checked in {relativeTime(device.last_check_in_at)}. Everything below is its last
                    known state, not its current one.
                </p>
            )}

            {notice && (
                <p className="mx-6 mt-4 rounded-md border border-brand/40 bg-brand/10 p-3 text-sm text-brand">{notice}</p>
            )}

            {actionError && (
                <p className="mx-6 mt-4 rounded-md border border-danger/40 bg-danger/10 p-3 text-sm text-danger">
                    {actionError}
                </p>
            )}

            <div className="grid gap-6 p-6 lg:grid-cols-3">
                <Panel title="Status">
                    <Field label="Compliance">
                        <StatusBadge tone={complianceTone(device.compliance_state)}>
                            {device.compliance_state_label ?? 'Unknown'}
                        </StatusBadge>
                    </Field>
                    <Field label="Encrypted">
                        {device.is_encrypted === null ? (
                            <StatusBadge tone="unknown">Unknown</StatusBadge>
                        ) : device.is_encrypted ? (
                            'Yes'
                        ) : (
                            <StatusBadge tone="warn">No</StatusBadge>
                        )}
                    </Field>
                    <Field label="Operating system">
                        {`${device.operating_system ?? '—'} ${device.os_version ?? ''}`.trim()}
                    </Field>
                    <Field label="Last check-in">{relativeTime(device.last_check_in_at)}</Field>
                    <Field label="Enrolled">{relativeTime(device.enrolled_at)}</Field>
                    <Field label="Primary user">
                        {device.primary_user.entra_user_id ? (
                            <Link
                                to={`/t/${tenant.slug}/users/${device.primary_user.entra_user_id}`}
                                className="text-brand hover:underline"
                            >
                                {device.primary_user.user_principal_name}
                            </Link>
                        ) : (
                            (device.primary_user.user_principal_name ?? '—')
                        )}
                    </Field>
                </Panel>

                <Panel title="Hardware">
                    <Field label="Manufacturer">{device.hardware.manufacturer ?? '—'}</Field>
                    <Field label="Model">{device.hardware.model ?? '—'}</Field>
                    <Field label="Serial number">{device.hardware.serial_number ?? '—'}</Field>
                    <Field label="Storage">{formatStorage(device.storage)}</Field>
                    <Field label="Wi-Fi MAC">{device.hardware.wifi_mac_address ?? '—'}</Field>
                    <Field label="Ethernet MAC">{device.hardware.ethernet_mac_address ?? '—'}</Field>
                </Panel>

                <Panel title="Management">
                    <Field label="Ownership">{device.management.owner_type ?? '—'}</Field>
                    <Field label="Agent">{device.management.agent ?? '—'}</Field>
                    <Field label="Enrollment">{device.management.enrollment_type ?? '—'}</Field>
                    <Field label="Registration">{device.management.registration_state ?? '—'}</Field>
                    <Field label="Supervised">{yesNoUnknown(device.management.is_supervised)}</Field>
                    <Field label="Jailbroken">{yesNoUnknown(device.management.jail_broken)}</Field>
                </Panel>
            </div>

            <ConfirmDialog
                open={confirming}
                title="Request a check-in?"
                description="Intune will contact the device the next time it is online. This is a request, not an instruction the device is guaranteed to receive — a machine that is powered off or offline gets nothing until it reconnects."
                impact={[
                    { label: 'Device', value: device.device_name ?? '—' },
                    { label: 'Last check-in', value: relativeTime(device.last_check_in_at) },
                ]}
                confirmLabel="Request check-in"
                busy={sync.isPending}
                onConfirm={() => sync.mutate()}
                onCancel={() => setConfirming(false)}
            />
        </>
    );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="rounded-lg border border-border-subtle bg-surface-raised p-4">
            <h2 className="text-sm font-semibold text-content">{title}</h2>
            <dl className="mt-3 space-y-2 text-sm">{children}</dl>
        </section>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-content-muted">{label}</dt>
            <dd className="text-right font-medium text-content">{children}</dd>
        </div>
    );
}

function yesNoUnknown(value: boolean | null): React.ReactNode {
    if (value === null) {
        return <StatusBadge tone="unknown">Unknown</StatusBadge>;
    }

    return value ? 'Yes' : 'No';
}

function formatStorage({ total_bytes, free_bytes }: ManagedDevice['storage']): string {
    if (total_bytes === null) {
        return '—';
    }

    const gigabytes = (bytes: number) => `${Math.round(bytes / 1_000_000_000)} GB`;

    return free_bytes === null
        ? gigabytes(total_bytes)
        : `${gigabytes(free_bytes)} free of ${gigabytes(total_bytes)}`;
}
