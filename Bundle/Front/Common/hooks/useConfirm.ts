import {useState, useCallback, useRef} from 'react';

export interface ConfirmState {
    visible: boolean;
    message: string;
    items: string[];
    confirmLabel?: string;
    variant?: 'success' | 'danger';
}

export interface ConfirmOptions {
    items?: string[];
    confirmLabel?: string;
    variant?: 'success' | 'danger';
}

export function useConfirm() {
    const [confirmState, setConfirmState] = useState<ConfirmState>({visible: false, message: '', items: []});
    const resolveRef = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback((message: string, opts: string[] | ConfirmOptions = []): Promise<boolean> => {
        return new Promise(resolve => {
            resolveRef.current = resolve;
            // Backwards-compatible: if opts is an array, treat as items
            if (Array.isArray(opts)) {
                setConfirmState({visible: true, message, items: opts});
            } else {
                setConfirmState({
                    visible: true,
                    message,
                    items: opts.items ?? [],
                    confirmLabel: opts.confirmLabel,
                    variant: opts.variant,
                });
            }
        });
    }, []);

    const handleConfirm = useCallback(() => {
        setConfirmState(prev => ({...prev, visible: false}));
        resolveRef.current?.(true);
        resolveRef.current = null;
    }, []);

    const handleCancel = useCallback(() => {
        setConfirmState(prev => ({...prev, visible: false}));
        resolveRef.current?.(false);
        resolveRef.current = null;
    }, []);

    return {confirmState, confirm, handleConfirm, handleCancel};
}
