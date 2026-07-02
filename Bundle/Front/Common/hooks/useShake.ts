import {useState, useRef, useCallback, useEffect} from 'react';

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
    const [shaking, setShaking] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    const shake = useCallback(() => {
        window.clearTimeout(timer.current);
        // Drop the class first so a repeat trigger restarts the animation.
        setShaking(false);
        requestAnimationFrame(() => {
            setShaking(true);
            timer.current = window.setTimeout(() => setShaking(false), durationMs);
        });
    }, [durationMs]);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    return [shaking, shake];
}
