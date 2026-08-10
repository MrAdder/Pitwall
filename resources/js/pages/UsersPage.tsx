import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api } from '@/services/api';
import { useTenant } from '@/hooks/useSession';
import { PageHeader } from '@/components/PageHeader';
import { DataTable, type Column } from '@/components/DataTable';
import { EmptyState, ErrorState, LoadingState } from '@/components/States';
import { StatusBadge } from '@/components/StatusBadge';
import type { EntraUser, Paginated } from '@/types/api';

export function UsersPage() {
    const tenant = useTenant();
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, error, refetch, isFetching } = useQuery({
        queryKey: ['users', tenant.slug, search, page],
        queryFn: async () =>
            (
                await api.get<Paginated<EntraUser>>(`/tenants/${tenant.slug}/users`, {
                    params: { search: search || undefined, page },
                })
            ).data,
        // Keeps the previous page on screen while the next loads, so the table
        // does not collapse to a spinner on every keystroke.
        placeholderData: keepPreviousData,
    });

    const columns: Column<EntraUser>[] = [
        {
            key: 'name',
            header: 'Name',
            render: (user) => (
                <div>
                    <p className="font-medium text-content">{user.display_name ?? '—'}</p>
                    <p className="text-xs text-content-muted">{user.user_principal_name}</p>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (user) =>
                user.account_enabled === null ? (
                    <StatusBadge tone="unknown">Unknown</StatusBadge>
                ) : user.account_enabled ? (
                    <StatusBadge tone="ok">Enabled</StatusBadge>
                ) : (
                    <StatusBadge tone="neutral">Disabled</StatusBadge>
                ),
        },
        {
            key: 'mfa',
            header: 'MFA',
            render: (user) =>
                user.mfa_registered === null ? (
                    <StatusBadge tone="unknown" title="The authentication methods report has not been synchronised.">
                        Unknown
                    </StatusBadge>
                ) : user.mfa_registered ? (
                    <StatusBadge tone="ok" title="Registered for MFA. Enforcement is decided by Conditional Access.">
                        Registered
                    </StatusBadge>
                ) : (
                    <StatusBadge tone="warn">Not registered</StatusBadge>
                ),
        },
        { key: 'department', header: 'Department', render: (user) => user.department ?? '—' },
        { key: 'devices', header: 'Devices', numeric: true, render: (user) => user.device_count },
        { key: 'licenses', header: 'Licences', numeric: true, render: (user) => user.license_count },
    ];

    return (
        <>
            <PageHeader
                title="Users"
                description={data ? `${data.meta.total.toLocaleString()} users in synchronised data` : undefined}
                actions={
                    <input
                        type="search"
                        value={search}
                        placeholder="Filter by name, sign-in name or mail"
                        aria-label="Filter users"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                        className="w-72 rounded-md border-0 bg-surface-raised px-3 py-1.5 text-sm ring-1 ring-border-subtle ring-inset focus:ring-2 focus:ring-brand"
                    />
                }
            />

            {isLoading && <LoadingState label="Loading users" />}
            {error && <ErrorState error={error} onRetry={() => void refetch()} />}

            {data && (
                <>
                    <DataTable
                        columns={columns}
                        rows={data.data}
                        rowKey={(user) => user.id}
                        onRowClick={(user) => navigate(`/t/${tenant.slug}/users/${user.id}`)}
                        emptyState={
                            <EmptyState
                                title={search ? 'No users match that filter' : 'No users have been synchronised yet'}
                                description={
                                    search
                                        ? 'Try a partial name or sign-in name. Only synchronised users are searched.'
                                        : 'The first synchronisation runs within a few minutes of connecting the tenant.'
                                }
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

interface PaginationProps {
    page: number;
    lastPage: number;
    busy: boolean;
    onChange: (page: number) => void;
}

export function Pagination({ page, lastPage, busy, onChange }: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-border-subtle px-6 py-3 text-sm">
            <p className="text-content-muted">
                Page {page} of {lastPage}
            </p>
            <div className="flex gap-2">
                <button
                    type="button"
                    disabled={page <= 1 || busy}
                    onClick={() => onChange(page - 1)}
                    className="rounded-md px-2.5 py-1 ring-1 ring-border-subtle ring-inset disabled:opacity-40"
                >
                    Previous
                </button>
                <button
                    type="button"
                    disabled={page >= lastPage || busy}
                    onClick={() => onChange(page + 1)}
                    className="rounded-md px-2.5 py-1 ring-1 ring-border-subtle ring-inset disabled:opacity-40"
                >
                    Next
                </button>
            </div>
        </div>
    );
}
