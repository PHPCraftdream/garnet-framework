import {useState, useCallback, useRef} from 'react';

export function useSending() {
    const [sending, setSending] = useState(false);
    // Guard читается синхронно из ref, а не из React state: между быстрыми
    // повторными вызовами (двойной клик/тач) `sending` из состояния ещё не
    // успевает обновиться до перерисовки, и оба вызова проходили проверку —
    // один клик отправлял по несколько одинаковых запросов подряд.
    const sendingRef = useRef(false);

    const withSending = useCallback(async (fn: () => Promise<void>) => {
        if (sendingRef.current) return;
        sendingRef.current = true;
        setSending(true);
        try {
            await fn();
        } finally {
            sendingRef.current = false;
            setSending(false);
        }
    }, []);

    return {sending, withSending};
}
