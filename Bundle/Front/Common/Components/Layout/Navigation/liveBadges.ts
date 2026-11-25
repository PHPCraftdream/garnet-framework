import {MenuItem, UtilityClusterData, LiveBadgeConfig} from './types';
import {LiveCounts} from '@common/Utils/Data/liveCounts/LiveCounts';

/** Applies current live counts to menu badges and utility links.
 *
 * Preserves server values until the first poll and leaves unavailable balances unchanged.
 */
export const applyLiveCounts = (
    menuItems: MenuItem[],
    utility: UtilityClusterData | undefined,
    live: LiveCounts | null,
    config?: LiveBadgeConfig,
): {items: MenuItem[]; util: UtilityClusterData | undefined} => {
    if (!live) {
        return {items: menuItems, util: utility};
    }

    const items = menuItems.map(item =>
        (config?.primaryBadgeItemId && item.id === config.primaryBadgeItemId)
            ? {...item, badge: live.primaryBadgeCount}
            : item,
    );

    const util = utility
        ? {
            ...utility,
            unreadMessages: live.unreadIm,
            unreadSupport: live.unreadSupport,
            // Only when the poll actually carries a balance: an app without
            // balances sends none, and overwriting with 0 would turn the
            // header into a permanent, confident "0 ₽".
            ...(live.balance === null ? {} : {balance: live.balance}),
        }
        : utility;

    return {items, util};
};
