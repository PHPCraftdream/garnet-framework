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

const pageIndexToNumber = (_: unknown, index: number): number => index + 1;
const comparePageNumbers = (a: number, b: number): number => a - b;

/** Keep the first, last, and nearby pages; mark gaps with ellipses. */
function getPageNumbers(page: number, totalPages: number): (number | '...')[] {
    if (totalPages <= 7) {
        return Array.from({length: totalPages}, pageIndexToNumber);
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

    const sorted = Array.from(near).toSorted(comparePageNumbers);

    for (let i = 0; i < sorted.length; i++) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
            pages.push('...');
        }
        pages.push(sorted[i]);
    }

    return pages;
}

export default class Pagination extends React.Component<Props> {
    private readonly handlePrev = (): void => {
        this.props.onPageChange(this.props.page - 1);
    };

    private readonly handleNext = (): void => {
        this.props.onPageChange(this.props.page + 1);
    };

    private renderCompact(labels: PaginationLabels) {
        const {page, totalPages, loading} = this.props;
        return (
            <div className="paging-compact-row">
                <button type="button" className="common-pgn-btn" disabled={page <= 1 || loading} onClick={this.handlePrev} data-test-id="pagination-prev">
                    {labels.prev}
                </button>
                <span className="common-pgn-info" data-test-id="pagination-info">{page} {labels.of} {totalPages}</span>
                <button type="button" className="common-pgn-btn" disabled={page >= totalPages || loading} onClick={this.handleNext} data-test-id="pagination-next">
                    {labels.next}
                </button>
            </div>
        );
    }

    private renderPages(labels: PaginationLabels, pages: (number | '...')[]) {
        const {page, totalPages, total, loading, onPageChange} = this.props;
        return (
            <div className="paging-pages-row">
                <button type="button" className="common-pgn-btn" disabled={page <= 1 || loading} onClick={this.handlePrev} data-test-id="pagination-prev">
                    {labels.prev}
                </button>
                <PageNumberButtons pages={pages} currentPage={page} loading={loading} onPageChange={onPageChange} />
                <button type="button" className="common-pgn-btn" disabled={page >= totalPages || loading} onClick={this.handleNext} data-test-id="pagination-next">
                    {labels.next}
                </button>
                {total != null && <span className="paging-total-info" data-test-id="pagination-info">{total} {labels.items}</span>}
            </div>
        );
    }

    public render() {
        const {page, totalPages, compact, labels, pageSize, onPageSizeChange} = this.props;
        const l = labels || fallbackLabels();
        const showSelector = pageSize != null && onPageSizeChange != null;
        if (totalPages <= 1 && !showSelector) return null;
        const pages = compact ? null : getPageNumbers(page, totalPages);
        return (
            <nav className="paging-navigation" aria-label="Pagination" data-test-id="pagination">
                {totalPages > 1 && (compact ? this.renderCompact(l) : this.renderPages(l, pages!))}
                {showSelector && <PageSizeSelector value={pageSize!} onChange={onPageSizeChange!} />}
            </nav>
        );
    }
}
