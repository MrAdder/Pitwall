import type { ReactNode } from 'react';
import clsx from 'clsx';

export interface Column<T> {
    key: string;
    header: string;
    render: (row: T) => ReactNode;
    /** Right-align and use tabular figures. For counts and sizes. */
    numeric?: boolean;
    className?: string;
}

interface Props<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string;
    onRowClick?: (row: T) => void;
    emptyState?: ReactNode;
}

/**
 * The table used for every operational list in the product.
 *
 * Dense on purpose: administrators scan these, and a list that shows eight rows
 * per screen wastes the time the product exists to save.
 */
export function DataTable<T>({ columns, rows, rowKey, onRowClick, emptyState }: Props<T>) {
    if (rows.length === 0 && emptyState) {
        return <>{emptyState}</>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                scope="col"
                                className={clsx(
                                    'border-border-subtle bg-surface-sunken text-content-muted sticky top-0 z-10 border-b px-3 py-2 text-left text-xs font-semibold whitespace-nowrap',
                                    column.numeric && 'text-right',
                                )}
                            >
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={rowKey(row)}
                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                            className={clsx(
                                // Zebra striping is a lift towards the raised
                                // surface rather than a wash of grey: on a dark
                                // ground, tinting downwards reads as a gap.
                                'even:bg-surface-raised/40',
                                onRowClick && 'hover:bg-brand/10 cursor-pointer',
                            )}
                        >
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={clsx(
                                        'border-border-subtle/50 border-b px-3 py-2 align-middle',
                                        column.numeric && 'tabular text-right',
                                        column.className,
                                    )}
                                >
                                    {column.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
