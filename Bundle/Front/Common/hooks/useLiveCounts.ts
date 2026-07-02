import {useEffect, useState} from 'react';
import {startLiveCounts, subscribeLiveCounts, LiveCounts} from '@common/Utils/liveCounts';

/**
 * React access to the shared live-counter poller. Starts the singleton poll
 * loop on mount and re-renders whenever fresh counts arrive. Returns null until
 * the first successful poll, so callers fall back to their server-rendered
 * values in the meantime.
 */
export const useLiveCounts = (): LiveCounts | null => {
    const [counts, setCounts] = useState<LiveCounts | null>(null);
    useEffect(() => {
        startLiveCounts();
        return subscribeLiveCounts(setCounts);
    }, []);
    return counts;
};
