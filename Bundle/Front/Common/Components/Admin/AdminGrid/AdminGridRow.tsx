import * as React from 'react';

export interface AdminGridCellContent {
    key: string;
    narrow?: boolean;
    content: React.ReactNode;
}

interface Props {
    testId: string;
    clickable: boolean;
    onClick?: () => void;
    hasExpander: boolean;
    expanderIcon: React.ReactNode;
    cells: AdminGridCellContent[];
    expandedContent: React.ReactNode;
    totalColumns: number;
}

const renderCell = (cell: AdminGridCellContent) => (
    <td key={cell.key} className={cell.narrow ? 'admin-grid-cell-narrow' : 'admin-grid-cell'}>{cell.content}</td>
);

/** Controlled row pair; expansion and content are owned by the grid. */
export function AdminGridRow({testId, clickable, onClick, hasExpander, expanderIcon, cells, expandedContent, totalColumns}: Props) {
    return (
        <>
            <tr data-test-id={testId} className={clickable ? 'admin-grid-row-action' : 'admin-grid-row'} onClick={onClick}>
                {hasExpander && <td className="admin-grid-expand-cell">{expanderIcon}</td>}
                {cells.map(renderCell)}
            </tr>
            {expandedContent != null && (
                <tr>
                    <td colSpan={totalColumns} className="admin-grid-expanded-cell">{expandedContent}</td>
                </tr>
            )}
        </>
    );
}
