import {useState, useEffect, useSyncExternalStore, useCallback} from 'react';
import type {Dispatch, SetStateAction} from 'react';
import {PaginationController} from './PaginationController';

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

/** Provides page data and navigation controls. */
export function usePagination<T = Record<string, unknown>>(options: UsePaginationOptions<T>): UsePaginationResult<T> {
    const {url, params, perPage, debounceMs = 300} = options;
    const [controller] = useState(() => new PaginationController(options));
    const state = useSyncExternalStore(controller.subscribeSnapshot, controller.snapshot, controller.snapshot);

    useEffect(() => {
        controller.setUrl(url);
        controller.setPinnedPerPage(perPage);
        controller.setParams(params, debounceMs);
    }, [controller, url, perPage, params, debounceMs]);

    useEffect(() => {
        controller.start();
        return controller.dispose;
    }, [controller]);

    // Callers compare their captured page after awaiting a mutation.
    const {page, totalPages} = state;
    const goToPage = useCallback((p: number): void => {
        if (p < 1 || p > totalPages || p === page) return;
        void controller.fetchPage(p);
    }, [controller, page, totalPages]);
    const nextPage = useCallback((): void => {
        if (page < totalPages) void controller.fetchPage(page + 1);
    }, [controller, page, totalPages]);
    const prevPage = useCallback((): void => {
        if (page > 1) void controller.fetchPage(page - 1);
    }, [controller, page]);
    const refresh = useCallback((): void => {
        void controller.fetchPage(page);
    }, [controller, page]);

    const {setItems, setPerPage} = controller;
    return {...state, setItems, goToPage, setPerPage, nextPage, prevPage, refresh};
}
