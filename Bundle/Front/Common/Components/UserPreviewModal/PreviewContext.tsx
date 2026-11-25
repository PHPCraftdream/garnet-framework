import * as React from 'react';

/** Context value for opening the user-preview interface. */
export interface PreviewCtx {
    openPreview: ((userId: number, name?: string) => void) | null;
}

/** Shared state through which preview providers expose their open action. */
export const PreviewContext = React.createContext<PreviewCtx>({openPreview: null});
