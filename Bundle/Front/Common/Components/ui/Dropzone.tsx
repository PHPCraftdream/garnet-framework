import * as React from 'react';
import {useRef, useState, useCallback} from 'react';
import {cn} from '@common/Utils/cn';
import {sendPostFormData} from '@common/Api/sendPostFormData';

/** A file that has been uploaded to pending storage (Phase 1). */
export interface UploadedFile {
    pendingId: number;
    previewUrl: string;
    name: string;
    size: number;
}

interface Props {
    /** Called when a new file is uploaded. */
    onUpload: (file: UploadedFile) => void;
    /** Called when a file is removed. */
    onRemove: (pendingId: number) => void;
    /** Currently uploaded files (controlled from parent). */
    files: UploadedFile[];
    /** Max number of files allowed. */
    maxFiles?: number;
    /** MIME type filter for the file input. Default: images. */
    accept?: string;
    /** URL for the upload endpoint. */
    uploadUrl: string;
    /** URL for removing a pending upload. */
    removeUrl?: string;
    /** Label text shown in the drop area. */
    label?: string;
    /** Label shown while uploading (default: "Uploading..."). */
    uploadingLabel?: string;
    /** Title for the remove button (default: "Remove"). */
    removeLabel?: string;
    /** Additional CSS class for the container. */
    className?: string;
}

export default function Dropzone({
    onUpload,
    onRemove,
    files,
    maxFiles = 10,
    accept = 'image/jpeg,image/png,image/gif,image/webp',
    uploadUrl,
    removeUrl = '/expert/~removePending',
    label,
    uploadingLabel = 'Uploading...',
    removeLabel = 'Remove',
    className,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const canAdd = files.length < maxFiles;

    const uploadFile = useCallback(async (file: File) => {
        if (!canAdd) return;
        setError(null);
        setUploading(true);

        try {
            const fd = new FormData();
            fd.append('file', file);

            const result = await sendPostFormData<FormData, {
                pendingId: number;
                previewUrl: string;
                name: string;
                size: number;
                error?: string;
            }>(uploadUrl, fd);

            if (result.error) {
                setError(result.error);
            } else {
                onUpload({
                    pendingId: result.pendingId,
                    previewUrl: result.previewUrl,
                    name: result.name,
                    size: result.size,
                });
            }
        } catch (err: any) {
            setError(err.message || 'Upload failed');
        } finally {
            setUploading(false);
        }
    }, [canAdd, uploadUrl, onUpload]);

    const handleFiles = useCallback((fileList: FileList | File[]) => {
        const arr = Array.from(fileList);
        const remaining = maxFiles - files.length;
        const toUpload = arr.slice(0, remaining);

        for (const file of toUpload) {
            uploadFile(file);
        }
    }, [maxFiles, files.length, uploadFile]);

    const handleRemove = useCallback(async (pendingId: number) => {
        try {
            const fd = new FormData();
            fd.append('pending_id', String(pendingId));
            await sendPostFormData(removeUrl, fd);
        } catch {
            // Best effort — file will be cleaned up by expiry
        }
        onRemove(pendingId);
    }, [removeUrl, onRemove]);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (canAdd) setIsDragging(true);
    }, [canAdd]);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        if (canAdd && e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files);
        }
    }, [canAdd, handleFiles]);

    const handleClick = useCallback(() => {
        if (canAdd) inputRef.current?.click();
    }, [canAdd]);

    const handleInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            handleFiles(e.target.files);
            e.target.value = '';
        }
    }, [handleFiles]);

    const formatSize = (bytes: number): string => {
        if (bytes < 1024) return bytes + 'B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB';
        return (bytes / (1024 * 1024)).toFixed(1) + 'MB';
    };

    return (
        <div className={cn('space-y-2', className)} data-test-id="dropzone">
            {/* Drop area */}
            <div
                className={cn(
                    'border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-colors',
                    'border-default text-muted',
                    isDragging && 'border-accent bg-accent-subtle',
                    uploading && 'opacity-60 pointer-events-none',
                    !canAdd && 'opacity-40 pointer-events-none',
                )}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                onClick={handleClick}
                data-test-id="dropzone-area"
            >
                <input
                    ref={inputRef}
                    type="file"
                    multiple={maxFiles > 1}
                    accept={accept}
                    className="hidden"
                    onChange={handleInputChange}
                    data-test-id="dropzone-input"
                />

                {uploading ? (
                    <div className="flex items-center justify-center gap-2 py-2">
                        <span className="animate-spin inline-block w-5 h-5 border-2 border-current border-t-transparent rounded-full" />
                        <span className="text-sm">{uploadingLabel}</span>
                    </div>
                ) : (
                    <div className="py-2">
                        <svg className="mx-auto mb-1 w-8 h-8 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                            <path d="M12 16V4m0 0L8 8m4-4l4 4" strokeLinecap="round" strokeLinejoin="round" />
                            <path d="M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        <p className="text-sm">
                            {label || (canAdd
                                ? `Drop files here or click to browse (${files.length}/${maxFiles})`
                                : `Maximum files reached (${maxFiles})`
                            )}
                        </p>
                    </div>
                )}
            </div>

            {/* Error message */}
            {error && (
                <div className="text-sm text-danger" data-test-id="dropzone-error">
                    {error}
                </div>
            )}

            {/* Thumbnails grid */}
            {files.length > 0 && (
                <div className="flex flex-wrap gap-2" data-test-id="dropzone-previews">
                    {files.map((f) => (
                        <div
                            key={f.pendingId}
                            className="relative group border border-default rounded overflow-hidden"
                            style={{width: 80, height: 80}}
                            data-test-id={`dropzone-preview-${f.pendingId}`}
                        >
                            <img
                                src={f.previewUrl}
                                alt={f.name}
                                className="w-full h-full object-cover"
                            />
                            <button
                                type="button"
                                className="absolute top-0 right-0 bg-danger-subtle text-danger rounded-bl text-xs px-1.5 py-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
                                title={removeLabel}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleRemove(f.pendingId);
                                }}
                                data-test-id={`dropzone-remove-${f.pendingId}`}
                            >
                                ×
                            </button>
                            <div className="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs px-1 py-0.5 truncate opacity-0 group-hover:opacity-100 transition-opacity">
                                {f.name} ({formatSize(f.size)})
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
