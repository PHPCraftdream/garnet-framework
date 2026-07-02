import * as React from 'react';
import {PageSizeSelector} from './PageSizeSelector';
import {PageNumberButtons} from './PageNumberButtons';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

export interface PaginationLabels {
    prev: string;
    next: string;
    of: string;
    items: string;
}

interface Props {
    page: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    total?: number;
    loading?: boolean;
    compact?: boolean;
    labels?: PaginationLabels;
    /** Current page size — pass together with `onPageSizeChange` to render the selector. */
    pageSize?: number;
    /** Setter for the selector. When omitted, the per-page dropdown is hidden. */
    onPageSizeChange?: (n: number) => void;
}

/** Localized fallback when the caller doesn't pass explicit labels. */
const fallbackLabels = (): PaginationLabels => ({
    prev: I18nFramework.Pagination_Prev(),
    next: I18nFramework.Pagination_Next(),
    of: I18nFramework.Pagination_Of(),
    items: I18nFramework.Pagination_Items(),
});

/**
 * Compute which page numbers to show: first, last, current +/- 1, with ellipses.
 * Example for page=5, total=10: [1, '...', 4, 5, 6, '...', 10]
 */
function getPageNumbers(page: number, totalPages: number): (number | '...')[] {
    if (totalPages <= 7) {
        return Array.from({length: totalPages}, (_, i) => i + 1);
    }

    const pages: (number | '...')[] = [];
    const near = new Set<number>();

    // Always include first and last
    near.add(1);
    near.add(totalPages);

    // Current page and neighbors
    for (let i = page - 1; i <= page + 1; i++) {
        if (i >= 1 && i <= totalPages) near.add(i);
    }

    const sorted = Array.from(near).toSorted((a, b) => a - b);

    for (let i = 0; i < sorted.length; i++) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
            pages.push('...');
        }
        pages.push(sorted[i]);
    }

    return pages;
}

export default function Pagination({page, totalPages, onPageChange, total, loading, compact, labels, pageSize, onPageSizeChange}: Props) {
    const l = labels || fallbackLabels();
    const showSelector = pageSize != null && onPageSizeChange != null;

    // The selector is useful even when there's only one page (the user may
    // want to switch back to a larger size after filtering down). Bail out
    // only when both controls would be empty.
    if (totalPages <= 1 && !showSelector) return null;

    const prevDisabled = page <= 1 || loading;
    const nextDisabled = page >= totalPages || loading;

    const btnNav = 'common-pgn-btn';

    if (compact) {
        return (
            <nav className="flex items-center gap-3 flex-wrap" aria-label="Pagination" data-test-id="pagination">
                {totalPages > 1 && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className={btnNav}
                            disabled={prevDisabled}
                            onClick={() => onPageChange(page - 1)}
                            data-test-id="pagination-prev"
                        >
                            {l.prev}
                        </button>
                        <span className="common-pgn-info" data-test-id="pagination-info">
                            {page} {l.of} {totalPages}
                        </span>
                        <button
                            type="button"
                            className={btnNav}
                            disabled={nextDisabled}
                            onClick={() => onPageChange(page + 1)}
                            data-test-id="pagination-next"
                        >
                            {l.next}
                        </button>
                    </div>
                )}
                {showSelector && <PageSizeSelector value={pageSize!} onChange={onPageSizeChange!} />}
            </nav>
        );
    }

    const pages = getPageNumbers(page, totalPages);

    return (
        <nav className="flex items-center gap-3 flex-wrap" aria-label="Pagination" data-test-id="pagination">
            {totalPages > 1 && (
                <div className="flex items-center gap-1 flex-wrap">
                    <button
                        type="button"
                        className={btnNav}
                        disabled={prevDisabled}
                        onClick={() => onPageChange(page - 1)}
                        data-test-id="pagination-prev"
                    >
                        {l.prev}
                    </button>

                    <PageNumberButtons
                        pages={pages}
                        currentPage={page}
                        loading={loading}
                        onPageChange={onPageChange}
                    />

                    <button
                        type="button"
                        className={btnNav}
                        disabled={nextDisabled}
                        onClick={() => onPageChange(page + 1)}
                        data-test-id="pagination-next"
                    >
                        {l.next}
                    </button>

                    {total != null && (
                        <span className="common-pgn-info ml-2" data-test-id="pagination-info">
                            {total} {l.items}
                        </span>
                    )}
                </div>
            )}
            {showSelector && <PageSizeSelector value={pageSize!} onChange={onPageSizeChange!} />}
        </nav>
    );
}
