import * as React from 'react';

interface Props {
    colSpan: number;
    children: React.ReactNode;
}

/** Controlled empty-state row. */
export function AdminGridEmptyRow({colSpan, children}: Props) {
    return <tr><td colSpan={colSpan} className="admin-grid-empty-cell">{children}</td></tr>;
}
