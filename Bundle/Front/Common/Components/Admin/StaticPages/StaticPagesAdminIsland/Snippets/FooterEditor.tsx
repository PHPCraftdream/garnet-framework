import * as React from 'react';
import {StaticPage, Labels} from '../types';
import {MenuItemData, MenuItemEditor} from './MenuItemEditor';

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
