import { Link } from 'react-router-dom';
import { useSession } from '@/hooks/useSession';
import { LoadingState } from '@/components/States';
import { StatusBadge } from '@/components/StatusBadge';

/**
 * Tenant picker, and the entry point for connecting a new one.
 *
 * Only tenants the signed-in administrator is a member of are listed. There is
 * no view of tenants on the platform as a whole.
 */
export function TenantsPage() {
    const { tenants, isLoading } = useSession();

    if (isLoading) {
        return <LoadingState label="Loading your tenants" />;
    }

    return (
        <div className="mx-auto max-w-2xl p-8">
            <h1 className="text-xl font-semibold text-content">Tenants</h1>
            <p className="mt-1 text-sm text-content-muted">Microsoft 365 environments you have access to.</p>

            {tenants.length === 0 ? (
                <div className="mt-6 rounded-lg border border-dashed border-border-subtle p-8 text-center">
                    <p className="text-sm font-medium text-content">No tenants connected yet</p>
                    <p className="mx-auto mt-1 max-w-md text-sm text-content-muted">
                        Connecting a tenant requires a Global Administrator of that Microsoft 365 environment to grant
                        admin consent.
                    </p>
                </div>
            ) : (
                <ul className="mt-6 divide-y divide-border-subtle/60 rounded-lg border border-border-subtle bg-surface-raised">
                    {tenants.map((tenant) => (
                        <li key={tenant.id} className="flex items-center justify-between gap-4 px-4 py-3">
                            <div className="min-w-0">
                                <Link
                                    to={`/t/${tenant.slug}`}
                                    className="text-sm font-medium text-brand hover:underline"
                                >
                                    {tenant.name}
                                </Link>
                                <p className="truncate text-xs text-content-muted">{tenant.default_domain}</p>
                            </div>

                            <div className="flex items-center gap-2">
                                <span className="text-xs text-content-muted">{tenant.role}</span>
                                <StatusBadge tone={tenant.is_connected ? 'ok' : 'warn'}>
                                    {tenant.status_label}
                                </StatusBadge>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-6">
                <a
                    href="/auth/microsoft/consent"
                    className="inline-block rounded-md bg-brand px-4 py-2 text-sm font-medium text-surface hover:bg-brand-strong"
                >
                    Connect a Microsoft 365 tenant
                </a>
                <p className="mt-2 text-xs text-content-muted">
                    You will be taken to Microsoft to review exactly which permissions this platform is requesting
                    before anything is granted.
                </p>
            </div>
        </div>
    );
}
