import * as React from 'react';
import type {GridColumnConfig} from './types';

interface Props {
    columns: GridColumnConfig[];
    sortFields: string[];
    sortField: string | null;
    sortDir: 'asc' | 'desc';
    hasExpander: boolean;
    onSort: (key: string) => void;
}

interface HeaderCellProps {
    column: GridColumnConfig;
    sortable: boolean;
    active: boolean;
    sortDir: 'asc' | 'desc';
    onSort: (key: string) => void;
}

class AdminGridHeaderCell extends React.Component<HeaderCellProps> {
    /** Handles handleSort. */
    private readonly handleSort = (): void => { this.props.onSort(this.props.column.key); };

    /** Handles render. */
    public render() {
        const {column, sortable, active, sortDir} = this.props;
        return (
            <th
                data-test-id={sortable ? `sort-col-${column.key}` : undefined}
                className={sortable
                    ? (column.shrink ? 'admin-grid-heading-sortable-narrow' : 'admin-grid-heading-sortable')
                    : (column.shrink ? 'admin-grid-heading-narrow' : 'admin-grid-heading')}
                onClick={sortable ? this.handleSort : undefined}
            >
                {column.label}
                {sortable && <span className={active ? 'admin-grid-sort-active' :
                    'admin-grid-sort-idle'}>{active ? (sortDir === 'asc' ? '▲' : '▼') : '⇅'}</span>}
            </th>
        );
    }
}

/** Controlled header; the grid owns sorting and all server requests. */
export class AdminGridHeader extends React.Component<Props> {
    /** Handles renderColumn. */
    private readonly renderColumn = (column: GridColumnConfig): React.ReactNode => {
        const {sortFields, sortField, sortDir, onSort} = this.props;
        return <AdminGridHeaderCell key={column.key} column={column}
            sortable={sortFields.includes(column.key)} active={sortField === column.key} sortDir={sortDir}
            onSort={onSort} />;
    };

    /** Handles render. */
    public render() {
        return (
            <thead>
                <tr>
                    {this.props.hasExpander && <th className="admin-grid-expander-heading" />}
                    {this.props.columns.map(this.renderColumn)}
                </tr>
            </thead>
        );
    }
}
