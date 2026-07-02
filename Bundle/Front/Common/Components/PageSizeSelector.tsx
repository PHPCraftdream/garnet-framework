import * as React from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {PAGE_SIZE_OPTIONS} from '@common/Utils/pagination';

interface Props {
    value: number;
    onChange: (n: number) => void;
}

/**
 * Tiny dropdown for choosing how many rows a grid shows per page.
 *
 * Pure presentational — the value/onChange pair is owned by usePageSize() at
 * the grid level, which mirrors it into localStorage. Used by Pagination and
 * AdminGrid; works the same in both because both call usePageSize().
 */
export function PageSizeSelector({value, onChange}: Props) {
    return (
        <label className="inline-flex items-center gap-2 text-sm text-muted whitespace-nowrap" data-test-id="page-size-selector">
            <span>{t.Grid_PerPage()}</span>
            <select
                className="form-control form-control-sm w-auto"
                value={value}
                onChange={e => onChange(parseInt(e.target.value, 10))}
                data-test-id="page-size-select"
            >
                {PAGE_SIZE_OPTIONS.map(n => (
                    <option key={n} value={n}>{n}</option>
                ))}
            </select>
        </label>
    );
}
