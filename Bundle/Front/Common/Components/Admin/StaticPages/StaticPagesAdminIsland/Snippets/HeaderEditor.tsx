import * as React from 'react';
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

export const HeaderEditor: React.FC<{
    data: HeaderData;
    onChange: (data: HeaderData) => void;
    labels: Labels;
    pages: StaticPage[];
    uploadImageUrl: string;
    deleteImageUrl: string;
}> = ({data, onChange, labels, pages, uploadImageUrl, deleteImageUrl}) => {
    const logo = data.logo || {url: '', alt: '', link: '/', height: 40};
    const items = data.items || [];
    const layout = data.layout || 'left';
    const sticky = data.sticky || false;
    const [logoUploading, setLogoUploading] = React.useState(false);

    const updateLogo = (field: string, value: string | number) => {
        onChange({...data, logo: {...logo, [field]: value}});
    };

    const handleLogoUpload = async (files: File[]) => {
        const file = files[0];
        if (!file) return;
        setLogoUploading(true);
        try {
            const fd = new FormData();
            fd.append('file', file);
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
            if (res?.url) {
                onChange({...data, logo: {...logo, url: res.url}});
            }
        } catch { /* silent */ }
        finally { setLogoUploading(false); }
    };

    const handleLogoRemove = async () => {
        if (logo.url) {
            try { await sendPost(deleteImageUrl, {url: logo.url}); } catch { /* silent */ }
        }
        onChange({...data, logo: {...logo, url: ''}});
    };

    const updateItem = (idx: number, item: MenuItemData) => {
        const newItems = [...items];
        newItems[idx] = item;
        onChange({...data, items: newItems});
    };

    const deleteItem = (idx: number) => {
        onChange({...data, items: items.filter((_, i) => i !== idx)});
    };

    const moveItem = (idx: number, dir: -1 | 1) => {
        const target = idx + dir;
        if (target < 0 || target >= items.length) return;
        const newItems = [...items];
        [newItems[idx], newItems[target]] = [newItems[target], newItems[idx]];
        onChange({...data, items: newItems});
    };

    const addItem = () => {
        onChange({...data, items: [...items, {type: 'link', label: '', url: '/'}]});
    };

    return (
        <div className="space-y-4">
            {/* Logo section */}
            <div className="sp-editor-section" data-test-id="header-logo-section">
                <div className="sp-editor-section-title">{labels.snippetsLogo}</div>
                {logo.url ? (
                    <div className="space-y-2">
                        <div className="blk-img-preview">
                            <img src={logo.url} alt={logo.alt || ''} className="blk-img-preview-img" />
                            <button type="button" className="blk-img-preview-remove" onClick={() => void handleLogoRemove()} title={labels.removeImage}>&#215;</button>
                        </div>
                        <div className="grid gap-3 md:grid-cols-3">
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLogoAlt}</span>
                                <input className="form-control form-control-sm w-full" data-test-id="header-logo-alt" value={logo.alt} onChange={e => updateLogo('alt', e.target.value)} />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLogoLink}</span>
                                <input className="form-control form-control-sm w-full" data-test-id="header-logo-link" value={logo.link} onChange={e => updateLogo('link', e.target.value)} />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLogoHeight}</span>
                                <input type="number" className="form-control form-control-sm w-20" data-test-id="header-logo-height" min={10} max={200} value={logo.height} onChange={e => updateLogo('height', Math.max(10, Math.min(200, parseInt(e.target.value, 10) || 40)))} />
                            </label>
                        </div>
                    </div>
                ) : (
                    <ImageUploadArea onUpload={files => void handleLogoUpload(files)} uploading={logoUploading} label={labels.uploadImage} />
                )}
            </div>

            {/* Menu items */}
            <div className="sp-editor-section" data-test-id="header-menu-section">
                <div className="sp-editor-section-title">{labels.snippetsMenuItems}</div>
                {items.map((item, idx) => (
                    <MenuItemEditor
                        key={idx}
                        item={item}
                        index={idx}
                        total={items.length}
                        onChange={updated => updateItem(idx, updated)}
                        onDelete={() => deleteItem(idx)}
                        onMove={dir => moveItem(idx, dir)}
                        pages={pages}
                        labels={labels}
                    />
                ))}
                <button type="button" className="btn btn-sm btn-secondary" data-test-id="header-add-item" onClick={addItem}>+ {labels.snippetsAddItem}</button>
            </div>

            {/* Settings */}
            <div className="sp-editor-section">
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLayout}</span>
                        <select
                            className="form-select form-select-sm w-full"
                            data-test-id="header-layout"
                            value={layout}
                            onChange={e => onChange({...data, layout: e.target.value})}
                        >
                            <option value="left">{labels.snippetsLayoutLeft}</option>
                            <option value="center">{labels.snippetsLayoutCenter}</option>
                            <option value="minimal">{labels.snippetsLayoutMinimal}</option>
                        </select>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer self-end pb-1">
                        <input type="checkbox" data-test-id="header-sticky" checked={sticky} onChange={e => onChange({...data, sticky: e.target.checked})} />
                        <span className="text-sm text-secondary">{labels.snippetsSticky}</span>
                    </label>
                </div>
            </div>
        </div>
    );
};
