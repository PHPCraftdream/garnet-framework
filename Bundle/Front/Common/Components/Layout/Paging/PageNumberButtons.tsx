import * as React from 'react';

interface Props {
    /** Page numbers and ellipsis markers to render. */
    pages: (number | '...')[];
    currentPage: number;
    loading?: boolean;
    onPageChange: (page: number) => void;
}

interface PageButtonProps {
    page: number;
    current: boolean;
    loading?: boolean;
    onPageChange: (page: number) => void;
}

class PageButton extends React.Component<PageButtonProps> {
    private readonly handleClick = (): void => {
        this.props.onPageChange(this.props.page);
    };

    public render() {
        const {page, current, loading} = this.props;
        return (
            <button
                type="button"
                className={current ? 'common-pgn-btn-active' : 'common-pgn-btn'}
                disabled={loading}
                onClick={this.handleClick}
                aria-current={current ? 'page' : undefined}
                data-test-id={`pagination-page-${page}`}
            >
                {page}
            </button>
        );
    }
}

/** Numbered page-link strip used by Pagination. */
export class PageNumberButtons extends React.Component<Props> {
    private readonly renderPage = (page: number | '...', index: number): React.ReactNode => {
        if (page === '...') {
            return <span key={`ellipsis-${index}`} className="common-pgn-ellipsis">...</span>;
        }
        const {currentPage, loading, onPageChange} = this.props;
        return <PageButton key={page} page={page} current={page === currentPage} loading={loading} onPageChange={onPageChange} />;
    };

    public render() {
        return <>{this.props.pages.map(this.renderPage)}</>;
    }
}
