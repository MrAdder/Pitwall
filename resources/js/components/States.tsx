import type { ReactNode } from 'react';
import { errorMessage, requiredPermission } from '@/services/api';

/**
 * The three states every data view needs besides its happy path. Centralised so
 * loading, empty and error look and read the same everywhere.
 */

export function LoadingState({ label = 'Loading' }: { label?: string }) {
    return (
        <div className="text-content-muted flex items-center justify-center gap-3 p-12 text-sm">
            <span
                className="border-border-subtle border-t-brand size-4 animate-spin rounded-full border-2"
                aria-hidden
            />
            <span role="status">{label}…</span>
        </div>
    );
}

interface EmptyStateProps {
    title: string;
    description?: string;
    action?: ReactNode;
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center gap-2 p-12 text-center">
            <p className="text-content text-sm font-medium">{title}</p>
            {description && <p className="text-content-muted max-w-md text-sm">{description}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}

/**
 * Error display.
 *
 * Shows the server's own message, which for Graph failures explains what went
 * wrong and what to do — including naming a missing consent. A generic
 * "something went wrong" would throw away the most useful part of the response.
 */
export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
    const permission = requiredPermission(error);

    return (
        <div className="border-danger/40 bg-danger/10 m-6 rounded-md border p-4">
            <p className="text-danger text-sm font-medium">{errorMessage(error)}</p>

            {permission && (
                <p className="text-content mt-2 text-sm">
                    Required Microsoft Graph permission:{' '}
                    <code className="bg-surface-sunken rounded px-1 py-0.5 font-mono">{permission}</code>
                </p>
            )}

            {onRetry && (
                <button
                    type="button"
                    onClick={onRetry}
                    className="text-content ring-border-subtle hover:bg-surface-raised mt-3 rounded-md px-2.5 py-1.5 text-sm font-medium ring-1 ring-inset"
                >
                    Try again
                </button>
            )}
        </div>
    );
}
