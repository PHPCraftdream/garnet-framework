import * as React from 'react';
import {PreviewContext, type PreviewCtx} from './PreviewContext';

/** Read the current user preview context. */
export function usePreview(): PreviewCtx {
    return React.useContext(PreviewContext);
}
