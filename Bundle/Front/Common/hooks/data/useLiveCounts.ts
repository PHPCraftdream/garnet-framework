import {useEffect, useState, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {startLiveCounts} from '@common/Utils/Data/liveCounts/startLiveCounts';
import {subscribeLiveCounts} from '@common/Utils/Data/liveCounts/subscribeLiveCounts';
import type {LiveCounts} from '@common/Utils/Data/liveCounts/LiveCounts';

/** Creates a store for live counter values. */
function createLiveCountsStore(): LiveCountsStore { return new LiveCountsStore(); }

class LiveCountsStore extends Carburetor<{counts: LiveCounts | null}> {
    /** Initializes empty live counter state. */
    constructor() { super({counts: null}); }
    /** Returns the current store snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to store snapshot changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the current live counter values. */
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

/** Reads live counts from the shared poller.
 *
 * Returns null until the first successful poll, allowing server-rendered fallback values.
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
