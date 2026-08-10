import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import { api } from '@/services/api';
import { useTenant } from '@/hooks/useSession';
import { PageHeader, relativeTime } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/States';
import { StatusBadge } from '@/components/StatusBadge';
import type { Dashboard, Envelope } from '@/types/api';

/**
 * The operational overview.
 *
 * Every tile here is either something to act on or the context needed to judge
 * it. Vanity counts are left out on purpose — a number nobody would act on is
 * noise competing with the ones that matter.
 */
export function DashboardPage() {
    const tenant = useTenant();

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['dashboard', tenant.slug],
        queryFn: async () => (await api.get<Envelope<Dashboard>>(`/tenants/${tenant.slug}/dashboard`)).data.data,
    });

    if (isLoading) return <LoadingState label="Loading dashboard" />;
    if (error) return <ErrorState error={error} onRetry={() => void refetch()} />;
    if (!data) return null;

    const base = `/t/${tenant.slug}`;
    const oldestSync = data.sync.reduce<string | null>(
        (oldest, state) =>
            state.last_successful_at === null || (oldest !== null && state.last_successful_at > oldest)
                ? oldest
                : state.last_successful_at,
        data.sync[0]?.last_successful_at ?? null,
    );

    return (
        <>
            <PageHeader
                title="Overview"
                description={tenant.name}
                syncedAt={oldestSync}
            />

            {data.tenant.connection_error && (
                <div className="mx-6 mt-4 rounded-md border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                    {data.tenant.connection_error}
                </div>
            )}

            <section className="grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4">
                <Tile label="Devices" value={data.devices.total} to={`${base}/devices`} />
                <Tile
                    label="Needs attention"
                    value={data.devices.needs_attention}
                    tone={data.devices.needs_attention > 0 ? 'error' : 'ok'}
                    to={`${base}/devices?compliance_state=noncompliant`}
                />
                <Tile
                    label="Not checked in (14 days)"
                    value={data.devices.not_checked_in_14_days}
                    tone={data.devices.not_checked_in_14_days > 0 ? 'warn' : 'ok'}
                    hint="These report their last known state, not their current one."
                    to={`${base}/devices?stale_days=14`}
                />
                <Tile
                    label="Compliance unknown"
                    value={data.devices.unknown}
                    tone="unknown"
                    hint="Intune has no compliance result for these devices."
                />

                <Tile label="Users" value={data.users.total} to={`${base}/users`} />
                <Tile label="Disabled accounts" value={data.users.disabled} />
                <Tile
                    label="MFA not registered"
                    value={data.users.mfa_not_registered}
                    tone={data.users.mfa_not_registered > 0 ? 'warn' : 'ok'}
                    hint="Registration is not enforcement: Conditional Access decides whether MFA is required."
                />
                <Tile
                    label="MFA state unknown"
                    value={data.users.mfa_registration_unknown}
                    tone="unknown"
                    hint="The authentication methods report has not been synchronised, or is unavailable on this tenant."
                />
            </section>

            <section className="grid gap-6 px-6 pb-8 lg:grid-cols-2">
                <div className="rounded-lg border border-border-subtle bg-surface-raised">
                    <h2 className="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-content">
                        Recent activity
                    </h2>

                    {data.recent_activity.length === 0 ? (
                        <p className="p-4 text-sm text-content-muted">No administrative actions recorded yet.</p>
                    ) : (
                        <ul className="divide-y divide-border-subtle/60">
                            {data.recent_activity.map((entry) => (
                                <li key={entry.id} className="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                    <div className="min-w-0">
                                        <p className="truncate text-content">
                                            {entry.action_label}
                                            {entry.resource.label && (
                                                <span className="text-content-muted"> · {entry.resource.label}</span>
                                            )}
                                        </p>
                                        <p className="truncate text-xs text-content-muted">
                                            {entry.actor.email ?? 'System'} · {relativeTime(entry.created_at)}
                                        </p>
                                    </div>
                                    <StatusBadge tone={resultTone(entry.result)}>{entry.result_label}</StatusBadge>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="border-t border-border-subtle px-4 py-2">
                        <Link to={`${base}/audit`} className="text-sm text-brand hover:underline">
                            View the full audit log
                        </Link>
                    </div>
                </div>

                <div className="rounded-lg border border-border-subtle bg-surface-raised">
                    <h2 className="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-content">
                        Synchronisation
                    </h2>

                    <ul className="divide-y divide-border-subtle/60">
                        {data.sync.length === 0 && (
                            <li className="px-4 py-3 text-sm text-content-muted">
                                Nothing has been synchronised yet. The first run happens within a few minutes of
                                connecting the tenant.
                            </li>
                        )}
                        {data.sync.map((state) => (
                            <li key={state.resource} className="flex items-center justify-between px-4 py-2 text-sm">
                                <span className="text-content">{state.resource_label}</span>
                                <span className="flex items-center gap-2">
                                    <span className="text-xs text-content-muted">
                                        {relativeTime(state.last_successful_at)}
                                    </span>
                                    <StatusBadge
                                        tone={state.status === 'failed' ? 'error' : state.is_stale ? 'warn' : 'ok'}
                                        title={state.error_code ?? undefined}
                                    >
                                        {state.status === 'failed' ? 'Failed' : state.is_stale ? 'Stale' : 'Current'}
                                    </StatusBadge>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>
        </>
    );
}

interface TileProps {
    label: string;
    value: number;
    tone?: 'ok' | 'warn' | 'error' | 'unknown';
    hint?: string;
    to?: string;
}

function Tile({ label, value, tone, hint, to }: TileProps) {
    const content = (
        <div
            className={clsx(
                'rounded-lg border bg-surface-raised p-4',
                tone === 'error' ? 'border-danger/40' : tone === 'warn' ? 'border-warning/40' : 'border-border-subtle',
                to && 'transition hover:border-brand/50',
            )}
        >
            <p className="text-sm text-content-muted">{label}</p>
            <p
                className={clsx(
                    'tabular mt-1 text-2xl font-semibold',
                    tone === 'error' ? 'text-danger' : tone === 'warn' ? 'text-warning' : 'text-content',
                )}
            >
                {value.toLocaleString()}
            </p>
            {hint && <p className="mt-1 text-xs text-content-muted">{hint}</p>}
        </div>
    );

    return to ? <Link to={to}>{content}</Link> : content;
}

function resultTone(result: string): 'ok' | 'warn' | 'error' | 'neutral' {
    switch (result) {
        case 'success':
            return 'ok';
        case 'failure':
        case 'denied':
            return 'error';
        case 'pending':
            return 'warn';
        default:
            return 'neutral';
    }
}
