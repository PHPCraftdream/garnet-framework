/** Describes the counters broadcast to navigation and badge consumers. */
export interface LiveCounts {
    primaryBadgeCount: number;
    unreadIm: number;
    unreadSupport: number;
    /**
     * Account balance, or null where the app has no such notion.
     *
     * It belongs in the same poll as the badges for the same reason: the header
     * shows a number baked into the HTML once, and the balance changes from
     * several places that are not the header — topping up, booking, cancelling,
     * a refund arriving. Anything that keeps only the top-up form in step would
     * leave the other three stale.
     */
    balance: number | null;
}
