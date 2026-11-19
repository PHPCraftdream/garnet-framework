import * as React from 'react';

interface Props {
    children: React.ReactNode;
}

/** Table shell shared by the grid header and body. */
export function AdminGridTableShell({children}: Props) {
    return (
        <div className="admin-grid-shell">
            <table className="admin-table">{children}</table>
        </div>
    );
}
