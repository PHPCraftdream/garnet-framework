import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {ImageUploadArea} from '@common/Components/Controls/ImageUploadArea';
import {StaticPage, Labels} from '../types';
import {MenuItemData, MenuItemEditor} from './MenuItemEditor';

export interface HeaderData {
    logo?: {url: string; alt: string; link: string; height: number};
    items: MenuItemData[];
    layout: string;
    sticky: boolean;
}

interface HeaderProps {
    data: HeaderData;
    onChange: (data: HeaderData) => void;
    labels: Labels;
    pages: StaticPage[];
    uploadImageUrl: string;
    deleteImageUrl: string;
}

interface HeaderRowProps {
    item: MenuItemData;
    index: number;
    total: number;
    pages: StaticPage[];
    labels: Labels;
    onChange: (index: number, item: MenuItemData) => void;
    onDelete: (index: number) => void;
    onMove: (index: number, direction: -1 | 1) => void;
}

class HeaderRow extends AntiHookComponent<HeaderRowProps> {
    private readonly change = (item: MenuItemData) => this.props.onChange(this.props.index, {...item});
    private readonly remove = () => this.props.onDelete(this.props.index);
    private readonly move = (direction: -1 | 1) => this.props.onMove(this.props.index, direction);

    public render() {
        const {item, index, total, pages, labels} = this.props;
        return <MenuItemEditor item={item} index={index} total={total} pages={pages} labels={labels}
            onChange={this.change} onDelete={this.remove} onMove={this.move} />;
    }
}

const defaultLogo = {url: '', alt: '', link: '/', height: 40};

export class HeaderEditor extends AntiHookComponent<HeaderProps> {
    private readonly ui = new Carburetor<{logoUploading: boolean}>({logoUploading: false});
    private readonly view = this.connectSelection(() => this.ui, state => state);

    private updateLogo(field: string, value: string | number) {
        const {data, onChange} = this.props;
        onChange({...data, logo: {...(data.logo || defaultLogo), [field]: value}});
    }

    private readonly changeAlt = (event: React.ChangeEvent<HTMLInputElement>) => this.updateLogo('alt', event.target.value);
    private readonly changeLink = (event: React.ChangeEvent<HTMLInputElement>) => this.updateLogo('link', event.target.value);
    private readonly changeHeight = (event: React.ChangeEvent<HTMLInputElement>) => this.updateLogo('height', Math.max(10, Math.min(200, parseInt(event.target.value, 10) || 40)));
    private readonly changeLayout = (event: React.ChangeEvent<HTMLSelectElement>) => this.props.onChange({...this.props.data, layout: event.target.value});
    private readonly changeSticky = (event: React.ChangeEvent<HTMLInputElement>) => this.props.onChange({...this.props.data, sticky: event.target.checked});

    private readonly uploadLogo = (files: File[]) => { void this.upload(files); };
    private async upload(files: File[]) {
        const file = files[0];
        if (!file) return;
        this.ui.setData({logoUploading: true});
        try {
            const form = new FormData();
            form.append('file', file);
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(this.props.uploadImageUrl, form);
            if (res?.url) this.updateLogo('url', res.url);
        } catch { /* silent */ }
        finally { this.ui.setData({logoUploading: false}); }
    }

    private readonly removeLogo = () => { void this.remove(); };
    private async remove() {
        const url = this.props.data.logo?.url;
        if (url) {
            try { await sendPost(this.props.deleteImageUrl, {url}); } catch { /* silent */ }
        }
        if (this.props.data.logo?.url === url) this.updateLogo('url', '');
    }

    private readonly updateItem = (index: number, item: MenuItemData) => {
        const {data, onChange} = this.props;
        const items = [...(data.items || [])];
        items[index] = {...item};
        onChange({...data, items});
    };
    private readonly deleteItem = (index: number) => {
        const {data, onChange} = this.props;
        onChange({...data, items: (data.items || []).filter((_, i) => i !== index)});
    };
    private readonly moveItem = (index: number, direction: -1 | 1) => {
        const {data, onChange} = this.props;
        const items = [...(data.items || [])];
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
        onChange({...data, items});
    };
    private readonly addItem = () => {
        const {data, onChange} = this.props;
        onChange({...data, items: [...(data.items || []), {type: 'link', label: '', url: '/'}]});
    };
    private readonly renderItem = (item: MenuItemData, index: number) => {
        const {data, pages, labels} = this.props;
        return <HeaderRow key={index} item={{...item}} index={index} total={(data.items || []).length} pages={pages} labels={labels}
            onChange={this.updateItem} onDelete={this.deleteItem} onMove={this.moveItem} />;
    };

    public render() {
        const {logoUploading} = this.view() as {logoUploading: boolean};
        return <div className="snippet-header-editor">
            {this.renderLogo(logoUploading)}
            {this.renderMenu()}
            {this.renderSettings()}
        </div>;
    }

    private renderLogo(logoUploading: boolean) {
        const {data, labels} = this.props;
        const logo = data.logo || defaultLogo;
        return <div className="sp-editor-section" data-test-id="header-logo-section">
                <div className="sp-editor-section-title">{labels.snippetsLogo}</div>
                {logo.url ? <div className="snippet-header-logo-fields">
                    <div className="blk-img-preview">
                        <img src={logo.url} alt={logo.alt || ''} className="blk-img-preview-img" />
                        <button type="button" className="blk-img-preview-remove" onClick={this.removeLogo} title={labels.removeImage}>&#215;</button>
                    </div>
                    <div className="static-admin-snippets-fields">
                        <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsLogoAlt}</span>
                            <input className="static-admin-input" data-test-id="header-logo-alt" value={logo.alt} onChange={this.changeAlt} />
                        </label>
                        <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsLogoLink}</span>
                            <input className="static-admin-input" data-test-id="header-logo-link" value={logo.link} onChange={this.changeLink} />
                        </label>
                        <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsLogoHeight}</span>
                            <input type="number" className="static-admin-input" data-test-id="header-logo-height" min={10} max={200} value={logo.height} onChange={this.changeHeight} />
                        </label>
                    </div>
                </div> : <ImageUploadArea onUpload={this.uploadLogo} uploading={logoUploading} label={labels.uploadImage} />}
            </div>;
    }

    private renderMenu() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section" data-test-id="header-menu-section">
                <div className="sp-editor-section-title">{labels.snippetsMenuItems}</div>
                {(data.items || []).map(this.renderItem)}
                <button type="button" className="static-admin-cancel" data-test-id="header-add-item" onClick={this.addItem}>+ {labels.snippetsAddItem}</button>
            </div>;
    }

    private renderSettings() {
        const {data, labels} = this.props;
        return <div className="sp-editor-section">
                <div className="static-admin-fields">
                    <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsLayout}</span>
                        <select className="static-admin-input" data-test-id="header-layout" value={data.layout || 'left'} onChange={this.changeLayout}>
                            <option value="left">{labels.snippetsLayoutLeft}</option>
                            <option value="center">{labels.snippetsLayoutCenter}</option>
                            <option value="minimal">{labels.snippetsLayoutMinimal}</option>
                        </select>
                    </label>
                    <label className="snippet-active-label">
                        <input type="checkbox" data-test-id="header-sticky" checked={!!data.sticky} onChange={this.changeSticky} />
                        <span className="snippet-active-caption">{labels.snippetsSticky}</span>
                    </label>
                </div>
            </div>;
    }
}
