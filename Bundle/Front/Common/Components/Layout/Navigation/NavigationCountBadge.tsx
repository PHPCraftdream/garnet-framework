import * as React from 'react';

interface Props {
    count: number;
}

/** Badge for a menu item's positive count. */
export function NavigationCountBadge({count}: Props) {
    return <span className="count-badge-warning" data-navigation-badge="true">{count}</span>;
}
