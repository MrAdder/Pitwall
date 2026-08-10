import clsx from 'clsx';
import type { ReactNode } from 'react';

export type StatusTone = 'ok' | 'warn' | 'error' | 'neutral' | 'unknown';

/**
 * On a dark surface the light-theme badge pattern inverts: rather than a pale
 * tint with dark text, each badge is a low-opacity wash of its own status
 * colour with the full-strength token as the text.
 */
const toneClasses: Record<StatusTone, string> = {
    ok: 'bg-success/12 text-success ring-success/30',
    warn: 'bg-warning/12 text-warning ring-warning/30',
    error: 'bg-danger/12 text-danger ring-danger/30',
    neutral: 'bg-content/8 text-content-muted ring-border-subtle',
    // Visually distinct from every definite state, because "we don't know" is
    // not the same as "it's fine" and must never read as though it were. The
    // dashed ring is the tell that survives being seen in peripheral vision.
    unknown: 'bg-transparent text-content-muted ring-border-subtle border border-dashed border-border-subtle',
};

interface Props {
    tone: StatusTone;
    children: ReactNode;
    title?: string;
}

export function StatusBadge({ tone, children, title }: Props) {
    return (
        <span
            title={title}
            className={clsx(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                toneClasses[tone],
            )}
        >
            {children}
        </span>
    );
}

/**
 * Compliance state to badge tone.
 *
 * `unknown` and `configManager` deliberately do not get a success badge. Intune
 * genuinely does not know the compliance of those devices, and colouring them
 * as healthy would overstate a tenant's posture.
 */
export function complianceTone(state: string | null): StatusTone {
    switch (state) {
        case 'compliant':
            return 'ok';
        case 'noncompliant':
        case 'error':
        case 'conflict':
            return 'error';
        case 'inGracePeriod':
            return 'warn';
        default:
            return 'unknown';
    }
}
