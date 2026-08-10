import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';

import { SessionProvider } from '@/hooks/useSession';
import { AppLayout } from '@/layouts/AppLayout';
import { AuditPage } from '@/pages/AuditPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { DeviceDetailPage } from '@/pages/DeviceDetailPage';
import { DevicesPage } from '@/pages/DevicesPage';
import { GroupsPage } from '@/pages/GroupsPage';
import { LoginPage } from '@/pages/LoginPage';
import { TenantsPage } from '@/pages/TenantsPage';
import { UserDetailPage } from '@/pages/UserDetailPage';
import { UsersPage } from '@/pages/UsersPage';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            // Cached Microsoft data changes on the sync schedule, not by the
            // second. Refetching on every window focus would add load without
            // making anything fresher.
            refetchOnWindowFocus: false,
            staleTime: 30_000,
            retry: (failureCount, error) => {
                // Retrying an authorisation failure just repeats it.
                const status = (error as { response?: { status?: number } }).response?.status;

                if (status === 401 || status === 403 || status === 404) {
                    return false;
                }

                return failureCount < 2;
            },
        },
    },
});

function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />

            <Route
                path="/tenants"
                element={
                    <SessionProvider>
                        <TenantsPage />
                    </SessionProvider>
                }
            />

            <Route
                path="/t/:tenantSlug"
                element={
                    <SessionProvider>
                        <AppLayout />
                    </SessionProvider>
                }
            >
                <Route index element={<DashboardPage />} />
                <Route path="users" element={<UsersPage />} />
                <Route path="users/:userId" element={<UserDetailPage />} />
                <Route path="devices" element={<DevicesPage />} />
                <Route path="devices/:deviceId" element={<DeviceDetailPage />} />
                <Route path="groups" element={<GroupsPage />} />
                <Route path="audit" element={<AuditPage />} />
            </Route>

            <Route path="*" element={<Navigate to="/tenants" replace />} />
        </Routes>
    );
}

const container = document.getElementById('app');

if (container) {
    createRoot(container).render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <BrowserRouter>
                    <App />
                </BrowserRouter>
            </QueryClientProvider>
        </StrictMode>,
    );
}
