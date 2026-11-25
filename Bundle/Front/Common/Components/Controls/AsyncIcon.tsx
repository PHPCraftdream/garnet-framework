import * as React from 'react';
import {AlertTriangle, Loader2} from 'lucide-react';
import {AsyncIconState} from '@common/hooks/ui/internal/useAsyncIcon';

/** Shows the icon corresponding to the current asynchronous state. */
/** Shows the icon corresponding to the current asynchronous state. */
export const AsyncIcon: React.FC<{
    state: AsyncIconState;
    icon: React.ReactNode;
    size?: number;
    /** Override the error glyph (default: a danger triangle). */
    errorIcon?: React.ReactNode;
}> = ({state, icon, size = 18, errorIcon}) => {
    if (state === 'loading') {
        return <Loader2 size={size} className="async-loading-icon" aria-hidden="true" />;
    }
    if (state === 'error') {
        return errorIcon !== undefined
            ? <>{errorIcon}</>
            : <AlertTriangle size={size} className="async-error-icon" aria-hidden="true" />;
    }
    return <>{icon}</>;
};
