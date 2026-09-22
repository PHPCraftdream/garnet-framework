import * as React from 'react';
import {ToastEntry, ToastManager} from './toastManager';

const typeClasses: Record<string, string> = {
    primary: 'text-bg-primary',
    success: 'text-bg-success',
    danger: 'text-bg-danger',
    warning: 'text-bg-warning',
};

/** Render ONCE in the layout — subscribes to ToastManager */
export const GlobalToastRenderer: React.FC = () => {
    const [entries, setEntries] = React.useState<ToastEntry[]>([]);

    React.useEffect(() => ToastManager.subscribe(setEntries), []);

    return (
        <div className="toast-container" data-test-id="toast-container">
            {entries.map(entry => (
                <div
                    key={entry.id}
                    role="alert"
                    aria-live="assertive"
                    aria-atomic="true"
                    className={`toast show ${typeClasses[entry.type] || 'text-bg-primary'}`}
                    onMouseEnter={() => ToastManager.pause(entry.id)}
                    onMouseLeave={() => ToastManager.resume(entry.id)}
                >
                    <div className="flex items-center">
                        <div className="toast-body">{entry.message}</div>
                        <button
                            type="button"
                            className="btn-close btn-close-white mr-2 ml-auto"
                            aria-label="Close"
                            onClick={() => ToastManager.hide(entry.id)}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
};
