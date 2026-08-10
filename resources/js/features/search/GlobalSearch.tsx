import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '@/services/api';
import { useTenant } from '@/hooks/useSession';
import type { Envelope, SearchResults } from '@/types/api';

/**
 * One search box across users, devices and groups.
 *
 * The main thing this product does better than the Microsoft portals, where
 * those three live in separate consoles. Results are grouped by type and link
 * straight to the object.
 */
export function GlobalSearch() {
    const tenant = useTenant();
    const [term, setTerm] = useState('');
    const [open, setOpen] = useState(false);

    const { data, isFetching } = useQuery({
        queryKey: ['search', tenant.slug, term],
        queryFn: async () =>
            (await api.get<Envelope<SearchResults>>(`/tenants/${tenant.slug}/search`, { params: { q: term } })).data
                .data,
        enabled: term.trim().length >= 2,
        staleTime: 10_000,
    });

    const base = `/t/${tenant.slug}`;
    const hasResults =
        data !== undefined && (data.users.length > 0 || data.devices.length > 0 || data.groups.length > 0);

    return (
        <div className="relative max-w-xl">
            <input
                type="search"
                value={term}
                placeholder="Search users, devices and groups"
                aria-label="Search"
                onChange={(event) => setTerm(event.target.value)}
                onFocus={() => setOpen(true)}
                // Delayed so a click on a result registers before the panel closes.
                onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                className="bg-surface-raised text-content placeholder:text-content-muted/70 ring-border-subtle focus:ring-brand w-full rounded-md border-0 px-3 py-1.5 text-sm ring-1 ring-inset focus:ring-2"
            />

            {open && term.trim().length >= 2 && (
                <div className="border-border-subtle bg-surface-raised absolute z-30 mt-1 w-full rounded-md border shadow-lg">
                    {isFetching && !data && <p className="text-content-muted p-3 text-sm">Searching…</p>}

                    {data && !hasResults && (
                        <p className="text-content-muted p-3 text-sm">
                            Nothing matching “{data.query}” in synchronised data.
                        </p>
                    )}

                    {data?.users.length ? (
                        <Section title="Users">
                            {data.users.map((user) => (
                                <Result key={user.id} to={`${base}/users/${user.id}`}>
                                    <span className="font-medium">{user.display_name}</span>
                                    <span className="text-content-muted">{user.user_principal_name}</span>
                                </Result>
                            ))}
                        </Section>
                    ) : null}

                    {data?.devices.length ? (
                        <Section title="Devices">
                            {data.devices.map((device) => (
                                <Result key={device.id} to={`${base}/devices/${device.id}`}>
                                    <span className="font-medium">{device.device_name}</span>
                                    <span className="text-content-muted">
                                        {device.operating_system} · {device.primary_user.user_principal_name ?? 'No user'}
                                    </span>
                                </Result>
                            ))}
                        </Section>
                    ) : null}

                    {data?.groups.length ? (
                        <Section title="Groups">
                            {data.groups.map((group) => (
                                <Result key={group.id} to={`${base}/groups/${group.id}`}>
                                    <span className="font-medium">{group.display_name}</span>
                                    <span className="text-content-muted">{group.member_count} members</span>
                                </Result>
                            ))}
                        </Section>
                    ) : null}
                </div>
            )}
        </div>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="border-border-subtle/60 border-b last:border-0">
            <p className="text-content-muted/70 px-3 pt-2 text-xs font-semibold tracking-wide uppercase">{title}</p>
            <ul className="py-1">{children}</ul>
        </div>
    );
}

function Result({ to, children }: { to: string; children: React.ReactNode }) {
    return (
        <li>
            <Link to={to} className="hover:bg-brand/10 flex flex-col px-3 py-1.5 text-sm">
                {children}
            </Link>
        </li>
    );
}
