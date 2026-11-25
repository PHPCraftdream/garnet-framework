import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {unixTime} from '@common/Utils/Time/UnixTime';
import {secondsToTime} from '@common/Utils/Time/SecondsToTime';

type TPropsRender = { value: number, onTimeout: () => void };

class CodeTimerRender extends AntiHookComponent<TPropsRender> {
    private readonly timerState = new Carburetor({value: this.props.value});
    private readonly initTime = unixTime();
    private timer: number | undefined;

    /** Starts the timer when mounted. */
    public componentDidMount(): void {
        super.componentDidMount();
        this.startTimer();
    }

    /** Updates the timer when its value changes. */
    public componentDidUpdate(prevProps: Readonly<TPropsRender>): void {
        super.componentDidUpdate(prevProps);
        if (prevProps.value !== this.props.value) {
            this.clearTimer();
            this.startTimer();
        }
    }

    /** Clears the timer when unmounted. */
    public componentWillUnmount(): void {
        this.clearTimer();
        super.componentWillUnmount();
    }

    /** Starts or restarts the countdown timer. */
    private startTimer(): void {
        const {value, onTimeout} = this.props;
        this.timer = window.setInterval(this.tick, 1000, value, onTimeout);
    }

    /** Decrements the countdown and notifies on expiry. */
    private readonly tick = (value: number, onTimeout: () => void): void => {
        const newValue = value - (unixTime() - this.initTime);
        if (newValue <= 0) {
            onTimeout();
            this.clearTimer();
        }
        this.timerState.setData({value: newValue});
    };

    /** Clears the active countdown timer. */
    private clearTimer(): void {
        window.clearInterval(this.timer);
        this.timer = undefined;
    }

    /** Renders the countdown. */
    public render() {
        const {value} = this.useCarburetor(this.timerState);
        if (!value) return null;
        return <div className="auth2-timer" data-test-id="auth-code-timer">{secondsToTime(value)}</div>;
    }
}

type TProps = { value?: number, onTimeout: () => void };

/** Renders a countdown with an optional expiry callback. */
export const CodeTimer: React.FC<TProps> = (props: TProps) => {
    if (!props.value) return null;
    return <CodeTimerRender value={props.value} onTimeout={props.onTimeout} />;
};
