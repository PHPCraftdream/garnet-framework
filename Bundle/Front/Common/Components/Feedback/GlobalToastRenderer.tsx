import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {ToastEntry, ToastManager} from './toastManager';

interface ToastItemProps {
    id: number;
    message: string;
    type: ToastEntry['type'];
}

class GlobalToastItem extends React.Component<ToastItemProps> {
    private readonly pause = (): void => { ToastManager.pause(this.props.id); };
    private readonly resume = (): void => { ToastManager.resume(this.props.id); };
    private readonly close = (): void => { ToastManager.hide(this.props.id); };

    public render() {
        const {type, message} = this.props;
        return (
            <div
                role="alert"
                aria-live="assertive"
                aria-atomic="true"
                className="global-toast-message"
                data-toast-type={type === 'danger' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'primary'}
                onMouseEnter={this.pause}
                onMouseLeave={this.resume}
            >
                <div className="global-toast-row">
                    <div className="toast-body">{message}</div>
                    <button type="button" className="global-toast-close" aria-label="Close" onClick={this.close} />
                </div>
            </div>
        );
    }
}

/** Render once in the layout; ToastManager owns the queue and timers. */
export class GlobalToastRenderer extends AntiHookComponent {
    private readonly store = new Carburetor<{entries: ToastEntry[]}>({entries: []});
    private unsubscribe: (() => void) | null = null;

    private readonly handleEntries = (entries: ToastEntry[]): void => {
        this.store.setData({entries});
    };

    public componentDidMount(): void {
        super.componentDidMount();
        this.unsubscribe = ToastManager.subscribe(this.handleEntries);
    }

    public componentWillUnmount(): void {
        this.unsubscribe?.();
        this.unsubscribe = null;
        super.componentWillUnmount();
    }

    public render() {
        const {entries} = this.useCarburetor(this.store);
        const items: React.ReactNode[] = [];
        for (const entry of entries) {
            items.push(<GlobalToastItem key={entry.id} id={entry.id} message={entry.message} type={entry.type} />);
        }
        return <div className="toast-container" data-test-id="toast-container">{items}</div>;
    }
}
