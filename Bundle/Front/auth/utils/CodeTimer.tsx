import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {unixTime} from '@common/Utils/Time/UnixTime';
import {secondsToTime} from '@common/Utils/Time/SecondsToTime';

type TPropsRender = { value: number, onTimeout: () => void };

class CodeTimerRender extends AntiHookComponent<TPropsRender> {
    private readonly timerState = new Carburetor({value: this.props.value});
    private readonly initTime = unixTime();
    private timer: number | undefined;

    public componentDidMount(): void {
        super.componentDidMount();
        this.startTimer();
    }

    public componentDidUpdate(prevProps: Readonly<TPropsRender>): void {
        super.componentDidUpdate(prevProps);
        if (prevProps.value !== this.props.value) {
            this.clearTimer();
            this.startTimer();
        }
    }

    public componentWillUnmount(): void {
        this.clearTimer();
        super.componentWillUnmount();
    }

    private startTimer(): void {
        const {value, onTimeout} = this.props;
        this.timer = window.setInterval(this.tick, 1000, value, onTimeout);
    }

    private readonly tick = (value: number, onTimeout: () => void): void => {
        const newValue = value - (unixTime() - this.initTime);
        if (newValue <= 0) {
            onTimeout();
            this.clearTimer();
        }
        this.timerState.setData({value: newValue});
    };

    private clearTimer(): void {
        window.clearInterval(this.timer);
        this.timer = undefined;
    }

    public render() {
        const {value} = this.useCarburetor(this.timerState);
        if (!value) return null;
        return <div className="auth2-timer" data-test-id="auth-code-timer">{secondsToTime(value)}</div>;
    }
}

type TProps = { value?: number, onTimeout: () => void };

export const CodeTimer: React.FC<TProps> = (props: TProps) => {
    if (!props.value) return null;
    return <CodeTimerRender value={props.value} onTimeout={props.onTimeout} />;
};
