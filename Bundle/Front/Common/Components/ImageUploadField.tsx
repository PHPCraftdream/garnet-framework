import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {sendPostFormData, ApiError} from '@common/Api/sendPostFormData';
import {showToast} from '@common/Components/GlobalToast';
import {useConfirm} from '@common/hooks/useConfirm';
import {ConfirmModal} from '@common/Components/ConfirmModal';

// ── Drag-and-drop / click upload zone (reusable) ──

export interface ImageUploadAreaProps {
    onUpload: (files: File[]) => void;
    uploading: boolean;
    label?: string;
    multiple?: boolean;
    /** Limits, stated before a file is chosen rather than in the refusal. */
    hint?: string;
    /** Shown when the picked file is not an image at all. */
    notAnImageLabel?: string;
}

export const ImageUploadArea: React.FC<ImageUploadAreaProps> = ({onUpload, uploading, label, multiple, hint, notAnImageLabel}) => {
    const inputRef = React.useRef<HTMLInputElement>(null);
    const [dragOver, setDragOver] = React.useState(false);

    const filterImages = (fileList: FileList | DataTransferItemList | null): File[] => {
        if (!fileList) return [];
        const files: File[] = [];
        for (let i = 0; i < fileList.length; i++) {
            const f = fileList instanceof DataTransferItemList ? (fileList[i] as any) : fileList[i];
            if (f instanceof File && f.type.startsWith('image/')) {
                files.push(f);
                if (!multiple) break;
            }
        }
        return files;
    };

    /**
     * Picking a non-image used to do nothing at all — the filter dropped it and
     * the screen never moved, which reads as a broken button rather than a
     * refusal.
     */
    const accept = (picked: File[], hadAny: boolean) => {
        if (picked.length > 0) {
            onUpload(picked);

            return;
        }

        if (hadAny && notAnImageLabel) {
            showToast(notAnImageLabel, 'danger');
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        accept(filterImages(e.dataTransfer.files), e.dataTransfer.files.length > 0);
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const picked = e.target.files;
        accept(filterImages(picked), !!picked && picked.length > 0);
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <div
            className={`blk-upload-area ${dragOver ? 'blk-upload-area-active' : ''}`}
            onClick={() => inputRef.current?.click()}
            onDragOver={e => { e.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={handleDrop}
        >
            <input ref={inputRef} type="file" accept="image/*" multiple={!!multiple} onChange={handleChange} className="hidden" />
            <p className="blk-upload-hint">{uploading ? '...' : (label || '')}</p>
            {!uploading && hint && (
                <p className="blk-upload-hint text-xs text-muted" data-test-id="image-upload-hint">{hint}</p>
            )}
        </div>
    );
};

/** Pulls the server's own explanation out of a failed upload, if it sent one. */
function serverReason(err: unknown): string | null {
    if (!(err instanceof ApiError)) return null;

    const body = err.response;

    if (typeof body === 'string') return body.trim() === '' ? null : body;

    const reason = (body as {error?: unknown} | null)?.error;

    return typeof reason === 'string' && reason !== '' ? reason : null;
}

// ── Single-image field: preview + remove, or an upload zone when empty ──

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

export const ImageUploadField: React.FC<ImageUploadFieldProps> = ({
    value, onChange, uploadUrl, deleteUrl,
    uploadLabel, removeLabel, removeConfirm, errorLabel, previewAlt, disabled,
    hint, notAnImageLabel,
}) => {
    const [uploading, setUploading] = React.useState(false);
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    const handleUpload = async (files: File[]) => {
        const file = files[0];
        if (!file) return;
        setUploading(true);
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadUrl, fd);
            if (res?.url) onChange(res.url);
        } catch (err: unknown) {
            // The server says why — too large, not an image — and this used to
            // replace that with a bare "Error". The reason existed; the display
            // threw it away.
            showToast(serverReason(err) ?? errorLabel ?? 'Error', 'danger');
        }
        finally { setUploading(false); }
    };

    const handleRemove = async () => {
        if (removeConfirm) {
            const ok = await confirm(removeConfirm, {variant: 'danger', confirmLabel: removeLabel});
            if (!ok) return;
        }
        const url = value;
        // Only our own uploads live under the upload path; external URLs are
        // rejected server-side — clear them client-side regardless.
        if (url) { try { await sendPost(deleteUrl, {url}); } catch { /* silent */ } }
        onChange('');
    };

    return (
        <div>
            {value ? (
                <div className="blk-img-preview">
                    <img src={value} alt={previewAlt || ''} className="blk-img-preview-img" />
                    <button
                        type="button"
                        className="blk-img-preview-remove"
                        onClick={() => void handleRemove()}
                        disabled={disabled}
                        title={removeLabel}
                    >
                        &#215;
                    </button>
                </div>
            ) : (
                    <ImageUploadArea
                    onUpload={files => void handleUpload(files)}
                    uploading={uploading}
                    label={uploadLabel}
                    hint={hint}
                    notAnImageLabel={notAnImageLabel}
                />
            )}
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </div>
    );
};
