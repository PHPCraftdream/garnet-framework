import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {StaticPage, Labels} from '../types';
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
    private readonly change = (item: MenuItemData) => this.props.onChange(this.props.index, {...item});
    private readonly remove = () => this.props.onDelete(this.props.index);
    private readonly move = (direction: -1 | 1) => this.props.onMove(this.props.index, direction);
    public render() {
        const {item, index, total, labels, pages} = this.props;
        return <MenuItemEditor item={item} index={index} total={total} labels={labels} pages={pages} testId="footer-col-item"
            onChange={this.change} onDelete={this.remove} onMove={this.move} />;
    }
}

class FooterColumn extends AntiHookComponent<ColumnProps> {
    private readonly changeTitle = (event: React.ChangeEvent<HTMLInputElement>) => this.update({...this.props.column, title: event.target.value});
    private readonly remove = () => this.props.onDelete(this.props.index);
    private update(column: Column) { this.props.onChange(this.props.index, column); }
    private readonly changeItem = (index: number, item: MenuItemData) => {
        const column = this.props.column;
        const replacement = {...item};
        this.update({...column, items: column.items.map((current, i) => i === index ? replacement : current)});
    };
    private readonly deleteItem = (index: number) => {
        const column = this.props.column;
        this.update({...column, items: column.items.filter((_, i) => i !== index)});
    };
    private readonly moveItem = (index: number, direction: -1 | 1) => {
        const column = this.props.column;
        const items = [...column.items];
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
        this.update({...column, items});
    };
    private readonly addItem = () => {
        const column = this.props.column;
        this.update({...column, items: [...column.items, {type: 'link', label: '', url: '/'}]});
    };
    private readonly renderItem = (item: MenuItemData, index: number) => {
        const {column, labels, pages} = this.props;
        return <FooterItem key={index} item={{...item}} index={index} total={column.items.length} labels={labels} pages={pages}
            onChange={this.changeItem} onDelete={this.deleteItem} onMove={this.moveItem} />;
    };
    public render() {
        const {column, labels} = this.props;
        return <div className="sp-col-card" data-test-id="footer-column">
            <div className="snippet-footer-column-heading">
                <input className="snippet-footer-column-title" placeholder={labels.snippetsColumnTitle} value={column.title} onChange={this.changeTitle} />
                <button type="button" className="snippet-delete" onClick={this.remove}>{labels.snippetsRemoveColumn}</button>
            </div>
            {column.items.map(this.renderItem)}
            <button type="button" className="static-admin-cancel" data-test-id="footer-col-add-item" onClick={this.addItem}>+ {labels.snippetsAddItem}</button>
        </div>;
    }
}

export class FooterEditor extends AntiHookComponent<FooterProps> {
    private readonly changeColumn = (index: number, column: Column) => {
        const {data, onChange} = this.props;
        const replacement = {...column, items: [...column.items]};
        onChange({...data, columns: (data.columns || []).map((current, i) => i === index ? replacement : current)});
    };
    private readonly deleteColumn = (index: number) => {
        const {data, onChange} = this.props;
        onChange({...data, columns: (data.columns || []).filter((_, i) => i !== index)});
    };
    private readonly addColumn = () => {
        const {data, onChange} = this.props;
        onChange({...data, columns: [...(data.columns || []), {title: '', items: []}]});
    };
    private readonly changeCopyright = (event: React.ChangeEvent<HTMLInputElement>) => this.props.onChange({...this.props.data, copyright: event.target.value});
    private readonly changeLayout = (event: React.ChangeEvent<HTMLSelectElement>) => this.props.onChange({...this.props.data, layout: event.target.value});
    private readonly renderColumn = (column: Column, index: number) => {
        const {labels, pages} = this.props;
        return <FooterColumn key={index} index={index} column={{...column, items: [...column.items]}} labels={labels} pages={pages}
            onChange={this.changeColumn} onDelete={this.deleteColumn} />;
    };
    public render() {
        return <div className="snippet-header-editor">{this.renderColumns()}{this.renderCopyright()}{this.renderSettings()}</div>;
    }
    private renderColumns() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section" data-test-id="footer-columns-section">
            <div className="sp-editor-section-title">{labels.snippetsColumns}</div>
            {(data.columns || []).map(this.renderColumn)}
            <button type="button" className="static-admin-cancel" data-test-id="footer-add-column" onClick={this.addColumn}>+ {labels.snippetsAddColumn}</button>
        </div>;
    }
    private renderCopyright() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section"><label className="static-admin-label">
            <span className="static-admin-caption">{labels.snippetsCopyright}</span>
            <input className="static-admin-input" data-test-id="footer-copyright" placeholder="&copy; {year} {title}" value={data.copyright || ''} onChange={this.changeCopyright} />
            <span className="static-admin-hint">{'{year}'}, {'{title}'}, {'{base-url}'}</span>
        </label></div>;
    }
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
