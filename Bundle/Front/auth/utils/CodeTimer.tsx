import * as React from 'react';
import {memo, useEffect, useRef, useState} from 'react';
import {unixTime} from '@common/Utils/UnixTime';
import {secondsToTime} from '@common/Utils/SecondsToTime';

type TPropsRender = { value: number, onTimeout: () => void };

const CodeTimerRender: React.FC<TPropsRender> = memo((props: TPropsRender) => {
    const {value, onTimeout} = props;
    const [val, set] = useState(value);
    const timerRef = useRef<number>();
    const initRef = useRef<number>(unixTime());

    useEffect(() => {
        timerRef.current = window.setInterval(() => {
            set((val) => {
                const newValue = value - (unixTime() - initRef.current);

                if (newValue <= 0) {
                    onTimeout();
                    clearInterval(timerRef.current);
                    timerRef.current = null;
                }

                return newValue;
            });
        }, 1000);

        return () => {
            clearInterval(timerRef.current);
            timerRef.current = null;
        };
    }, [value]);

    if (!val) {
        return null;
    }

    return <div className="flex items-center auth2-timer" data-test-id="auth-code-timer">{secondsToTime(val)}</div>;
});

type TProps = { value?: number, onTimeout: () => void };

export const CodeTimer: React.FC<TProps> = (props: TProps) => {
    if (!props.value) {
        return null;
    }

    return <CodeTimerRender value={props.value} onTimeout={props.onTimeout} />;
};


