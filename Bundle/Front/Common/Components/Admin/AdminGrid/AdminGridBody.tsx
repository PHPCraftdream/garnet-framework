import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {ChevronDown, ChevronRight} from 'lucide-react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import type {GridColumnConfig} from './types';
import {AdminGridEmptyRow} from './AdminGridEmptyRow';
import {AdminGridRow} from './AdminGridRow';

type RowKey = string | number;

interface BodyProps<T> {
    rows: T[];
    columns: GridColumnConfig[];
    rowKey: (row: T) => RowKey;
    renderCell: (row: T, key: string) => React.ReactNode;
    emptyMessage?: React.ReactNode;
    expandRenderer?: (row: T, isExpanded: boolean) => React.ReactNode;
    expandable?: (row: T) => boolean;
    onRowClick?: (row: T) => void;
    rowTestId?: (row: T) => string;
}

interface BodyRowProps<T> extends Omit<React.ComponentProps<typeof AdminGridRow>, 'onClick'> {
    row: T;
    rowKey: RowKey;
    canExpand: boolean;
    onToggle: (key: RowKey) => void;
    onRowClick?: (row: T) => void;
}

class AdminGridBodyRow<T> extends React.Component<BodyRowProps<T>> {
    /** Handles handleClick. */
    private readonly handleClick = (): void => {
        const {canExpand, rowKey, row, onToggle, onRowClick} = this.props;
        if (canExpand) onToggle(rowKey);
        else onRowClick?.(row);
    };

    /** Handles render. */
    public render() {
        const {testId, clickable, hasExpander, expanderIcon, cells, expandedContent, totalColumns} = this.props;
        return <AdminGridRow testId={testId} clickable={clickable} onClick={clickable ? this.handleClick :
            undefined} hasExpander={hasExpander} expanderIcon={expanderIcon} cells={cells}
            expandedContent={expandedContent} totalColumns={totalColumns} />;
    }
}

export class AdminGridBody<T> extends AntiHookComponent<BodyProps<T>> {
    private readonly expansion = new Carburetor<{keys: RowKey[]}>({keys: []});

    // Rows and renderer inputs can change in place.
    private readonly updateOnParentRender = true;

    /** Handles shouldComponentUpdate. */
    public shouldComponentUpdate(nextProps: Readonly<BodyProps<T>>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    /** Handles toggleExpand. */
    private readonly toggleExpand = (key: RowKey): void => {
        const keys = this.expansion.getData().keys;
        this.expansion.setData({keys: keys.includes(key) ? keys.filter(item => !Object.is(item, key) && item
            !== key) : [...keys, key]});
    };

    /** Handles renderRow. */
    private renderRow(row: T, keys: readonly RowKey[]): React.ReactNode {
        const {rowKey, expandable, expandRenderer, rowTestId, columns, renderCell, onRowClick} = this.props;
        const key = rowKey(row);
        const isExpanded = keys.includes(key);
        const canExpand = expandRenderer ? (expandable ? expandable(row) : true) : false;
        const expandContent = (expandRenderer && isExpanded) ? expandRenderer(row, true) : null;
        const testId = rowTestId ? rowTestId(row) : `grid-row-${key}`;
        const expanderIcon = canExpand
            ? (isExpanded
                ? <ChevronDown className="admin-grid-expander-active" size="1em" aria-hidden="true" />
                : <ChevronRight className="admin-grid-expander-idle" size="1em" aria-hidden="true" />)
            : null;
        const cells: React.ComponentProps<typeof AdminGridRow>['cells'] = [];
        for (const col of columns) {
            cells.push({key: col.key, narrow: col.shrink, content: renderCell(row, col.key)});
        }
        return (
            <AdminGridBodyRow key={key} row={row} rowKey={key} canExpand={canExpand}
                onToggle={this.toggleExpand} onRowClick={onRowClick}
                testId={testId} clickable={!!(canExpand || onRowClick)} hasExpander={!!expandRenderer}
                    expanderIcon={expanderIcon}
                cells={cells} expandedContent={expandContent} totalColumns={columns.length + (expandRenderer ?
                    1 : 0)} />
        );
    }

    /** Handles render. */
    public render() {
        const {keys} = this.useCarburetor(this.expansion);
        const {rows, columns, expandRenderer, emptyMessage} = this.props;
        const totalColumns = columns.length + (expandRenderer ? 1 : 0);
        const renderedRows: React.ReactNode[] = [];
        for (const row of rows) renderedRows.push(this.renderRow(row, keys));
        return (
            <tbody>
                {rows.length === 0
                    ? <AdminGridEmptyRow colSpan={totalColumns}>{emptyMessage ?? t.Grid_NoData()}</AdminGridEmptyRow>
                    : renderedRows}
            </tbody>
        );
    }
}
