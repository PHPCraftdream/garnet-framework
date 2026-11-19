import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {showToast} from '@common/Components/Feedback/GlobalToast';

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

function filterImages(fileList: FileList | DataTransferItemList | null, multiple?: boolean): File[] {
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
}

export class ImageUploadArea extends AntiHookComponent<ImageUploadAreaProps> {
    private readonly inputRef = React.createRef<HTMLInputElement>();
    private readonly dragState = new Carburetor({dragOver: false});
    private readonly updateOnParentRender = true;

    public shouldComponentUpdate(nextProps: Readonly<ImageUploadAreaProps>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    private accept(picked: File[], hadAny: boolean): void {
        if (picked.length > 0) {
            this.props.onUpload(picked);
            return;
        }
        if (hadAny && this.props.notAnImageLabel) {
            showToast(this.props.notAnImageLabel, 'danger');
        }
    }

    private readonly handleClick = (): void => {
        this.inputRef.current?.click();
    };

    private readonly handleDragOver = (e: React.DragEvent): void => {
        e.preventDefault();
        this.dragState.setData({dragOver: true});
    };

    private readonly handleDragLeave = (): void => {
        this.dragState.setData({dragOver: false});
    };

    private readonly handleDrop = (e: React.DragEvent): void => {
        e.preventDefault();
        this.dragState.setData({dragOver: false});
        this.accept(filterImages(e.dataTransfer.files, this.props.multiple), e.dataTransfer.files.length > 0);
    };

    private readonly handleChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
        const picked = e.target.files;
        this.accept(filterImages(picked, this.props.multiple), !!picked && picked.length > 0);
        if (this.inputRef.current) this.inputRef.current.value = '';
    };

    public render() {
        const {uploading, label, multiple, hint} = this.props;
        const {dragOver} = this.useCarburetor(this.dragState);
        return (
            <div
                className="blk-upload-area"
                data-drag-state={dragOver ? 'active' : undefined}
                onClick={this.handleClick}
                onDragOver={this.handleDragOver}
                onDragLeave={this.handleDragLeave}
                onDrop={this.handleDrop}
            >
                <input ref={this.inputRef} type="file" accept="image/*" multiple={!!multiple} onChange={this.handleChange} className="image-upload-input" />
                <p className="blk-upload-hint">{uploading ? '...' : (label || '')}</p>
                {!uploading && hint && (
                    <p className="blk-upload-hint" data-test-id="image-upload-hint">{hint}</p>
                )}
            </div>
        );
    }
}
