/**
 * Редакторы шапки и подвала вместе с редактором пункта меню:
 * пункт меню используется и там, и там, и разносить их по разным
 * файлам значило бы разорвать одну связку.
 */
import * as React from 'react';
import {sendPost} from '@common/Api/Send/sendPost';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {EntityHistoryButton} from '@common/Components/Admin/EntityHistory/EntityHistoryButton';
import {TabNav, TabDef} from '@common/Components/Layout/Navigation/TabNav';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {formatTs} from '@common/Utils/Time/DateUtils';
import {markdownToHtml} from '@common/Utils/Ui/markdownToHtml';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {ImageUploadArea, ImageUploadField} from '@common/Components/Controls/ImageUploadField';
import {FileText} from 'lucide-react';
import {StaticPage, Labels} from '../types';

export interface MenuItemData {
    type: string;
    label?: string;
    url?: string;
    slug?: string;
    external?: boolean;
    // Тот же словарь, что у страниц и блоков: all | guest | auth | moderator.
    // Пустое значение = виден всем, поэтому старые меню не трогаем.
    visibility?: string;
}

export const ITEM_TYPES = ['link', 'page', 'divider'] as const;

export const ITEM_TYPE_LABELS: Record<string, (l: Labels) => string> = {
    link: l => l.snippetsItemTypeLink,
    page: l => l.snippetsItemTypePage,
    divider: l => l.snippetsItemTypeDivider,
};

export const MenuItemEditor: React.FC<{
    item: MenuItemData;
    index: number;
    total: number;
    onChange: (item: MenuItemData) => void;
    onDelete: () => void;
    onMove: (dir: -1 | 1) => void;
    pages: StaticPage[];
    labels: Labels;
    testId?: string;
}> = ({item, index, total, onChange, onDelete, onMove, pages, labels, testId = 'header-menu-item'}) => {
    const publishedPages = pages.filter(p => p.is_published);
    return (
        <div className="sp-menu-item-row" data-test-id={testId}>
            <select
                className="form-select form-select-sm w-28"
                value={item.type}
                onChange={e => {
                    const newType = e.target.value;
                    // Видимость переживает смену типа: она про то, кому пункт
                    // показывать, и к тому, ссылка это или страница, отношения
                    // не имеет — терять её при переключении было бы обидно.
                    const visibility = item.visibility;
                    if (newType === 'divider') {
                        onChange({type: 'divider'});
                    } else if (newType === 'page') {
                        onChange({type: 'page', slug: '', label: '', visibility});
                    } else {
                        onChange({type: 'link', label: item.label ?? '', url: item.url ?? '/', external: false, visibility});
                    }
                }}
            >
                {ITEM_TYPES.map(t => (
                    <option key={t} value={t}>{(ITEM_TYPE_LABELS[t] ?? (() => t))(labels)}</option>
                ))}
            </select>

            {item.type !== 'divider' && (
                <input
                    className="form-control form-control-sm"
                    style={{flex: '1 1 120px', minWidth: '120px'}}
                    placeholder={labels.snippetsItemLabel}
                    value={item.label ?? ''}
                    onChange={e => onChange({...item, label: e.target.value})}
                />
            )}

            {item.type === 'link' && (
                <>
                    <input
                        className="form-control form-control-sm"
                        style={{flex: '1 1 150px', minWidth: '150px'}}
                        placeholder={labels.snippetsItemUrl}
                        value={item.url ?? ''}
                        onChange={e => onChange({...item, url: e.target.value})}
                    />
                    <label className="flex items-center gap-1 text-xs text-secondary whitespace-nowrap cursor-pointer">
                        <input
                            type="checkbox"
                            checked={!!item.external}
                            onChange={e => onChange({...item, external: e.target.checked})}
                        />
                        {labels.snippetsItemExternal}
                    </label>
                </>
            )}

            {item.type === 'page' && (
                <select
                    className="form-select form-select-sm"
                    style={{flex: '1 1 180px', minWidth: '180px'}}
                    value={item.slug ?? ''}
                    onChange={e => onChange({...item, slug: e.target.value})}
                >
                    <option value="">{labels.snippetsSelectPage}</option>
                    {publishedPages.map(p => (
                        <option key={p.id} value={p.slug}>{p.title_rendered || p.title || p.slug}</option>
                    ))}
                </select>
            )}

            {item.type === 'divider' && <div className="flex-1" />}

            {item.type !== 'divider' && (
                <select
                    className="form-select form-select-sm text-xs"
                    style={{width: 'auto', minWidth: '90px'}}
                    value={item.visibility || 'all'}
                    onChange={e => onChange({...item, visibility: e.target.value})}
                    data-test-id="menu-item-visibility"
                    title={labels.visibility}
                >
                    <option value="all">{labels.visibilityAll}</option>
                    <option value="guest">{labels.visibilityGuest}</option>
                    <option value="auth">{labels.visibilityAuth}</option>
                    <option value="moderator">{labels.visibilityModerator}</option>
                </select>
            )}

            <button type="button" className="blk-icon-btn" data-test-id="move-up" onClick={() => onMove(-1)} disabled={index === 0} title={labels.moveUp}>&#8593;</button>
            <button type="button" className="blk-icon-btn" data-test-id="move-down" onClick={() => onMove(1)} disabled={index === total - 1} title={labels.moveDown}>&#8595;</button>
            <button type="button" className="blk-icon-btn-danger" data-test-id="delete-item" onClick={onDelete} title={labels.actionDelete}>&#215;</button>
        </div>
    );
};

// ── Header Editor ──

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

// ── Footer Editor ──

export interface FooterData {
    columns: {title: string; items: MenuItemData[]}[];
    copyright: string;
    layout: string;
}

export const FooterEditor: React.FC<{
    data: FooterData;
    onChange: (data: FooterData) => void;
    labels: Labels;
    pages: StaticPage[];
}> = ({data, onChange, labels, pages}) => {
    const columns = data.columns || [];
    const copyright = data.copyright || '';
    const layout = data.layout || 'columns';

    const updateColumn = (colIdx: number, field: string, value: any) => {
        const newCols = columns.map((c, i) => i === colIdx ? {...c, [field]: value} : c);
        onChange({...data, columns: newCols});
    };

    const addColumn = () => {
        onChange({...data, columns: [...columns, {title: '', items: []}]});
    };

    const removeColumn = (colIdx: number) => {
        onChange({...data, columns: columns.filter((_, i) => i !== colIdx)});
    };

    const updateColumnItem = (colIdx: number, itemIdx: number, item: MenuItemData) => {
        const col = columns[colIdx];
        const newItems = col.items.map((it, i) => i === itemIdx ? item : it);
        updateColumn(colIdx, 'items', newItems);
    };

    const deleteColumnItem = (colIdx: number, itemIdx: number) => {
        const col = columns[colIdx];
        updateColumn(colIdx, 'items', col.items.filter((_, i) => i !== itemIdx));
    };

    const moveColumnItem = (colIdx: number, itemIdx: number, dir: -1 | 1) => {
        const col = columns[colIdx];
        const target = itemIdx + dir;
        if (target < 0 || target >= col.items.length) return;
        const newItems = [...col.items];
        [newItems[itemIdx], newItems[target]] = [newItems[target], newItems[itemIdx]];
        updateColumn(colIdx, 'items', newItems);
    };

    const addColumnItem = (colIdx: number) => {
        const col = columns[colIdx];
        updateColumn(colIdx, 'items', [...col.items, {type: 'link', label: '', url: '/'}]);
    };

    return (
        <div className="space-y-4">
            {/* Columns */}
            <div className="sp-editor-section" data-test-id="footer-columns-section">
                <div className="sp-editor-section-title">{labels.snippetsColumns}</div>
                {columns.map((col, colIdx) => (
                    <div key={colIdx} className="sp-col-card" data-test-id="footer-column">
                        <div className="flex items-center gap-2">
                            <input
                                className="form-control form-control-sm flex-1"
                                placeholder={labels.snippetsColumnTitle}
                                value={col.title}
                                onChange={e => updateColumn(colIdx, 'title', e.target.value)}
                            />
                            <button
                                type="button"
                                className="btn btn-sm btn-danger"
                                onClick={() => removeColumn(colIdx)}
                            >
                                {labels.snippetsRemoveColumn}
                            </button>
                        </div>
                        {col.items.map((item, itemIdx) => (
                            <MenuItemEditor
                                key={itemIdx}
                                item={item}
                                index={itemIdx}
                                total={col.items.length}
                                onChange={updated => updateColumnItem(colIdx, itemIdx, updated)}
                                onDelete={() => deleteColumnItem(colIdx, itemIdx)}
                                onMove={dir => moveColumnItem(colIdx, itemIdx, dir)}
                                pages={pages}
                                testId="footer-col-item"
                                labels={labels}
                            />
                        ))}
                        <button type="button" className="btn btn-sm btn-secondary" data-test-id="footer-col-add-item" onClick={() => addColumnItem(colIdx)}>+ {labels.snippetsAddItem}</button>
                    </div>
                ))}
                <button type="button" className="btn btn-sm btn-secondary" data-test-id="footer-add-column" onClick={addColumn}>+ {labels.snippetsAddColumn}</button>
            </div>

            {/* Copyright */}
            <div className="sp-editor-section">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsCopyright}</span>
                    <input
                        className="form-control form-control-sm w-full"
                        data-test-id="footer-copyright"
                        placeholder="&copy; {year} {title}"
                        value={copyright}
                        onChange={e => onChange({...data, copyright: e.target.value})}
                    />
                    <span className="text-xs text-muted mt-1 block">{'{year}'}, {'{title}'}, {'{base-url}'}</span>
                </label>
            </div>

            {/* Settings */}
            <div className="sp-editor-section">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLayout}</span>
                    <select
                        className="form-select form-select-sm w-48"
                        value={layout}
                        onChange={e => onChange({...data, layout: e.target.value})}
                    >
                        <option value="columns">{labels.snippetsLayoutColumns}</option>
                        <option value="simple">{labels.snippetsLayoutSimple}</option>
                    </select>
                </label>
            </div>
        </div>
    );
};
