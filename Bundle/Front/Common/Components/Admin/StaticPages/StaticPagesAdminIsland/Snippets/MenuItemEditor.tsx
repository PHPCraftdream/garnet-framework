import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {StaticPage, Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';
import {ITEM_TYPES, ITEM_TYPE_LABELS} from './menuItemTypes';

export interface MenuItemData {
    type: string;
    label?: string;
    url?: string;
    slug?: string;
    external?: boolean;
    visibility?: string;
}

interface MenuItemEditorProps {
    item: MenuItemData;
    index: number;
    total: number;
    onChange: (item: MenuItemData) => void;
    onDelete: () => void;
    onMove: (dir: -1 | 1) => void;
    pages: StaticPage[];
    labels: Labels;
    testId?: string;
}

const isPublished = (page: StaticPage) => page.is_published;
const renderPage = (page: StaticPage) => (
    <option key={page.id} value={page.slug}>
        {page.title_rendered || page.title || page.slug}
    </option>
);

export class MenuItemEditor extends AntiHookComponent<MenuItemEditorProps> {
    /** Updates the item fields appropriate to its selected type. */
    private readonly changeType = (event: React.ChangeEvent<HTMLSelectElement>) => {
        const {item, onChange} = this.props;
        const type = event.target.value;
        const visibility = item.visibility;
        if (type === 'divider') onChange({type: 'divider'});
        else if (type === 'page') onChange({type: 'page', slug: '', label: '', visibility});
        else onChange({type: 'link', label: item.label ?? '', url: item.url ?? '/', external: false, visibility});
    };
    /** Updates the menu item's displayed label. */
    private readonly changeLabel = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.props.onChange({...this.props.item, label: event.target.value});
    /** Updates the menu item's link URL. */
    private readonly changeUrl = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.props.onChange({...this.props.item, url: event.target.value});
    /** Updates whether the link opens externally. */
    private readonly changeExternal = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.props.onChange({...this.props.item, external: event.target.checked});
    /** Updates the selected page slug. */
    private readonly changeSlug = (event: React.ChangeEvent<HTMLSelectElement>) =>
        this.props.onChange({...this.props.item, slug: event.target.value});
    /** Updates the menu item's visibility setting. */
    private readonly changeVisibility = (event: React.ChangeEvent<HTMLSelectElement>) =>
        this.props.onChange({...this.props.item, visibility: event.target.value});
    /** Requests moving the item one position up. */
    private readonly moveUp = () => this.props.onMove(-1);
    /** Requests moving the item one position down. */
    private readonly moveDown = () => this.props.onMove(1);
    /** Renders an option for a menu item type. */
    private readonly renderType = (type: string) => (
        <option key={type} value={type}>
            {(ITEM_TYPE_LABELS[type] ?? (() => type))(this.props.labels)}
        </option>
    );

    /** Renders the menu item controls. */
    public render() {
        const {item, index, total, labels, testId = 'header-menu-item', onDelete} = this.props;
        return <div className="sp-menu-item-row" data-test-id={testId}>
            <select className="snippet-menu-type" value={item.type} onChange={this.changeType}>
                {ITEM_TYPES.map(this.renderType)}
            </select>
            {item.type !== 'divider' && <input className="snippet-menu-label"
                placeholder={labels.snippetsItemLabel} value={item.label ?? ''} onChange={this.changeLabel} />}
            {this.renderTarget()}
            {item.type !== 'divider' && this.renderVisibility()}
            <button type="button" className="blk-icon-btn" data-test-id="move-up" onClick={this.moveUp}
                disabled={index === 0} title={labels.moveUp}>&#8593;</button>
            <button type="button" className="blk-icon-btn" data-test-id="move-down" onClick={this.moveDown}
                disabled={index === total - 1} title={labels.moveDown}>&#8595;</button>
            <button type="button" className="blk-icon-btn-danger" data-test-id="delete-item" onClick={onDelete}
                title={labels.actionDelete}>&#215;</button>
        </div>;
    }

    /** Renders the URL, page, or spacer control for the current item type. */
    private renderTarget() {
        const {item, labels, pages} = this.props;
        if (item.type === 'link') return <>
            <input className="snippet-menu-url" placeholder={labels.snippetsItemUrl}
                value={item.url ?? ''} onChange={this.changeUrl} />
            <label className="snippet-menu-external"><input type="checkbox" checked={!!item.external}
                onChange={this.changeExternal} />{labels.snippetsItemExternal}</label>
        </>;
        if (item.type === 'page') return <select className="snippet-menu-page" value={item.slug ?? ''}
            onChange={this.changeSlug}>
            <option value="">{labels.snippetsSelectPage}</option>
            {pages.filter(isPublished).map(renderPage)}
        </select>;
        if (item.type === 'divider') return <div className="snippet-menu-spacer" />;
        return null;
    }

    /** Renders the visibility selector for the current menu item. */
    private renderVisibility() {
        const {item, labels} = this.props;
        return <select className="snippet-menu-visibility" value={item.visibility || 'all'}
            onChange={this.changeVisibility} data-test-id="menu-item-visibility" title={labels.visibility}>
            <option value="all">{labels.visibilityAll}</option>
            <option value="guest">{labels.visibilityGuest}</option>
            <option value="auth">{labels.visibilityAuth}</option>
            <option value="moderator">{labels.visibilityModerator}</option>
        </select>;
    }
}
