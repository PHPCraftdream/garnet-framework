import {useState, useEffect, useCallback, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {readPageSize} from '@common/Utils/Data/pagination/readPageSize';
import {writePageSize} from '@common/Utils/Data/pagination/writePageSize';

/** Creates a store for the page size. */
function createPageSizeStore(): PageSizeStore { return new PageSizeStore(); }

class PageSizeStore extends Carburetor<{size: number}> {
    /** Initializes the store from the saved page size. */
    constructor() { super({size: readPageSize()}); }
    /** Returns the current page-size snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to page-size snapshot changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the current page size. */
    readonly setSize = (size: number) => {
        const previous = this.getData();
        if (Object.is(previous.size, size)) return;
        const version = this.getVersion();
        this.setData({size});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

/**
 * Cross-grid page-size state synced via localStorage.
 *
 * Every grid/list that renders a <PageSizeSelector> calls this hook. The
 * setter writes the new value into localStorage AND broadcasts an in-page
 * CustomEvent so sibling grids on the same page update without a reload.
 *
 * Returns a tuple-style API like useState to keep call sites compact.
 */
export function usePageSize(): [number, (n: number) => void] {
    const [store] = useState(createPageSizeStore);
    const {size} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);

    useEffect(() => {
        const onLocalChange = (e: Event) => {
            const detail = (e as CustomEvent<number>).detail;
            if (typeof detail === 'number') store.setSize(detail);
        };
        const onStorageChange = (e: StorageEvent) => {
            if (e.key === null || e.key === 'garnet.pageSize') {
                store.setSize(readPageSize());
            }
        };
        window.addEventListener('garnet:pageSizeChange', onLocalChange);
        window.addEventListener('storage', onStorageChange);
        return () => {
            window.removeEventListener('garnet:pageSizeChange', onLocalChange);
            window.removeEventListener('storage', onStorageChange);
        };
    }, [store]);

    const update = useCallback((n: number) => {
        writePageSize(n);
        store.setSize(n);
    }, [store]);

    return [size, update];
}
