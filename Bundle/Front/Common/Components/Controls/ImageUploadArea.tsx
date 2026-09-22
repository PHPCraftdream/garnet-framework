import * as React from 'react';
import {showToast} from '@common/Components/Feedback/GlobalToast';

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
