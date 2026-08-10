import { useEffect, useRef, useState } from 'react';
import clsx from 'clsx';

export interface ImpactRow {
    label: string;
    value: string;
}

interface Props {
    open: boolean;
    title: string;
    /** What the action does, in plain terms, including anything it will not do. */
    description: string;
    /** Current state, requested state, affected objects — whatever makes the blast radius concrete. */
    impact?: ImpactRow[];
    risk?: 'low' | 'medium' | 'high';
    confirmLabel: string;
    /** For high risk actions: the exact text the administrator must type to proceed. */
    confirmPhrase?: string;
    busy?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

/**
 * Confirmation for an action that changes something in Microsoft 365.
 *
 * Shows what will be affected before asking, rather than a bare "Are you
 * sure?", which trains people to click through without reading. High-risk
 * actions additionally require typing the object's name: the friction is the
 * feature.
 */
export function ConfirmDialog({
    open,
    title,
    description,
    impact = [],
    risk = 'low',
    confirmLabel,
    confirmPhrase,
    busy = false,
    onConfirm,
    onCancel,
}: Props) {
    const [typed, setTyped] = useState('');
    const dialogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (open) {
            setTyped('');
            dialogRef.current?.focus();
        }
    }, [open]);

    if (!open) {
        return null;
    }

    const confirmed = !confirmPhrase || typed.trim() === confirmPhrase;

    return (
        <div className="bg-surface-sunken/80 fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                ref={dialogRef}
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
                onKeyDown={(event) => event.key === 'Escape' && onCancel()}
                className={clsx(
                    'bg-surface-raised w-full max-w-lg rounded-lg border shadow-xl outline-none',
                    // A high-risk dialog is outlined in danger before it is
                    // read, so the colour lands ahead of the words.
                    risk === 'high' ? 'border-danger/50' : 'border-border-subtle',
                )}
            >
                <div className="border-border-subtle border-b px-5 py-4">
                    <h2 id="confirm-title" className="text-content text-base font-semibold">
                        {title}
                    </h2>
                    <p className="text-content-muted mt-1 text-sm">{description}</p>
                </div>

                {impact.length > 0 && (
                    <dl className="divide-border-subtle/60 divide-y px-5 py-3 text-sm">
                        {impact.map((row) => (
                            <div key={row.label} className="flex justify-between gap-4 py-1.5">
                                <dt className="text-content-muted">{row.label}</dt>
                                <dd className="text-content text-right font-medium">{row.value}</dd>
                            </div>
                        ))}
                    </dl>
                )}

                {confirmPhrase && (
                    <div className="px-5 pb-3">
                        <label className="text-content-muted block text-sm">
                            Type <span className="text-content font-mono font-semibold">{confirmPhrase}</span> to
                            confirm
                            <input
                                type="text"
                                value={typed}
                                onChange={(event) => setTyped(event.target.value)}
                                autoComplete="off"
                                className="bg-surface-sunken text-content ring-border-subtle focus:ring-brand mt-1 block w-full rounded-md border-0 px-2 py-1.5 text-sm ring-1 ring-inset focus:ring-2"
                            />
                        </label>
                    </div>
                )}

                <div className="border-border-subtle flex justify-end gap-2 border-t px-5 py-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={busy}
                        className="text-content ring-border-subtle hover:bg-surface rounded-md px-3 py-1.5 text-sm font-medium ring-1 ring-inset disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={!confirmed || busy}
                        className={clsx(
                            // Dark text on a bright fill: at these lightness
                            // values it carries far more contrast than white
                            // would, and the buttons are the one place that
                            // must never be marginal.
                            'text-surface rounded-md px-3 py-1.5 text-sm font-medium disabled:opacity-50',
                            risk === 'high' ? 'bg-danger hover:bg-danger/85' : 'bg-brand hover:bg-brand-strong',
                        )}
                    >
                        {busy ? 'Working…' : confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
