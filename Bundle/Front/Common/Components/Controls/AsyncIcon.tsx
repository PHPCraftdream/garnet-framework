import * as React from 'react';
import {AlertTriangle, Loader2} from 'lucide-react';
import {AsyncIconState} from '@common/hooks/ui/useAsyncIcon';

/**
 * Renders the correct glyph for an {@link AsyncIconState}:
 *   idle    → the supplied `icon`
 *   loading → a spinner
 *   error   → an error icon (danger-coloured)
 *
 * Icon-system agnostic: `icon` is any ReactNode, so it works with Lucide
 * components (`<Archive size={16}/>`) or Bootstrap glyphs
 * (`<i className="bi bi-archive"/>`).
 */
export const AsyncIcon: React.FC<{
    state: AsyncIconState;
    icon: React.ReactNode;
    size?: number;
    /** Override the error glyph (default: a danger triangle). */
    errorIcon?: React.ReactNode;
}> = ({state, icon, size = 18, errorIcon}) => {
    if (state === 'loading') {
        return <Loader2 size={size} className="animate-spin" aria-hidden="true" />;
    }
    if (state === 'error') {
        return errorIcon !== undefined
            ? <>{errorIcon}</>
            : <AlertTriangle size={size} className="text-danger" aria-hidden="true" />;
    }
    return <>{icon}</>;
};
