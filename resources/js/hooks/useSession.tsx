import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { api } from '@/services/api';
import type { CurrentUser, Envelope, Permission, Tenant } from '@/types/api';

interface SessionValue {
    user: CurrentUser | null;
    tenant: Tenant | null;
    tenants: Tenant[];
    isLoading: boolean;
    /**
     * Whether the signed-in administrator holds a permission on the current
     * tenant. Used to hide actions rather than to enforce anything: the server
     * checks every request regardless, and this only keeps the UI from
     * offering buttons that would be refused.
     */
    can: (permission: Permission) => boolean;
}

const SessionContext = createContext<SessionValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
    const { tenantSlug } = useParams();

    const { data, isLoading } = useQuery({
        queryKey: ['me'],
        queryFn: async () => (await api.get<Envelope<CurrentUser>>('/me')).data.data,
        retry: false,
        staleTime: 60_000,
    });

    const value = useMemo<SessionValue>(() => {
        const tenants = data?.tenants ?? [];
        const tenant = tenants.find((t) => t.slug === tenantSlug) ?? tenants[0] ?? null;
        const granted = new Set(tenant?.permissions ?? []);

        return {
            user: data ?? null,
            tenant,
            tenants,
            isLoading,
            can: (permission) => granted.has(permission),
        };
    }, [data, isLoading, tenantSlug]);

    return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionValue {
    const context = useContext(SessionContext);

    if (context === null) {
        throw new Error('useSession must be used inside a SessionProvider.');
    }

    return context;
}

/**
 * The current tenant, for code paths that cannot run without one. Routes are
 * arranged so this is only reachable once a tenant is resolved.
 */
export function useTenant(): Tenant {
    const { tenant } = useSession();

    if (tenant === null) {
        throw new Error('No tenant is selected.');
    }

    return tenant;
}
