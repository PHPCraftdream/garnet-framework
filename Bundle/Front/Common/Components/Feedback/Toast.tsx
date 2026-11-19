import * as React from 'react';
import {ToastControls} from '@common/hooks/ui/useToast';

interface Props {
    ctrl: ToastControls;
}

export const Toast: React.FC<Props> = ({ctrl}) => (
    <div className="toast-container">
        <div
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            className={ctrl.toast.visible ? 'global-toast-message' : 'local-toast-hidden'}
            data-toast-type={ctrl.toast.type === 'danger' ? 'danger' : ctrl.toast.type === 'success' ? 'success' : ctrl.toast.type === 'warning' ? 'warning' : 'primary'}
            onMouseEnter={ctrl.pause}
            onMouseLeave={ctrl.resume}
        >
            <div className="global-toast-row">
                <div className="toast-body">{ctrl.toast.message}</div>
                <button
                    type="button"
                    className="global-toast-close"
                    aria-label="Close"
                    onClick={ctrl.hideToast}
                />
            </div>
        </div>
    </div>
);
