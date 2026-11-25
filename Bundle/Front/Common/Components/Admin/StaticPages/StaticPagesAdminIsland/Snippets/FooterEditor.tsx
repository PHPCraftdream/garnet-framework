import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {StaticPage, Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';
import {MenuItemData, MenuItemEditor} from './MenuItemEditor';

export interface FooterData {
    columns: {title: string; items: MenuItemData[]}[];
    copyright: string;
    layout: string;
}

type Column = FooterData['columns'][number];
interface FooterProps {
    data: FooterData;
    onChange: (data: FooterData) => void;
    labels: Labels;
    pages: StaticPage[];
}
interface ColumnProps {
    column: Column;
    index: number;
    labels: Labels;
    pages: StaticPage[];
    onChange: (index: number, column: Column) => void;
    onDelete: (index: number) => void;
}
interface ItemProps {
    item: MenuItemData;
    index: number;
    total: number;
    labels: Labels;
    pages: StaticPage[];
    onChange: (index: number, item: MenuItemData) => void;
    onDelete: (index: number) => void;
    onMove: (index: number, direction: -1 | 1) => void;
}

class FooterItem extends AntiHookComponent<ItemProps> {
    /** Propagates an edited menu item with its current index. */
    private readonly change = (item: MenuItemData) => this.props.onChange(this.props.index, {...item});
    /** Removes this menu item. */
    private readonly remove = () => this.props.onDelete(this.props.index);
    /** Requests moving this menu item in the given direction. */
    private readonly move = (direction: -1 | 1) => this.props.onMove(this.props.index, direction);
    /** Renders the menu item editor. */
    public render() {
        const {item, index, total, labels, pages} = this.props;
        return <MenuItemEditor item={item} index={index} total={total} labels={labels} pages={pages}
            testId="footer-col-item" onChange={this.change} onDelete={this.remove} onMove={this.move} />;
    }
}

class FooterColumn extends AntiHookComponent<ColumnProps> {
    /** Updates this column's title from its input. */
    private readonly changeTitle = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.update({...this.props.column, title: event.target.value});
    /** Removes this column. */
    private readonly remove = () => this.props.onDelete(this.props.index);
    /** Sends an updated column to the parent. */
    private update(column: Column) { this.props.onChange(this.props.index, column); }
    /** Replaces one item in the column. */
    private readonly changeItem = (index: number, item: MenuItemData) => {
        const column = this.props.column;
        const replacement = {...item};
        this.update({...column, items: column.items.map((current, i) => i === index ? replacement : current)});
    };
    /** Removes an item from the column. */
    private readonly deleteItem = (index: number) => {
        const column = this.props.column;
        this.update({...column, items: column.items.filter((_, i) => i !== index)});
    };
    /** Moves an item one position when the destination is in range. */
    private readonly moveItem = (index: number, direction: -1 | 1) => {
        const column = this.props.column;
        const items = [...column.items];
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
        this.update({...column, items});
    };
    /** Appends a blank link item to the column. */
    private readonly addItem = () => {
        const column = this.props.column;
        this.update({...column, items: [...column.items, {type: 'link', label: '', url: '/'}]});
    };
    /** Renders one menu item editor. */
    private readonly renderItem = (item: MenuItemData, index: number) => {
        const {column, labels, pages} = this.props;
        return <FooterItem key={index} item={{...item}} index={index} total={column.items.length}
            labels={labels} pages={pages} onChange={this.changeItem} onDelete={this.deleteItem}
            onMove={this.moveItem} />;
    };
    /** Renders the column card and its item controls. */
    public render() {
        const {column, labels} = this.props;
        return <div className="sp-col-card" data-test-id="footer-column">
            <div className="snippet-footer-column-heading">
                <input className="snippet-footer-column-title" placeholder={labels.snippetsColumnTitle}
                    value={column.title} onChange={this.changeTitle} />
                <button type="button" className="snippet-delete"
                    onClick={this.remove}>{labels.snippetsRemoveColumn}</button>
            </div>
            {column.items.map(this.renderItem)}
            <button type="button" className="static-admin-cancel" data-test-id="footer-col-add-item"
                onClick={this.addItem}>+ {labels.snippetsAddItem}</button>
        </div>;
    }
}

export class FooterEditor extends AntiHookComponent<FooterProps> {
    /** Replaces a column while preserving the other footer data. */
    private readonly changeColumn = (index: number, column: Column) => {
        const {data, onChange} = this.props;
        const replacement = {...column, items: [...column.items]};
        onChange({...data, columns: (data.columns || []).map((current, i) => i === index ? replacement : current)});
    };
    /** Removes a footer column by index. */
    private readonly deleteColumn = (index: number) => {
        const {data, onChange} = this.props;
        onChange({...data, columns: (data.columns || []).filter((_, i) => i !== index)});
    };
    /** Appends an empty footer column. */
    private readonly addColumn = () => {
        const {data, onChange} = this.props;
        onChange({...data, columns: [...(data.columns || []), {title: '', items: []}]});
    };
    /** Updates the copyright text. */
    private readonly changeCopyright = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.props.onChange({...this.props.data, copyright: event.target.value});
    /** Updates the selected footer layout. */
    private readonly changeLayout = (event: React.ChangeEvent<HTMLSelectElement>) =>
        this.props.onChange({...this.props.data, layout: event.target.value});
    /** Renders one footer column editor. */
    private readonly renderColumn = (column: Column, index: number) => {
        const {labels, pages} = this.props;
        return <FooterColumn key={index} index={index} column={{...column, items: [...column.items]}}
            labels={labels} pages={pages} onChange={this.changeColumn} onDelete={this.deleteColumn} />;
    };
    /** Renders the footer editor sections. */
    public render() {
        return <div className="snippet-header-editor">
            {this.renderColumns()}{this.renderCopyright()}{this.renderSettings()}
        </div>;
    }
    /** Renders footer columns and the add-column control. */
    private renderColumns() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section" data-test-id="footer-columns-section">
            <div className="sp-editor-section-title">{labels.snippetsColumns}</div>
            {(data.columns || []).map(this.renderColumn)}
            <button type="button" className="static-admin-cancel" data-test-id="footer-add-column"
                onClick={this.addColumn}>+ {labels.snippetsAddColumn}</button>
        </div>;
    }
    /** Renders the copyright text field and supported placeholders. */
    private renderCopyright() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section"><label className="static-admin-label">
            <span className="static-admin-caption">{labels.snippetsCopyright}</span>
            <input className="static-admin-input" data-test-id="footer-copyright"
                placeholder="&copy; {year} {title}" value={data.copyright || ''}
                onChange={this.changeCopyright} />
            <span className="static-admin-hint">{'{year}'}, {'{title}'}, {'{base-url}'}</span>
        </label></div>;
    }
    /** Renders the footer layout selector. */
    private renderSettings() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section"><label className="static-admin-label">
            <span className="static-admin-caption">{labels.snippetsLayout}</span>
            <select className="snippet-footer-layout" value={data.layout || 'columns'} onChange={this.changeLayout}>
                <option value="columns">{labels.snippetsLayoutColumns}</option>
                <option value="simple">{labels.snippetsLayoutSimple}</option>
            </select>
        </label></div>;
    }
}
