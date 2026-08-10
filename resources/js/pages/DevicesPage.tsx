import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '@/services/api';
import { useTenant } from '@/hooks/useSession';
import { PageHeader, relativeTime } from '@/components/PageHeader';
import { DataTable, type Column } from '@/components/DataTable';
import { EmptyState, ErrorState, LoadingState } from '@/components/States';
import { StatusBadge, complianceTone } from '@/components/StatusBadge';
import { Pagination } from '@/pages/UsersPage';
import type { ManagedDevice, Paginated } from '@/types/api';

export function DevicesPage() {
    const tenant = useTenant();
    const navigate = useNavigate();
    const [params] = useSearchParams();

    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    // Dashboard tiles link here with a filter applied, so the numbers on the
    // overview are one click from the rows behind them.
    const complianceState = params.get('compliance_state');
    const staleDays = params.get('stale_days');

    const { data, isLoading, error, refetch, isFetching } = useQuery({
        queryKey: ['devices', tenant.slug, search, complianceState, staleDays, page],
        queryFn: async () =>
            (
                await api.get<Paginated<ManagedDevice>>(`/tenants/${tenant.slug}/devices`, {
                    params: {
                        search: search || undefined,
                        compliance_state: complianceState || undefined,
                        stale_days: staleDays || undefined,
                        page,
                    },
                })
            ).data,
        placeholderData: keepPreviousData,
    });

    const columns: Column<ManagedDevice>[] = [
        {
            key: 'name',
            header: 'Device',
            render: (device) => (
                <div>
                    <p className="font-medium text-content">{device.device_name ?? '—'}</p>
                    <p className="text-xs text-content-muted">
                        {[device.manufacturer, device.model].filter(Boolean).join(' ') || '—'}
                    </p>
                </div>
            ),
        },
        {
            key: 'compliance',
            header: 'Compliance',
            render: (device) => (
                <StatusBadge tone={complianceTone(device.compliance_state)}>
                    {device.compliance_state_label ?? 'Unknown'}
                </StatusBadge>
            ),
        },
        {
            key: 'os',
            header: 'Operating system',
            render: (device) => `${device.operating_system ?? '—'} ${device.os_version ?? ''}`.trim(),
        },
        {
            key: 'user',
            header: 'Primary user',
            render: (device) => device.primary_user.user_principal_name ?? '—',
        },
        {
            key: 'checkin',
            header: 'Last check-in',
            render: (device) => (
                <span className={device.is_stale ? 'text-warning' : undefined}>
                    {relativeTime(device.last_check_in_at)}
                </span>
            ),
        },
        {
            key: 'encryption',
            header: 'Encrypted',
            render: (device) =>
                device.is_encrypted === null ? (
                    <StatusBadge tone="unknown">Unknown</StatusBadge>
                ) : device.is_encrypted ? (
                    'Yes'
                ) : (
                    <StatusBadge tone="warn">No</StatusBadge>
                ),
        },
    ];

    const activeFilter = complianceState ?? (staleDays ? `not checked in for ${staleDays} days` : null);

    return (
        <>
            <PageHeader
                title="Devices"
                description={
                    data
                        ? `${data.meta.total.toLocaleString()} devices${activeFilter ? ` · filtered by ${activeFilter}` : ''}`
                        : undefined
                }
                actions={
                    <input
                        type="search"
                        value={search}
                        placeholder="Filter by name, serial or user"
                        aria-label="Filter devices"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                        className="w-72 rounded-md border-0 bg-surface-raised px-3 py-1.5 text-sm ring-1 ring-border-subtle ring-inset focus:ring-2 focus:ring-brand"
                    />
                }
            />

            {isLoading && <LoadingState label="Loading devices" />}
            {error && <ErrorState error={error} onRetry={() => void refetch()} />}

            {data && (
                <>
                    <DataTable
                        columns={columns}
                        rows={data.data}
                        rowKey={(device) => device.id}
                        onRowClick={(device) => navigate(`/t/${tenant.slug}/devices/${device.id}`)}
                        emptyState={
                            <EmptyState
                                title="No devices match"
                                description="Only synchronised devices are shown. The first synchronisation runs within a few minutes of connecting the tenant."
                            />
                        }
                    />

                    <Pagination
                        page={data.meta.current_page}
                        lastPage={data.meta.last_page}
                        busy={isFetching}
                        onChange={setPage}
                    />
                </>
            )}
        </>
    );
}
