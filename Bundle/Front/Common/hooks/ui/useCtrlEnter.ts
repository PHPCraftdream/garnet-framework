import {KeyboardEvent} from 'react';

export {CTRL_ENTER_HINT} from '@common/hooks/ui/internal/CTRL_ENTER_HINT';

/**
 * Returns an onKeyDown handler that calls `onSubmit` when Ctrl+Enter is pressed.
 * Use with textarea elements where Enter creates newlines.
 *
 * Usage:
 *   const handleKeyDown = useCtrlEnter(handleSend, sending);
 *   <textarea onKeyDown={handleKeyDown} placeholder="... (Ctrl+Enter)" />
 */
export function useCtrlEnter(onSubmit: () => void, disabled?: boolean) {
    return (e: KeyboardEvent<HTMLTextAreaElement | HTMLInputElement>) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && !disabled) {
            e.preventDefault();
            onSubmit();
        }
    };
}

