import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '@/services/api';
import { useSession, useTenant } from '@/hooks/useSession';
import { PageHeader, relativeTime } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/States';
import { StatusBadge } from '@/components/StatusBadge';
import { ConfirmDialog, type ImpactRow } from '@/components/ConfirmDialog';
import type { EntraUserDetail, Envelope } from '@/types/api';

type PendingAction = 'disable' | 'enable' | 'revoke-sessions' | null;

/**
 * One page answering "what is going on with this person" — status, MFA,
 * licences, groups, devices — with the actions a help desk actually needs.
 */
export function UserDetailPage() {
    const tenant = useTenant();
    const { can } = useSession();
    const { userId } = useParams();
    const queryClient = useQueryClient();

    const [pending, setPending] = useState<PendingAction>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const { data: user, isLoading, error, refetch } = useQuery({
        queryKey: ['user', tenant.slug, userId],
        queryFn: async () =>
            (await api.get<Envelope<EntraUserDetail>>(`/tenants/${tenant.slug}/users/${userId}`)).data.data,
    });

    const action = useMutation({
        mutationFn: async (path: Exclude<PendingAction, null>) =>
            (await api.post<{ message: string }>(`/tenants/${tenant.slug}/users/${userId}/${path}`)).data,
        onSuccess: (response) => {
            setNotice(response.message);
            setActionError(null);
            setPending(null);
            void queryClient.invalidateQueries({ queryKey: ['user', tenant.slug, userId] });
        },
        onError: (mutationError) => {
            setActionError(errorMessage(mutationError));
            setPending(null);
        },
    });

    if (isLoading) return <LoadingState label="Loading user" />;
    if (error) return <ErrorState error={error} onRetry={() => void refetch()} />;
    if (!user) return null;

    const base = `/t/${tenant.slug}`;
    const canToggle = can('users.disable') && !user.is_directory_synced;

    return (
        <>
            <PageHeader
                title={user.display_name ?? user.user_principal_name ?? 'User'}
                description={user.user_principal_name ?? undefined}
                syncedAt={user.synced_at}
                actions={
                    <div className="flex gap-2">
                        {can('users.revoke_sessions') && (
                            <button
                                type="button"
                                onClick={() => setPending('revoke-sessions')}
                                className="rounded-md bg-surface-raised px-3 py-1.5 text-sm font-medium text-content ring-1 ring-border-subtle ring-inset hover:bg-surface"
                            >
                                Revoke sessions
                            </button>
                        )}

                        {canToggle && (
                            <button
                                type="button"
                                onClick={() => setPending(user.account_enabled ? 'disable' : 'enable')}
                                className="rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-surface hover:bg-brand-strong"
                            >
                                {user.account_enabled ? 'Disable account' : 'Enable account'}
                            </button>
                        )}
                    </div>
                }
            />

            {user.is_directory_synced && (
                <p className="mx-6 mt-4 rounded-md border border-border-subtle bg-surface-sunken p-3 text-sm text-content">
                    This account is synchronised from on-premises Active Directory. Its enabled state and most
                    attributes must be changed there.
                </p>
            )}

            {notice && (
                <p className="mx-6 mt-4 rounded-md border border-brand/40 bg-brand/10 p-3 text-sm text-brand">
                    {notice}
                </p>
            )}

            {actionError && (
                <p className="mx-6 mt-4 rounded-md border border-danger/40 bg-danger/10 p-3 text-sm text-danger">
                    {actionError}
                </p>
            )}

            <div className="grid gap-6 p-6 lg:grid-cols-3">
                <section className="rounded-lg border border-border-subtle bg-surface-raised p-4 lg:col-span-1">
                    <h2 className="text-sm font-semibold text-content">Account</h2>
                    <dl className="mt-3 space-y-2 text-sm">
                        <Field label="Status">
                            {user.account_enabled === null ? (
                                <StatusBadge tone="unknown">Unknown</StatusBadge>
                            ) : user.account_enabled ? (
                                <StatusBadge tone="ok">Enabled</StatusBadge>
                            ) : (
                                <StatusBadge tone="neutral">Disabled</StatusBadge>
                            )}
                        </Field>
                        <Field label="MFA">
                            {user.mfa_registered === null ? (
                                <StatusBadge tone="unknown">Unknown</StatusBadge>
                            ) : user.mfa_registered ? (
                                <StatusBadge tone="ok">Registered</StatusBadge>
                            ) : (
                                <StatusBadge tone="warn">Not registered</StatusBadge>
                            )}
                        </Field>
                        <Field label="Mail">{user.mail ?? '—'}</Field>
                        <Field label="Job title">{user.job_title ?? '—'}</Field>
                        <Field label="Department">{user.department ?? '—'}</Field>
                        <Field label="Office">{user.office_location ?? '—'}</Field>
                        <Field label="Type">{user.user_type ?? '—'}</Field>
                        <Field label="Last sign-in">{relativeTime(user.sign_in.last_interactive_at)}</Field>
                        <Field label="Licences">{user.licenses.count}</Field>
                    </dl>
                </section>

                <section className="rounded-lg border border-border-subtle bg-surface-raised lg:col-span-1">
                    <h2 className="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-content">
                        Groups ({user.groups.length})
                    </h2>
                    {user.groups.length === 0 ? (
                        <p className="p-4 text-sm text-content-muted">Not a member of any synchronised group.</p>
                    ) : (
                        <ul className="divide-y divide-border-subtle/60">
                            {user.groups.map((group) => (
                                <li key={group.id} className="px-4 py-2 text-sm">
                                    <Link to={`${base}/groups/${group.id}`} className="text-brand hover:underline">
                                        {group.display_name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-lg border border-border-subtle bg-surface-raised lg:col-span-1">
                    <h2 className="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-content">
                        Devices ({user.devices.length})
                    </h2>
                    {user.devices.length === 0 ? (
                        <p className="p-4 text-sm text-content-muted">No managed devices assigned to this user.</p>
                    ) : (
                        <ul className="divide-y divide-border-subtle/60">
                            {user.devices.map((device) => (
                                <li key={device.id} className="flex items-center justify-between px-4 py-2 text-sm">
                                    <Link to={`${base}/devices/${device.id}`} className="text-brand hover:underline">
                                        {device.device_name}
                                    </Link>
                                    <StatusBadge tone={device.compliance_state === 'compliant' ? 'ok' : 'warn'}>
                                        {device.compliance_state_label ?? 'Unknown'}
                                    </StatusBadge>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <ConfirmDialog
                open={pending === 'disable'}
                title="Disable this account?"
                description="The user will not be able to sign in to Microsoft 365. This is reversible: you can enable the account again at any time."
                impact={accountImpact(user, false)}
                risk="medium"
                confirmLabel="Disable account"
                busy={action.isPending}
                onConfirm={() => action.mutate('disable')}
                onCancel={() => setPending(null)}
            />

            <ConfirmDialog
                open={pending === 'enable'}
                title="Enable this account?"
                description="The user will be able to sign in to Microsoft 365 again."
                impact={accountImpact(user, true)}
                confirmLabel="Enable account"
                busy={action.isPending}
                onConfirm={() => action.mutate('enable')}
                onCancel={() => setPending(null)}
            />

            <ConfirmDialog
                open={pending === 'revoke-sessions'}
                title="Revoke sign-in sessions?"
                description="Refresh tokens are invalidated and the user is signed out of their sessions. Access tokens already issued stay valid until they expire, typically within an hour, so this is not an instant lockout."
                impact={[{ label: 'User', value: user.user_principal_name ?? '—' }]}
                risk="medium"
                confirmLabel="Revoke sessions"
                busy={action.isPending}
                onConfirm={() => action.mutate('revoke-sessions')}
                onCancel={() => setPending(null)}
            />
        </>
    );
}

function accountImpact(user: EntraUserDetail, enabled: boolean): ImpactRow[] {
    return [
        { label: 'User', value: user.user_principal_name ?? '—' },
        { label: 'Current state', value: user.account_enabled ? 'Enabled' : 'Disabled' },
        { label: 'New state', value: enabled ? 'Enabled' : 'Disabled' },
        { label: 'Assigned devices', value: String(user.devices.length) },
        { label: 'Licences', value: String(user.licenses.count) },
    ];
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-content-muted">{label}</dt>
            <dd className="text-right font-medium text-content">{children}</dd>
        </div>
    );
}
