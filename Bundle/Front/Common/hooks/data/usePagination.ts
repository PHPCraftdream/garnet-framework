import {useState, useCallback, useRef, useEffect, useSyncExternalStore, Dispatch, SetStateAction} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {usePageSize} from '@common/hooks/data/usePageSize';

export interface PageResponse<T = Record<string, unknown>> {
    items: T[];
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
}

export interface UsePaginationOptions<T> {
    /** POST endpoint URL */
    url: string;
    /**
     * Extra params to send alongside page/perPage. Reactive: a change
     * (by reference — memoize with useMemo/useState) refetches from page 1
     * after `debounceMs`, same as a search box or sort-column click.
     */
    params?: Record<string, unknown>;
    /**
     * Pin the page size, ignoring the user's localStorage preference. Use
     * sparingly — only when a list has a hard backend cap that wouldn't
     * survive the user picking 100/page (e.g. an embed-mode preview).
     */
    perPage?: number;
    /** SSR initial data — skips fetch on mount when provided */
    initialData?: PageResponse<T>;
    /** Debounce before a `params` change triggers a refetch. Default 300ms. */
    debounceMs?: number;
}

export interface UsePaginationResult<T> {
    items: T[];
    /** Patch the current page in place (e.g. after a row mutation succeeds) — does not refetch. */
    setItems: Dispatch<SetStateAction<T[]>>;
    page: number;
    perPage: number;
    totalPages: number;
    total: number;
    loading: boolean;
    goToPage: (page: number) => void;
    setPerPage: (n: number) => void;
    nextPage: () => void;
    prevPage: () => void;
    refresh: () => void;
}

interface PaginationState<T> {
    items: T[];
    page: number;
    totalPages: number;
    total: number;
    loading: boolean;
}

class PaginationStore<T> extends Carburetor<PaginationState<T>> {
    constructor(initialData?: PageResponse<T>) {
        super({
            items: initialData?.items ?? [],
            page: initialData?.page ?? 1,
            totalPages: initialData?.totalPages ?? 1,
            total: initialData?.total ?? 0,
            loading: false,
        });
    }

    readonly snapshot = () => this.getData();
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };

    readonly update = (changes: Partial<PaginationState<T>>) => {
        const previous = this.getData();
        const version = this.getVersion();
        this.setData({...previous, ...changes});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };

    readonly setItems: Dispatch<SetStateAction<T[]>> = (value) => {
        const previous = this.snapshot().items;
        const items = typeof value === 'function' ? value(previous) : value;
        if (!Object.is(items, previous)) this.update({items});
    };
}

export function usePagination<T = Record<string, unknown>>(options: UsePaginationOptions<T>): UsePaginationResult<T> {
    const {url, params, perPage: pinnedPerPage, initialData, debounceMs = 300} = options;
    const [storedPageSize, setStoredPageSize] = usePageSize();
    const perPage = pinnedPerPage ?? storedPageSize;

    const [store] = useState(() => new PaginationStore<T>(initialData));
    const {items, page, totalPages, total, loading} = useSyncExternalStore(
        store.subscribeSnapshot, store.snapshot, store.snapshot,
    );
    const setItems = store.setItems;

    const hasInitialData = useRef(!!initialData);
    const mountedRef = useRef(true);

    useEffect(() => {
        return () => { mountedRef.current = false; };
    }, []);

    // Latest params/perPage via ref — fetchPage must not close over a stale
    // value just because it wasn't re-created this render.
    const paramsRef = useRef(params);
    paramsRef.current = params;

    const fetchPage = useCallback(async (targetPage: number, targetPerPage: number = perPage) => {
        store.update({loading: true});
        try {
            const resp = await sendPost<{page: number; perPage: number} & Record<string, unknown>, PageResponse<T>>(
                url,
                {page: targetPage, perPage: targetPerPage, ...paramsRef.current}
            );
            if (!mountedRef.current) return;
            const data = ('data' in resp && resp.data) ? resp.data : resp as unknown as PageResponse<T>;
            store.update({items: data.items, page: data.page, totalPages: data.totalPages, total: data.total});
        } catch {
            // Error is handled by caller if needed; keep existing state
        } finally {
            if (mountedRef.current) {
                store.update({loading: false});
            }
        }
    }, [url, perPage, store]);

    // Fetch on mount if no initialData
    useEffect(() => {
        if (hasInitialData.current) {
            hasInitialData.current = false;
            return;
        }
        fetchPage(1);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Reactive params (search query, sort column, filters…) — refetch from
    // page 1 after a short debounce. Skips the render that just mounted
    // (that one is covered by the effect above, or by initialData).
    const isFirstParamsRun = useRef(true);
    useEffect(() => {
        if (isFirstParamsRun.current) {
            isFirstParamsRun.current = false;
            return;
        }
        const handle = setTimeout(() => fetchPage(1), debounceMs);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [params, debounceMs]);

    const goToPage = useCallback((p: number) => {
        if (p < 1 || p > totalPages || p === page) return;
        fetchPage(p);
    }, [fetchPage, totalPages, page]);

    const setPerPage = useCallback((n: number) => {
        // Persist user choice (no-op when caller pinned perPage explicitly)
        // and refetch from page 1 with the new size.
        if (pinnedPerPage == null) setStoredPageSize(n);
        fetchPage(1, n);
    }, [fetchPage, pinnedPerPage, setStoredPageSize]);

    const nextPage = useCallback(() => {
        if (page < totalPages) fetchPage(page + 1);
    }, [fetchPage, page, totalPages]);

    const prevPage = useCallback(() => {
        if (page > 1) fetchPage(page - 1);
    }, [fetchPage, page]);

    const refresh = useCallback(() => {
        fetchPage(page);
    }, [fetchPage, page]);

    return {items, setItems, page, perPage, totalPages, total, loading, goToPage, setPerPage, nextPage, prevPage, refresh};
}
