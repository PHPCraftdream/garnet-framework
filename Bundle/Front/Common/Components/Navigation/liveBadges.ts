import {MenuItem, UtilityClusterData} from './types';
import {LiveCounts} from '@common/Utils/liveCounts';

/**
 * Overlay freshly-polled counters onto the server-rendered menu/utility data:
 *   - the menu item tagged id='bookings' gets the live pending-bookings badge;
 *   - the utility cluster's message / support badges get the live unread counts.
 * Returns the inputs unchanged while `live` is null (before the first poll), so
 * the server values stay visible until then. Shared by TopMenu and MobileMenu.
 */
export const applyLiveCounts = (
    menuItems: MenuItem[],
    utility: UtilityClusterData | undefined,
    live: LiveCounts | null,
): {items: MenuItem[]; util: UtilityClusterData | undefined} => {
    if (!live) {
        return {items: menuItems, util: utility};
    }

    const items = menuItems.map(item =>
        item.id === 'bookings'
            ? {...item, badge: live.bookingsPending}
            : item,
    );

    const util = utility
        ? {...utility, unreadMessages: live.unreadIm, unreadSupport: live.unreadSupport}
        : utility;

    return {items, util};
};
