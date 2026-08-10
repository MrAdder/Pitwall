import type { ReactNode } from 'react';

interface Props {
    title: string;
    description?: string;
    /** Freshness of the data on the page. Cached Microsoft data is never shown without it. */
    syncedAt?: string | null;
    actions?: ReactNode;
}

export function PageHeader({ title, description, syncedAt, actions }: Props) {
    return (
        <div className="border-border-subtle flex flex-wrap items-start justify-between gap-4 border-b px-6 py-4">
            <div>
                <h1 className="text-content text-lg font-semibold">{title}</h1>
                {description && <p className="text-content-muted mt-0.5 text-sm">{description}</p>}
                {syncedAt !== undefined && (
                    <p className="text-content-muted/70 mt-1 text-xs">Last synchronised {relativeTime(syncedAt)}</p>
                )}
            </div>

            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}

/**
 * Relative time, e.g. "2 minutes ago".
 *
 * A never-synchronised resource says so explicitly rather than showing an
 * empty space that could be mistaken for "just now".
 */
export function relativeTime(iso: string | null | undefined): string {
    if (!iso) {
        return 'never';
    }

    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return 'never';
    }

    const seconds = Math.round((then - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
    ];

    for (const [unit, secondsPerUnit] of units) {
        if (Math.abs(seconds) >= secondsPerUnit) {
            return formatter.format(Math.round(seconds / secondsPerUnit), unit);
        }
    }

    return formatter.format(seconds, 'second');
}
