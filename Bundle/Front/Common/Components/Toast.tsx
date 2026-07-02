import * as React from 'react';
import {ToastControls} from '@common/hooks/useToast';

interface Props {
    ctrl: ToastControls;
}

const typeClasses: Record<string, string> = {
    primary: 'text-bg-primary',
    success: 'text-bg-success',
    danger: 'text-bg-danger',
    warning: 'text-bg-warning',
};

export const Toast: React.FC<Props> = ({ctrl}) => (
    <div className="toast-container">
        <div
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            className={`toast ${ctrl.toast.visible ? 'show' : ''} ${typeClasses[ctrl.toast.type] || 'text-bg-primary'}`}
            onMouseEnter={ctrl.pause}
            onMouseLeave={ctrl.resume}
        >
            <div className="flex items-center">
                <div className="toast-body">{ctrl.toast.message}</div>
                <button
                    type="button"
                    className="btn-close btn-close-white mr-2 ml-auto"
                    aria-label="Close"
                    onClick={ctrl.hideToast}
                />
            </div>
        </div>
    </div>
);
