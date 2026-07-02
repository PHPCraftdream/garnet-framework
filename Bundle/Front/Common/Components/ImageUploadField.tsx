import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {sendPostFormData} from '@common/Api/sendPostFormData';
import {showToast} from '@common/Components/GlobalToast';
import {useConfirm} from '@common/hooks/useConfirm';
import {ConfirmModal} from '@common/Components/ConfirmModal';

// ── Drag-and-drop / click upload zone (reusable) ──

export interface ImageUploadAreaProps {
    onUpload: (files: File[]) => void;
    uploading: boolean;
    label?: string;
    multiple?: boolean;
}

export const ImageUploadArea: React.FC<ImageUploadAreaProps> = ({onUpload, uploading, label, multiple}) => {
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

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        const files = filterImages(e.dataTransfer.files);
        if (files.length > 0) onUpload(files);
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = filterImages(e.target.files);
        if (files.length > 0) onUpload(files);
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
        </div>
    );
};

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
}

export const ImageUploadField: React.FC<ImageUploadFieldProps> = ({
    value, onChange, uploadUrl, deleteUrl,
    uploadLabel, removeLabel, removeConfirm, errorLabel, previewAlt, disabled,
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
        } catch { showToast(errorLabel || 'Error', 'danger'); }
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
                <ImageUploadArea onUpload={files => void handleUpload(files)} uploading={uploading} label={uploadLabel} />
            )}
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </div>
    );
};
