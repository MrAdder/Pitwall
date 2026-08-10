import { NavLink, Outlet, useNavigate, useParams } from 'react-router-dom';
import clsx from 'clsx';
import { useSession } from '@/hooks/useSession';
import { LoadingState } from '@/components/States';
import { GlobalSearch } from '@/features/search/GlobalSearch';
import type { Permission } from '@/types/api';

interface NavItem {
    to: string;
    label: string;
    permission: Permission;
}

const navigation: NavItem[] = [
    { to: '', label: 'Dashboard', permission: 'tenant.read' },
    { to: 'users', label: 'Users', permission: 'users.read' },
    { to: 'devices', label: 'Devices', permission: 'devices.read' },
    { to: 'groups', label: 'Groups', permission: 'groups.read' },
    { to: 'audit', label: 'Audit', permission: 'audit.read' },
];

export function AppLayout() {
    const { user, tenant, tenants, isLoading, can } = useSession();
    const { tenantSlug } = useParams();
    const navigate = useNavigate();

    if (isLoading) {
        return <LoadingState label="Loading your tenants" />;
    }

    if (!user) {
        window.location.href = '/login';

        return null;
    }

    if (!tenant) {
        navigate('/tenants', { replace: true });

        return null;
    }

    const base = `/t/${tenant.slug}`;

    return (
        <div className="flex h-full">
            <aside className="border-border-subtle bg-surface-sunken flex w-60 shrink-0 flex-col border-r">
                {/* Wordmark rather than the banner: the banner is 4:1 and would
                    dominate a 240px sidebar. */}
                <div className="border-border-subtle border-b px-4 py-3">
                    <p className="text-content text-sm font-semibold tracking-wide">
                        PIT<span className="text-brand">WALL</span>
                    </p>
                    <p className="text-content-muted/70 text-[10px] tracking-[0.2em] uppercase">IT Operations</p>
                </div>

                <div className="border-border-subtle border-b px-4 py-3">
                    <select
                        value={tenantSlug ?? tenant.slug}
                        onChange={(event) => navigate(`/t/${event.target.value}`)}
                        aria-label="Tenant"
                        className="bg-surface-raised text-content ring-border-subtle focus:ring-brand w-full rounded-md border-0 px-2 py-1.5 text-sm font-medium ring-1 ring-inset focus:ring-2"
                    >
                        {tenants.map((option) => (
                            <option key={option.id} value={option.slug}>
                                {option.name}
                            </option>
                        ))}
                    </select>

                    {!tenant.is_connected && (
                        <p className="text-warning mt-2 text-xs">{tenant.status_label}. Data may be out of date.</p>
                    )}
                </div>

                <nav className="flex-1 space-y-0.5 p-2">
                    {navigation
                        .filter((item) => can(item.permission))
                        .map((item) => (
                            <NavLink
                                key={item.to}
                                to={item.to === '' ? base : `${base}/${item.to}`}
                                end={item.to === ''}
                                className={({ isActive }) =>
                                    clsx(
                                        'block rounded-md px-3 py-1.5 text-sm',
                                        isActive
                                            ? 'bg-brand/15 text-brand font-medium'
                                            : 'text-content-muted hover:bg-surface-raised hover:text-content',
                                    )
                                }
                            >
                                {item.label}
                            </NavLink>
                        ))}
                </nav>

                <div className="border-border-subtle text-content-muted border-t p-3 text-xs">
                    <p className="text-content truncate font-medium">{user.name}</p>
                    <p className="truncate">{user.email}</p>
                    <form method="POST" action="/auth/microsoft/logout" className="mt-2">
                        <input type="hidden" name="_token" value={csrfToken()} />
                        <button type="submit" className="hover:text-content underline">
                            Sign out
                        </button>
                    </form>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="border-border-subtle bg-surface-sunken border-b px-6 py-2">
                    <GlobalSearch />
                </header>

                <main className="min-h-0 flex-1 overflow-y-auto">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
