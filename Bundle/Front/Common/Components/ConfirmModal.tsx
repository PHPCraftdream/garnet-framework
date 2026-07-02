import * as React from 'react';
import {useBodyScrollLock} from '@common/hooks/useBodyScrollLock';
import {ConfirmState} from '@common/hooks/useConfirm';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {Portal} from '@common/Components/Portal';

interface Props {
    state: ConfirmState;
    onConfirm: () => void;
    onCancel: () => void;
    confirmLabel?: string;
    cancelLabel?: string;
}

const variantClass: Record<string, string> = {
    success: 'btn btn-success',
    danger: 'btn btn-danger',
};

export const ConfirmModal: React.FC<Props> = ({state, onConfirm, onCancel, confirmLabel, cancelLabel}) => {
    useBodyScrollLock(state.visible);
    if (!state.visible) return null;

    const handleBackdrop = (e: React.MouseEvent) => {
        if (e.target === e.currentTarget) onCancel();
    };

    // Per-invocation values from state take priority over static props
    const label = state.confirmLabel || confirmLabel || I18nFramework.Common_OK();
    const btnClass = variantClass[state.variant ?? ''] || 'btn btn-success';

    return (
        <Portal>
            <div className="modal show" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmModalBody" onClick={handleBackdrop}>
                <div className="modal-dialog modal-dialog-centered">
                    <div className="modal-content">
                        <div className="modal-body fs-5" id="confirmModalBody">
                            <p>{state.message}</p>
                            {state.items.length > 0 && (
                                <ul className="list-group list-group-flush mb-2">
                                    {state.items.map((item, i) => (
                                        <li key={i} className="list-group-item py-1 px-0">{item}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <div className="modal-footer">
                            <button
                                type="button"
                                className="btn btn-secondary"
                                onClick={onCancel}
                                data-test-id="modal-cancel-btn"
                            >
                                {cancelLabel || I18nFramework.Common_Cancel()}
                            </button>
                            <button
                                type="button"
                                className={btnClass}
                                id="confirmModalOk"
                                onClick={onConfirm}
                                data-test-id="modal-confirm-btn"
                            >
                                {label}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Portal>
    );
};
