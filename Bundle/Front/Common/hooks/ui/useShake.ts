import {useState, useRef, useCallback, useEffect, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';

/** Creates a shake-animation store. */
function createShakeStore(): ShakeStore { return new ShakeStore(); }

class ShakeStore extends Carburetor<{shaking: boolean}> {
    /** Initializes the animation state. */
    constructor() { super({shaking: false}); }
    /** Returns the current animation snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to animation-state changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the shake-animation state. */
    readonly setShaking = (shaking: boolean) => {
        const previous = this.getData();
        if (previous.shaking === shaking) return;
        const version = this.getVersion();
        this.setData({shaking});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

/**
 * Reusable "shake" trigger — draws attention to an element by briefly shaking
 * it left-right (e.g. an error/notice when the user attempts a blocked action).
 *
 * Usage:
 *   const [shaking, shake] = useShake();
 *   <div className={shaking ? 'animate-shake' : ''}>Not enough balance</div>
 *   <button onClick={() => canDo ? doIt() : shake()}>Do it</button>
 *
 * Pair with the `.animate-shake` CSS class (see components.css). Re-triggering
 * while already animating restarts the animation.
 */
export function useShake(durationMs = 500): [boolean, () => void] {
    const [store] = useState(createShakeStore);
    const {shaking} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    const timer = useRef<number | undefined>(undefined);

    const shake = useCallback(() => {
        window.clearTimeout(timer.current);
        // Drop the class first so a repeat trigger restarts the animation.
        store.setShaking(false);
        requestAnimationFrame(() => {
            store.setShaking(true);
            timer.current = window.setTimeout(() => store.setShaking(false), durationMs);
        });
    }, [durationMs, store]);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    return [shaking, shake];
}
