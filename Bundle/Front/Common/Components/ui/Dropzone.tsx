import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {cn} from '@common/Utils/Ui/cn';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';

/** A file that has been uploaded to pending storage (Phase 1). */
export interface UploadedFile {
    pendingId: number;
    previewUrl: string;
    name: string;
    size: number;
}

interface Props {
    onUpload: (file: UploadedFile) => void;
    onRemove: (pendingId: number) => void;
    files: UploadedFile[];
    maxFiles?: number;
    accept?: string;
    uploadUrl: string;
    removeUrl: string;
    label?: string;
    uploadingLabel?: string;
    removeLabel?: string;
    className?: string;
}

interface DropzoneState {
    isDragging: boolean;
    uploading: boolean;
    error: string | null;
}

export default class Dropzone extends AntiHookComponent<Props> {
    private readonly inputRef = React.createRef<HTMLInputElement>();
    private readonly store = new Carburetor<DropzoneState>({isDragging: false, uploading: false, error: null});

    private setStateData(update: Partial<DropzoneState>): void {
        this.store.setData({...this.store.getData(), ...update});
    }

    private canAdd(): boolean {
        return this.props.files.length < (this.props.maxFiles ?? 10);
    }

    private readonly uploadFile = async (file: File): Promise<void> => {
        if (!this.canAdd()) return;
        const {uploadUrl, onUpload} = this.props;
        this.setStateData({error: null, uploading: true});
        try {
            const fd = new FormData();
            fd.append('file', file);
            const result = await sendPostFormData<FormData, UploadedFile & {error?: string}>(uploadUrl, fd);
            if (result.error) {
                this.setStateData({error: result.error});
            } else {
                onUpload({pendingId: result.pendingId, previewUrl: result.previewUrl, name: result.name, size: result.size});
            }
        } catch (err: any) {
            this.setStateData({error: err.message || 'Upload failed'});
        } finally {
            this.setStateData({uploading: false});
        }
    };

    private readonly handleFiles = (fileList: FileList | File[]): void => {
        const remaining = (this.props.maxFiles ?? 10) - this.props.files.length;
        for (const file of Array.from(fileList).slice(0, remaining)) {
            this.uploadFile(file);
        }
    };

    private readonly handleRemove = async (event: React.MouseEvent<HTMLButtonElement>): Promise<void> => {
        event.stopPropagation();
        const pendingId = Number(event.currentTarget.value);
        const {removeUrl, onRemove} = this.props;
        try {
            const fd = new FormData();
            fd.append('pending_id', String(pendingId));
            await sendPostFormData(removeUrl, fd);
        } catch {
            // Pending files expire automatically.
        }
        onRemove(pendingId);
    };

    private readonly handleDragOver = (event: React.DragEvent): void => {
        event.preventDefault();
        event.stopPropagation();
        if (this.canAdd()) this.setStateData({isDragging: true});
    };

    private readonly handleDragLeave = (event: React.DragEvent): void => {
        event.preventDefault();
        event.stopPropagation();
        this.setStateData({isDragging: false});
    };

    private readonly handleDrop = (event: React.DragEvent): void => {
        event.preventDefault();
        event.stopPropagation();
        this.setStateData({isDragging: false});
        if (this.canAdd() && event.dataTransfer.files.length > 0) this.handleFiles(event.dataTransfer.files);
    };

    private readonly handleClick = (): void => {
        if (this.canAdd()) this.inputRef.current?.click();
    };

    private readonly handleInputChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
        if (event.target.files && event.target.files.length > 0) {
            this.handleFiles(event.target.files);
            event.target.value = '';
        }
    };

    private readonly renderFile = (file: UploadedFile) => {
        const {removeLabel = 'Remove'} = this.props;
        const size = file.size < 1024 ? file.size + 'B' : file.size < 1024 * 1024
            ? (file.size / 1024).toFixed(1) + 'KB' : (file.size / (1024 * 1024)).toFixed(1) + 'MB';
        return <div key={file.pendingId} className="dropzone-preview" data-test-id={`dropzone-preview-${file.pendingId}`}>
            <img src={file.previewUrl} alt={file.name} className="dropzone-image" />
            <button type="button" className="dropzone-remove" title={removeLabel} value={file.pendingId}
                onClick={this.handleRemove} data-test-id={`dropzone-remove-${file.pendingId}`}>×</button>
            <div className="dropzone-filename">{file.name} ({size})</div>
        </div>;
    };

    public render() {
        const {isDragging, uploading, error} = this.useCarburetor(this.store);
        const {files, maxFiles = 10, accept = 'image/jpeg,image/png,image/gif,image/webp', label,
            uploadingLabel = 'Uploading...', className} = this.props;
        const canAdd = this.canAdd();
        // carburetor-disable-next-line require-function-class
        return <div className={className ? cn('dropzone-shell', className) : 'dropzone-shell'} data-test-id="dropzone">
            <div className="dropzone-area" data-dragging={isDragging} data-uploading={uploading} data-disabled={!canAdd}
                onDragOver={this.handleDragOver} onDragLeave={this.handleDragLeave} onDrop={this.handleDrop}
                onClick={this.handleClick} data-test-id="dropzone-area">
                <input ref={this.inputRef} type="file" multiple={maxFiles > 1} accept={accept}
                    className="dropzone-input" onChange={this.handleInputChange} data-test-id="dropzone-input" />
                {uploading ? <div className="dropzone-uploading">
                    <span className="dropzone-spinner" /><span className="dropzone-label">{uploadingLabel}</span>
                </div> : <div className="dropzone-prompt">
                    <svg className="dropzone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                        <path d="M12 16V4m0 0L8 8m4-4l4 4" strokeLinecap="round" strokeLinejoin="round" />
                        <path d="M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                    <p className="dropzone-label">{label || (canAdd
                        ? `Drop files here or click to browse (${files.length}/${maxFiles})`
                        : `Maximum files reached (${maxFiles})`)}</p>
                </div>}
            </div>
            {error && <div className="dropzone-error" data-test-id="dropzone-error">{error}</div>}
            {files.length > 0 && <div className="dropzone-previews" data-test-id="dropzone-previews">{files.map(this.renderFile)}</div>}
        </div>;
    }
}
