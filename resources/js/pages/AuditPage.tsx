import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { api } from '@/services/api';
import { useTenant } from '@/hooks/useSession';
import { PageHeader } from '@/components/PageHeader';
import { DataTable, type Column } from '@/components/DataTable';
import { EmptyState, ErrorState, LoadingState } from '@/components/States';
import { StatusBadge } from '@/components/StatusBadge';
import { Pagination } from '@/pages/UsersPage';
import type { AuditLogEntry, Paginated } from '@/types/api';

/**
 * The audit trail.
 *
 * Read-only, because it is append-only: there is no endpoint to edit or delete
 * an entry, and the model refuses both.
 */
export function AuditPage() {
    const tenant = useTenant();
    const [page, setPage] = useState(1);

    const { data, isLoading, error, refetch, isFetching } = useQuery({
        queryKey: ['audit', tenant.slug, page],
        queryFn: async () =>
            (await api.get<Paginated<AuditLogEntry>>(`/tenants/${tenant.slug}/audit`, { params: { page } })).data,
        placeholderData: keepPreviousData,
    });

    const columns: Column<AuditLogEntry>[] = [
        {
            key: 'when',
            header: 'When',
            render: (entry) => (
                <span className="tabular whitespace-nowrap">
                    {entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}
                </span>
            ),
        },
        {
            key: 'action',
            header: 'Action',
            render: (entry) => (
                <div>
                    <p className="font-medium text-content">{entry.action_label}</p>
                    {entry.resource.label && <p className="text-xs text-content-muted">{entry.resource.label}</p>}
                </div>
            ),
        },
        {
            key: 'actor',
            header: 'Actor',
            render: (entry) => (
                <div>
                    <p>{entry.actor.name ?? 'System'}</p>
                    <p className="text-xs text-content-muted">{entry.actor.email ?? entry.channel}</p>
                </div>
            ),
        },
        {
            key: 'result',
            header: 'Result',
            render: (entry) => (
                <StatusBadge
                    tone={
                        entry.result === 'success'
                            ? 'ok'
                            : entry.result === 'pending'
                              ? 'warn'
                              : entry.result === 'denied'
                                ? 'error'
                                : 'error'
                    }
                    title={entry.error_code ?? undefined}
                >
                    {entry.result_label}
                </StatusBadge>
            ),
        },
        {
            key: 'change',
            header: 'Change',
            render: (entry) => <ChangeSummary entry={entry} />,
        },
    ];

    return (
        <>
            <PageHeader
                title="Audit"
                description="Every administrative action taken through this platform. Entries cannot be edited or deleted."
            />

            {isLoading && <LoadingState label="Loading audit log" />}
            {error && <ErrorState error={error} onRetry={() => void refetch()} />}

            {data && (
                <>
                    <DataTable
                        columns={columns}
                        rows={data.data}
                        rowKey={(entry) => entry.id}
                        emptyState={
                            <EmptyState
                                title="Nothing recorded yet"
                                description="Actions taken through the platform appear here as they happen."
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

/**
 * Before/after for the fields the action actually touched.
 */
function ChangeSummary({ entry }: { entry: AuditLogEntry }) {
    const keys = Object.keys(entry.new_state ?? {});

    if (keys.length === 0) {
        return <span className="text-content-muted/70">—</span>;
    }

    return (
        <ul className="space-y-0.5 text-xs">
            {keys.map((key) => (
                <li key={key}>
                    <span className="text-content-muted">{key}: </span>
                    <span className="text-content-muted line-through">{format(entry.previous_state?.[key])}</span>
                    <span className="text-content"> → {format(entry.new_state?.[key])}</span>
                </li>
            ))}
        </ul>
    );
}

function format(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'true' : 'false';

    return String(value);
}
