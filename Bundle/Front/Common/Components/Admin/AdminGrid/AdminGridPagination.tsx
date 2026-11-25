import * as React from 'react';
import Pagination from '@common/Components/Layout/Paging/Pagination';

type Props = React.ComponentProps<typeof Pagination> & {position: 'top' | 'bottom'};

/** Handles AdminGridPagination. */
export function AdminGridPagination({position, ...props}: Props) {
    return (
        <div className={position === 'top' ? 'admin-grid-pagination-top' : 'admin-grid-pagination-bottom'}>
            <Pagination {...props} />
        </div>
    );
}
