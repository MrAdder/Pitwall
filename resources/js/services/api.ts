import axios, { AxiosError } from 'axios';
import type { ApiError } from '@/types/api';

/**
 * The single HTTP client for the application.
 *
 * Authentication is by session cookie, which is why `withCredentials` is on and
 * why no token is ever stored in JavaScript: there is nothing for a script
 * injection to steal.
 */
export const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiError>) => {
        // The session has gone. Send the browser to sign-in rather than
        // letting every query render its own auth error.
        if (error.response?.status === 401 && !window.location.pathname.startsWith('/login')) {
            window.location.href = '/login';
        }

        return Promise.reject(error);
    },
);

/**
 * Turn an error into something worth showing an administrator.
 *
 * The backend already writes these messages for a human audience — including
 * naming the missing Graph permission — so the job here is to surface them
 * rather than replace them with "Something went wrong".
 */
export function errorMessage(error: unknown): string {
    if (axios.isAxiosError<ApiError>(error)) {
        const message = error.response?.data?.message;

        if (message) {
            return message;
        }

        if (error.response?.status === 403) {
            return 'You do not have permission to do that.';
        }

        if (!error.response) {
            return 'Could not reach the server. Check your connection and try again.';
        }
    }

    return 'Something went wrong. Please try again.';
}

/** The Graph permission an administrator needs to consent to, when the failure was a 403 from Graph. */
export function requiredPermission(error: unknown): string | null {
    if (axios.isAxiosError<ApiError>(error)) {
        return error.response?.data?.error?.required_permission ?? null;
    }

    return null;
}
