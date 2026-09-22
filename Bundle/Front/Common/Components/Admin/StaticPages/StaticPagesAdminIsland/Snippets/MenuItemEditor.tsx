/**
 * Пункт меню используется и в шапке, и в подвале (HeaderEditor/FooterEditor) —
 * общая связка, физически расположена рядом (в этой же папке).
 */
import * as React from 'react';
import {StaticPage, Labels} from '../types';
import {ITEM_TYPES, ITEM_TYPE_LABELS} from './menuItemTypes';

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
