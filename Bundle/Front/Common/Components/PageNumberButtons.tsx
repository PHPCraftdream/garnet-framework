import * as React from 'react';

interface Props {
    /**
     * Page numbers and ellipsis markers to render, e.g. `[1, '...', 4, 5, 6, '...', 10]`.
     * Use the `getPageNumbers` helper from Pagination to compute this list.
     */
    pages: (number | '...')[];
    currentPage: number;
    loading?: boolean;
    onPageChange: (page: number) => void;
}

/**
 * Numbered page-link strip used by Pagination.
 *
 * Split out so callers that want only the number row (without prev/next/info)
 * can compose it themselves — and so the rendering logic isn't buried inside
 * the larger Pagination component.
 */
export function PageNumberButtons({pages, currentPage, loading, onPageChange}: Props) {
    const btnActive = 'common-pgn-btn-active';
    const btnPage = 'common-pgn-btn';

    return (
        <>
            {pages.map((p, i) =>
                p === '...' ? (
                    <span key={`ellipsis-${i}`} className="common-pgn-ellipsis">...</span>
                ) : (
                    <button
                        key={p}
                        type="button"
                        className={p === currentPage ? btnActive : btnPage}
                        disabled={loading}
                        onClick={() => onPageChange(p)}
                        aria-current={p === currentPage ? 'page' : undefined}
                        data-test-id={`pagination-page-${p}`}
                    >
                        {p}
                    </button>
                )
            )}
        </>
    );
}
