import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {useBodyScrollLock} from '@common/hooks/ui/internal/useBodyScrollLock';
import {ConfirmState} from '@common/hooks/ui/useConfirm';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {Portal} from '@common/Components/Layout/Portal';

interface Props {
    state: ConfirmState;
    onConfirm: () => void;
    onCancel: () => void;
    confirmLabel?: string;
    cancelLabel?: string;
}

/** Preserve each item while adding stable keys for duplicate values. */
function renderItems(items: readonly string[]): React.ReactNode[] {
    const counts = new Map<string, number>();
    const result: React.ReactNode[] = [];
    for (const item of items) {
        const count = counts.get(item) ?? 0;
        counts.set(item, count + 1);
        result.push(<li key={JSON.stringify([item, count])} className="confirm-modal-item">{item}</li>);
    }
    return result;
}

class ConfirmModalView extends AntiHookComponent<Props> {
    /** Cancel when the backdrop itself is clicked. */
    private readonly handleBackdrop = (e: React.MouseEvent): void => {
        if (e.target === e.currentTarget) this.props.onCancel();
    };

    /** Render the dialog only while confirmation is visible. */
    public render() {
        return this.props.state.visible ? this.renderDialog() : null;
    }

    /** Render the confirmation dialog through the portal. */
    private renderDialog() {
        const {state, onConfirm, onCancel, confirmLabel, cancelLabel} = this.props;
        const label = state.confirmLabel || confirmLabel || I18nFramework.Common_OK();

        return (
        <Portal>
            <div
                className="confirm-modal-overlay"
                id="confirmModal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirmModalBody"
                onClick={this.handleBackdrop}
            >
                <div className="confirm-modal-dialog">
                    <div className="confirm-modal-content">
                        <div className="confirm-modal-body" id="confirmModalBody">
                            <p>{state.message}</p>
                            {state.items.length > 0 && (
                                <ul className="confirm-modal-list">
                                    {renderItems(state.items)}
                                </ul>
                            )}
                        </div>
                        <div className="confirm-modal-footer">
                            <button
                                type="button"
                                className="confirm-modal-cancel"
                                onClick={onCancel}
                                data-test-id="modal-cancel-btn"
                            >
                                {cancelLabel || I18nFramework.Common_Cancel()}
                            </button>
                            <button
                                type="button"
                                className={
                                    state.variant === 'danger'
                                        ? 'confirm-modal-danger'
                                        : 'confirm-modal-success'
                                }
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
    }
}

/** Render the confirmation modal and lock body scrolling while it is open. */
export function ConfirmModal(props: Props) {
    useBodyScrollLock(props.state.visible);
    return <ConfirmModalView {...props} />;
}
