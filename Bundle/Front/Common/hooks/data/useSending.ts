import {useState, useCallback, useRef, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';

class SendingStore extends Carburetor<{sending: boolean}> {
    /** Initializes the sending state. */
    constructor() { super({sending: false}); }
    /** Returns the current sending snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to sending-state changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the sending state. */
    readonly setSending = (sending: boolean) => {
        const previous = this.getData();
        if (previous.sending === sending) return;
        const version = this.getVersion();
        this.setData({sending});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

/** Creates a sending-state store. */
function createSendingStore(): SendingStore { return new SendingStore(); }

/** Provides sending state and a guarded async runner. */
export function useSending() {
    const [store] = useState(createSendingStore);
    const {sending} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    const setSending = store.setSending;
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
    }, [setSending]);

    return {sending, withSending};
}
