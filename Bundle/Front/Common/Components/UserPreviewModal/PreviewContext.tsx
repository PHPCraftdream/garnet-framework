import * as React from 'react';

/**
 * Generic in-foreground user preview context.
 *
 * Provider exposes `openPreview(userId, name?)`. Consumer components (e.g. EntityLink)
 * use `useContext` and call openPreview when present; if not present (e.g. admin
 * panel), they should fall back to navigation via href.
 */
export interface PreviewCtx {
    openPreview: ((userId: number, name?: string) => void) | null;
}

export const PreviewContext = React.createContext<PreviewCtx>({openPreview: null});

export function usePreview(): PreviewCtx {
    return React.useContext(PreviewContext);
}
