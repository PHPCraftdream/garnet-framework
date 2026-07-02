import * as React from 'react';
import {AlertTriangle, Loader2} from 'lucide-react';
import {AsyncIconState, useAsyncIcon, UseAsyncIconOptions} from '@common/hooks/useAsyncIcon';

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

export interface AsyncIconButtonProps extends UseAsyncIconOptions {
    /** Idle glyph (Lucide node or `<i className="bi …"/>`). */
    icon: React.ReactNode;
    /** The request to run on click. The button shows a loader while it's pending. */
    onAction: () => unknown | Promise<unknown>;
    /** Optional text beside the icon. */
    label?: React.ReactNode;
    /** Render as an anchor instead of a button (still intercepts the click). */
    href?: string;
    className?: string;
    title?: string;
    ariaLabel?: string;
    testId?: string;
    disabled?: boolean;
    iconSize?: number;
    errorIcon?: React.ReactNode;
}

/**
 * A button (or link) whose icon turns into a spinner while its request runs,
 * flips to an error icon + raises a toast on failure, then reverts after 2s —
 * all centralised through {@link useAsyncIcon}. No per-call-site boilerplate.
 *
 * ```tsx
 * <AsyncIconButton
 *   icon={<Archive size={16}/>}
 *   className="util-icon-btn"
 *   title="Move to archive"
 *   testId={`news-archive-${id}`}
 *   errorToast="Failed to archive"
 *   onAction={() => archiveEvent(id)}   // returns the sendPost promise
 * />
 * ```
 */
export const AsyncIconButton: React.FC<AsyncIconButtonProps> = ({
    icon, onAction, label, href, className = '', title, ariaLabel, testId,
    disabled = false, iconSize = 18, errorIcon,
    errorToast, errorMs, onError, rethrow,
}) => {
    const {state, isLoading, run} = useAsyncIcon({errorToast, errorMs, onError, rethrow});

    const handleClick = (e: React.MouseEvent) => {
        e.preventDefault();
        if (disabled || isLoading) return;
        void run(onAction);
    };

    const inner = (
        <>
            <AsyncIcon state={state} icon={icon} size={iconSize} errorIcon={errorIcon} />
            {label != null && <span>{label}</span>}
        </>
    );

    const shared = {
        className,
        title,
        'aria-label': ariaLabel ?? title,
        'aria-busy': isLoading || undefined,
        'data-test-id': testId,
        onClick: handleClick,
    };

    if (href != null) {
        return (
            <a href={href} aria-disabled={disabled || isLoading || undefined} {...shared}>
                {inner}
            </a>
        );
    }

    return (
        <button type="button" disabled={disabled || isLoading} {...shared}>
            {inner}
        </button>
    );
};
