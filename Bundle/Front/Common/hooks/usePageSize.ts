import {useState, useEffect, useCallback} from 'react';
import {readPageSize, writePageSize} from '@common/Utils/pagination';

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
    const [size, setSize] = useState<number>(() => readPageSize());

    useEffect(() => {
        const onLocalChange = (e: Event) => {
            const detail = (e as CustomEvent<number>).detail;
            if (typeof detail === 'number') setSize(detail);
        };
        const onStorageChange = (e: StorageEvent) => {
            if (e.key === null || e.key === 'garnet.pageSize') {
                setSize(readPageSize());
            }
        };
        window.addEventListener('garnet:pageSizeChange', onLocalChange);
        window.addEventListener('storage', onStorageChange);
        return () => {
            window.removeEventListener('garnet:pageSizeChange', onLocalChange);
            window.removeEventListener('storage', onStorageChange);
        };
    }, []);

    const update = useCallback((n: number) => {
        writePageSize(n);
        setSize(n);
    }, []);

    return [size, update];
}
