import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { api } from '@/services/api';
import { useTenant } from '@/hooks/useSession';
import { PageHeader } from '@/components/PageHeader';
import { DataTable, type Column } from '@/components/DataTable';
import { EmptyState, ErrorState, LoadingState } from '@/components/States';
import { StatusBadge } from '@/components/StatusBadge';
import { Pagination } from '@/pages/UsersPage';
import type { EntraGroup, Paginated } from '@/types/api';

export function GroupsPage() {
    const tenant = useTenant();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, error, refetch, isFetching } = useQuery({
        queryKey: ['groups', tenant.slug, search, page],
        queryFn: async () =>
            (
                await api.get<Paginated<EntraGroup>>(`/tenants/${tenant.slug}/groups`, {
                    params: { search: search || undefined, page },
                })
            ).data,
        placeholderData: keepPreviousData,
    });

    const columns: Column<EntraGroup>[] = [
        {
            key: 'name',
            header: 'Group',
            render: (group) => (
                <div>
                    <p className="font-medium text-content">{group.display_name ?? '—'}</p>
                    {group.description && <p className="text-xs text-content-muted">{group.description}</p>}
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            render: (group) => (
                <div className="flex flex-wrap gap-1">
                    {group.security_enabled && <StatusBadge tone="neutral">Security</StatusBadge>}
                    {group.group_types.includes('Unified') && <StatusBadge tone="neutral">Microsoft 365</StatusBadge>}
                    {group.has_dynamic_membership && <StatusBadge tone="neutral">Dynamic</StatusBadge>}
                    {group.is_directory_synced && <StatusBadge tone="neutral">Synced from AD</StatusBadge>}
                </div>
            ),
        },
        {
            key: 'members',
            header: 'User members',
            numeric: true,
            render: (group) => group.member_count,
        },
        {
            key: 'editable',
            header: 'Membership',
            render: (group) =>
                group.is_membership_editable ? (
                    'Editable'
                ) : (
                    <span
                        className="text-content-muted"
                        title={
                            group.has_dynamic_membership
                                ? 'Membership is computed from a rule and cannot be edited directly.'
                                : 'Mastered on-premises and read-only in the cloud.'
                        }
                    >
                        Read-only
                    </span>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Groups"
                description={
                    data
                        ? `${data.meta.total.toLocaleString()} groups · counts include user members only`
                        : undefined
                }
                actions={
                    <input
                        type="search"
                        value={search}
                        placeholder="Filter by name or mail"
                        aria-label="Filter groups"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                        className="w-72 rounded-md border-0 bg-surface-raised px-3 py-1.5 text-sm ring-1 ring-border-subtle ring-inset focus:ring-2 focus:ring-brand"
                    />
                }
            />

            {isLoading && <LoadingState label="Loading groups" />}
            {error && <ErrorState error={error} onRetry={() => void refetch()} />}

            {data && (
                <>
                    <DataTable
                        columns={columns}
                        rows={data.data}
                        rowKey={(group) => group.id}
                        emptyState={<EmptyState title="No groups match" />}
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
