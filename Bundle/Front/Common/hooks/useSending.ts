import {useState, useCallback} from 'react';

export function useSending() {
    const [sending, setSending] = useState(false);

    const withSending = useCallback(async (fn: () => Promise<void>) => {
        if (sending) return;
        setSending(true);
        try {
            await fn();
        } finally {
            setSending(false);
        }
    }, [sending]);

    return {sending, withSending};
}
