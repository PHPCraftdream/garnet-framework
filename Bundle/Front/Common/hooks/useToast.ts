import {useState, useCallback, useRef} from 'react';

export type ToastType = 'primary' | 'success' | 'danger' | 'warning';

export interface ToastState {
    message: string;
    type: ToastType;
    visible: boolean;
}

export interface ToastControls {
    toast: ToastState;
    showToast: (message: string, type?: ToastType) => void;
    hideToast: () => void;
    /** Pause auto-hide timer (call on mouse enter) */
    pause: () => void;
    /** Resume auto-hide timer (call on mouse leave) */
    resume: () => void;
}

export function useToast(): ToastControls {
    const [toast, setToast] = useState<ToastState>({message: '', type: 'primary', visible: false});
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const remainingRef = useRef(0);
    const startRef = useRef(0);

    const clearTimer = () => {
        if (timerRef.current) { clearTimeout(timerRef.current); timerRef.current = null; }
    };

    const startTimer = (ms: number) => {
        clearTimer();
        remainingRef.current = ms;
        startRef.current = Date.now();
        timerRef.current = setTimeout(() => setToast(prev => ({...prev, visible: false})), ms);
    };

    const showToast = useCallback((message: string, type: ToastType = 'primary') => {
        clearTimer();
        setToast({message, type, visible: true});
        startTimer(4000);
    }, []);

    const hideToast = useCallback(() => {
        clearTimer();
        setToast(prev => ({...prev, visible: false}));
    }, []);

    const pause = useCallback(() => {
        if (timerRef.current) {
            remainingRef.current = Math.max(0, remainingRef.current - (Date.now() - startRef.current));
            clearTimer();
        }
    }, []);

    const resume = useCallback(() => {
        if (remainingRef.current > 0) startTimer(remainingRef.current);
    }, []);

    return {toast, showToast, hideToast, pause, resume};
}
