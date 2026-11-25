import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {sendPostFormData, ApiError} from '@common/Api/Send/sendPostFormData';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import type {ConfirmState} from '@common/hooks/ui/useConfirm';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {ImageUploadArea} from './ImageUploadArea';

/** Pulls the server's own explanation out of a failed upload, if it sent one. */
function serverReason(err: unknown): string | null {
    if (!(err instanceof ApiError)) return null;

    const body = err.response;

    if (typeof body === 'string') return body.trim() === '' ? null : body;

    const reason = (body as {error?: unknown} | null)?.error;

    return typeof reason === 'string' && reason !== '' ? reason : null;
}

export interface ImageUploadFieldProps {
    /** Current image URL ('' when none). */
    value: string;
    /** Called with the new URL after upload, or '' after removal. */
    onChange: (url: string) => void;
    /** Endpoint accepting multipart `file`, returns {success, url}. */
    uploadUrl: string;
    /** Endpoint accepting JSON {url}, removes the stored file. */
    deleteUrl: string;
    uploadLabel?: string;
    removeLabel?: string;
    /** Confirm prompt before deleting; omit to delete without confirmation. */
    removeConfirm?: string;
    errorLabel?: string;
    previewAlt?: string;
    disabled?: boolean;
    /** Limits, stated before a file is chosen rather than in the refusal. */
    hint?: string;
    /** Shown when the picked file is not an image at all. */
    notAnImageLabel?: string;
}

type FieldState = {uploading: boolean; confirmState: ConfirmState};

export class ImageUploadField extends AntiHookComponent<ImageUploadFieldProps> {
    private readonly fieldStore = new Carburetor<FieldState>({
        uploading: false,
        confirmState: {visible: false, message: '', items: []},
    });
    private confirmResolver: ((value: boolean) => void) | null = null;
    private readonly updateOnParentRender = true;

    /** Re-render when parent-provided values change in place. */
    public shouldComponentUpdate(nextProps: Readonly<ImageUploadFieldProps>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    /** Forward picked images to the upload routine. */
    private readonly handleUpload = (files: File[]): void => {
        void this.upload(files);
    };

    /** Upload the first image and update the field value on success. */
    private async upload(files: readonly File[]): Promise<void> {
        const {uploadUrl, onChange, errorLabel} = this.props;
        const file = files[0];
        if (!file) return;
        this.fieldStore.setData({...this.fieldStore.getData(), uploading: true});
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadUrl, fd);
            if (res?.url) onChange(res.url);
        } catch (err: unknown) {
            showToast(serverReason(err) ?? errorLabel ?? 'Error', 'danger');
        } finally {
            this.fieldStore.setData({...this.fieldStore.getData(), uploading: false});
        }
    }

    /** Show a confirmation prompt and resolve with the user's choice. */
    private confirm(message: string, confirmLabel?: string): Promise<boolean> {
        return new Promise(resolve => {
            this.confirmResolver = resolve;
            this.fieldStore.setData({
                ...this.fieldStore.getData(),
                confirmState: {visible: true, message, items: [], confirmLabel, variant: 'danger'},
            });
        });
    }

    /** Hide the confirmation and resolve its pending request. */
    private settleConfirm(confirmed: boolean): void {
        const data = this.fieldStore.getData();
        this.fieldStore.setData({...data, confirmState: {...data.confirmState, visible: false}});
        this.confirmResolver?.(confirmed);
        this.confirmResolver = null;
    }

    /** Resolve the pending confirmation as accepted. */
    private readonly handleConfirm = (): void => { this.settleConfirm(true); };
    /** Resolve the pending confirmation as canceled. */
    private readonly handleCancel = (): void => { this.settleConfirm(false); };
    /** Start removing the selected image. */
    private readonly handleRemove = (): void => { void this.remove(); };

    /** Confirm and remove the current image, then clear the field value. */
    private async remove(): Promise<void> {
        const {removeConfirm, removeLabel, value, deleteUrl, onChange} = this.props;
        if (removeConfirm) {
            const ok = await this.confirm(removeConfirm, removeLabel);
            if (!ok) return;
        }
        if (value) { try { await sendPost(deleteUrl, {url: value}); } catch { /* silent */ } }
        onChange('');
    }

    /** Render the image preview or picker and its confirmation dialog. */
    public render() {
        const {value} = this.props;
        const {uploading, confirmState} = this.useCarburetor(this.fieldStore);
        const modalState: ConfirmState = {
            visible: confirmState.visible,
            message: confirmState.message,
            items: [...confirmState.items],
            confirmLabel: confirmState.confirmLabel,
            variant: confirmState.variant,
        };
        return (
            <div>
                {value ? this.renderPreview(value) : this.renderPicker(uploading)}
                <ConfirmModal state={modalState} onConfirm={this.handleConfirm} onCancel={this.handleCancel} />
            </div>
        );
    }

    /** Render the current image and its remove control. */
    private renderPreview(value: string) {
        const {previewAlt, removeLabel, disabled} = this.props;
        return (
            <div className="blk-img-preview">
                <img src={value} alt={previewAlt || ''} className="blk-img-preview-img" />
                <button
                    type="button"
                    className="blk-img-preview-remove"
                    onClick={this.handleRemove}
                    disabled={disabled}
                    title={removeLabel}
                >
                    &#215;
                </button>
            </div>
        );
    }

    /** Render the image picker with its configured labels and guidance. */
    private renderPicker(uploading: boolean) {
        const {uploadLabel, hint, notAnImageLabel} = this.props;
        return (
            <ImageUploadArea
                onUpload={this.handleUpload}
                uploading={uploading}
                label={uploadLabel}
                hint={hint}
                notAnImageLabel={notAnImageLabel}
            />
        );
    }
}
