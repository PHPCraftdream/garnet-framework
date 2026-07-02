import {useState, useCallback, useRef, useEffect} from 'react';
import {sendPost} from '@common/Api/sendPost';
import {usePageSize} from '@common/hooks/usePageSize';

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
    /** Extra params to send alongside page/perPage */
    params?: Record<string, unknown>;
    /**
     * Pin the page size, ignoring the user's localStorage preference. Use
     * sparingly — only when a list has a hard backend cap that wouldn't
     * survive the user picking 100/page (e.g. an embed-mode preview).
     */
    perPage?: number;
    /** SSR initial data — skips fetch on mount when provided */
    initialData?: PageResponse<T>;
}

export interface UsePaginationResult<T> {
    items: T[];
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

export function usePagination<T = Record<string, unknown>>(options: UsePaginationOptions<T>): UsePaginationResult<T> {
    const {url, params, perPage: pinnedPerPage, initialData} = options;
    const [storedPageSize, setStoredPageSize] = usePageSize();
    const perPage = pinnedPerPage ?? storedPageSize;

    const [items, setItems] = useState<T[]>(initialData?.items ?? []);
    const [page, setPage] = useState(initialData?.page ?? 1);
    const [totalPages, setTotalPages] = useState(initialData?.totalPages ?? 1);
    const [total, setTotal] = useState(initialData?.total ?? 0);
    const [loading, setLoading] = useState(false);

    const hasInitialData = useRef(!!initialData);
    const mountedRef = useRef(true);

    useEffect(() => {
        return () => { mountedRef.current = false; };
    }, []);

    const fetchPage = useCallback(async (targetPage: number, targetPerPage: number = perPage) => {
        if (loading) return;
        setLoading(true);
        try {
            const resp = await sendPost<{page: number; perPage: number} & Record<string, unknown>, PageResponse<T>>(
                url,
                {page: targetPage, perPage: targetPerPage, ...params}
            );
            if (!mountedRef.current) return;
            const data = ('data' in resp && resp.data) ? resp.data : resp as unknown as PageResponse<T>;
            setItems(data.items);
            setPage(data.page);
            setTotalPages(data.totalPages);
            setTotal(data.total);
        } catch {
            // Error is handled by caller if needed; keep existing state
        } finally {
            if (mountedRef.current) {
                setLoading(false);
            }
        }
    }, [url, perPage, params, loading]);

    // Fetch on mount if no initialData
    useEffect(() => {
        if (hasInitialData.current) {
            hasInitialData.current = false;
            return;
        }
        fetchPage(1);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

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

    return {items, page, perPage, totalPages, total, loading, goToPage, setPerPage, nextPage, prevPage, refresh};
}
