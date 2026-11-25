import {Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {readPageSize} from '@common/Utils/Data/pagination/readPageSize';
import {writePageSize} from '@common/Utils/Data/pagination/writePageSize';
import type {PageResponse, UsePaginationOptions} from './usePagination';

type PaginationControllerOptions<T> = Omit<UsePaginationOptions<T>, 'initialData'> & {
    initialData?: PageResponse<T> | null;
};

interface PaginationState<T> {
    items: T[];
    page: number;
    perPage: number;
    totalPages: number;
    total: number;
    loading: boolean;
}

export class PaginationController<T = Record<string, unknown>> extends Carburetor<PaginationState<T>> {
    private url: string;
    private params: Record<string, unknown> | undefined;
    private pinnedPerPage: number | undefined;
    private storedPageSize: number;
    private debounceMs: number;
    private readonly hasInitialData: boolean;
    private generation = 0;
    private active = false;
    private debounceTimer: ReturnType<typeof setTimeout> | undefined;

    /** Creates a pagination controller from the supplied options. */
    constructor({url, params, perPage, initialData, debounceMs = 300}: PaginationControllerOptions<T>) {
        const storedPageSize = readPageSize();
        super({
            items: initialData?.items ?? [],
            page: initialData?.page ?? 1,
            perPage: perPage ?? storedPageSize,
            totalPages: initialData?.totalPages ?? 1,
            total: initialData?.total ?? 0,
            loading: false,
        });
        this.url = url;
        this.params = params;
        this.pinnedPerPage = perPage;
        this.storedPageSize = storedPageSize;
        this.debounceMs = debounceMs;
        this.hasInitialData = !!initialData;
    }

    /** Returns the current pagination snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to pagination snapshot changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };

    /** Applies a partial update to pagination state. */
    private patch(changes: Partial<PaginationState<T>>) {
        const previous = this.getData();
        const version = this.getVersion();
        this.setData({...previous, ...changes});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    }

    /** Replaces or updates the current page items. */
    readonly setItems = (value: T[] | ((previous: T[]) => T[])) => {
        const previous = this.snapshot().items;
        const items = typeof value === 'function' ? value(previous) : value;
        if (!Object.is(items, previous)) this.patch({items});
    };

    /** Updates the page request URL. */
    readonly setUrl = (url: string) => {
        this.url = url;
    };

    /** Sets the optional fixed page size. */
    readonly setPinnedPerPage = (perPage?: number) => {
        this.pinnedPerPage = perPage;
        this.syncPerPage();
    };

    /** Updates request parameters and schedules a refresh. */
    readonly setParams = (params?: Record<string, unknown>, debounceMs = this.debounceMs) => {
        if (Object.is(params, this.params) && Object.is(debounceMs, this.debounceMs)) return;
        this.params = params;
        this.debounceMs = debounceMs;
        if (!this.active) return;
        clearTimeout(this.debounceTimer);
        // Keep the scheduled URL and size, but send the latest params.
        const url = this.url;
        const {perPage} = this.snapshot();
        this.debounceTimer = setTimeout(() => this.requestPage(url, 1, perPage), debounceMs);
    };

    /** Synchronizes the effective page size. */
    private syncPerPage() {
        const perPage = this.pinnedPerPage ?? this.storedPageSize;
        if (!Object.is(perPage, this.snapshot().perPage)) this.patch({perPage});
    }

    /** Stores and applies the shared page size. */
    private setStoredPageSize(size: number) {
        this.storedPageSize = size;
        this.syncPerPage();
    }

    /** Handles page-size changes in this window. */
    private readonly onLocalChange = (event: Event) => {
        const detail = (event as CustomEvent<number>).detail;
        if (typeof detail === 'number') this.setStoredPageSize(detail);
    };

    /** Handles page-size changes from another window. */
    private readonly onStorageChange = (event: StorageEvent) => {
        if (event.key === null || event.key === 'garnet.pageSize') this.setStoredPageSize(readPageSize());
    };

    /** Starts subscriptions and loads initial data. */
    readonly start = () => {
        if (this.active) return;
        this.active = true;
        if (typeof window !== 'undefined') {
            window.addEventListener('garnet:pageSizeChange', this.onLocalChange);
            window.addEventListener('storage', this.onStorageChange);
        }
        if (!this.hasInitialData) this.fetchPage(1);
        else if (this.snapshot().loading) this.patch({loading: false});
    };

    /** Stops subscriptions and cancels pending work. */
    readonly dispose = () => {
        if (!this.active) return;
        this.active = false;
        this.generation++;
        clearTimeout(this.debounceTimer);
        if (typeof window !== 'undefined') {
            window.removeEventListener('garnet:pageSizeChange', this.onLocalChange);
            window.removeEventListener('storage', this.onStorageChange);
        }
    };

    /** Fetches a page and updates controller state. */
    private async requestPage(url: string, page: number, perPage: number) {
        if (!this.active) return;
        const generation = this.generation;
        this.patch({loading: true});
        try {
            const resp = await sendPost<{page: number; perPage: number} & Record<string, unknown>, PageResponse<T>>(
                url,
                {page, perPage, ...this.params}
            );
            if (this.generation !== generation) return;
            const data = ('data' in resp && resp.data) ? resp.data : resp as unknown as PageResponse<T>;
            this.patch({items: data.items, page: data.page, totalPages: data.totalPages, total: data.total});
        } catch {
            // Error is handled by caller if needed; keep existing state
        } finally {
            if (this.generation === generation) this.patch({loading: false});
        }
    }

    /** Fetches the requested page. */
    readonly fetchPage = (page: number, perPage = this.snapshot().perPage) => this.requestPage(this.url, page, perPage);

    /** Navigates to a valid page. */
    readonly goToPage = (target: number) => {
        const {page, totalPages} = this.snapshot();
        if (target < 1 || target > totalPages || target === page) return;
        this.fetchPage(target);
    };

    /** Changes the page size and reloads page one. */
    readonly setPerPage = (n: number) => {
        if (this.pinnedPerPage == null) {
            writePageSize(n);
            this.setStoredPageSize(n);
        }
        this.fetchPage(1, n);
    };

    /** Navigates to the next page when available. */
    readonly nextPage = () => {
        const {page, totalPages} = this.snapshot();
        if (page < totalPages) this.fetchPage(page + 1);
    };

    /** Navigates to the previous page when available. */
    readonly prevPage = () => {
        const {page} = this.snapshot();
        if (page > 1) this.fetchPage(page - 1);
    };

    /** Reloads the current page. */
    readonly refresh = () => {
        this.fetchPage(this.snapshot().page);
    };
}
