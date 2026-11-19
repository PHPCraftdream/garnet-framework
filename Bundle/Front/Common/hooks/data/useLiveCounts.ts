import {useEffect, useState, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {startLiveCounts, subscribeLiveCounts, LiveCounts} from '@common/Utils/Data/liveCounts';

function createLiveCountsStore(): LiveCountsStore { return new LiveCountsStore(); }

class LiveCountsStore extends Carburetor<{counts: LiveCounts | null}> {
    constructor() { super({counts: null}); }
    readonly snapshot = () => this.getData();
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    readonly setCounts = (counts: LiveCounts | null) => {
        const previous = this.getData();
        if (Object.is(previous.counts, counts)) return;
        const version = this.getVersion();
        this.setData({counts});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

/**
 * React access to the shared live-counter poller. Starts the singleton poll
 * loop on mount and re-renders whenever fresh counts arrive. Returns null until
 * the first successful poll, so callers fall back to their server-rendered
 * values in the meantime.
 */
export const useLiveCounts = (): LiveCounts | null => {
    const [store] = useState(createLiveCountsStore);
    const {counts} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    useEffect(() => {
        startLiveCounts();
        return subscribeLiveCounts(store.setCounts);
    }, [store]);
    return counts;
};
