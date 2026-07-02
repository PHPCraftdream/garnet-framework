import {ICropData, TCropHandler, TSelectImageHandler} from '@common/Models';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.min.css';
import {DomEl} from '@common/Dom/DomEl';
import {resolveTimeout} from '@common/Utils/ResolveTimeout';

export class ImageUploader {
    protected cropper: Cropper | null = null;
    protected lastCrop: number = 0;
    protected cropData: ICropData;
    // The ORIGINAL file the user just picked (null when editing an existing
    // photo's crop). We upload the original — never the cropped canvas — so the
    // server keeps the full image and the crop frame can be rebuilt on re-edit.
    protected selectedFile: File | null = null;

    constructor(
        protected fileInput: DomEl<HTMLInputElement>,
        protected previewImage: DomEl<HTMLImageElement>,
        protected cropperOptions: Record<string, number | boolean | string>,
        protected onSelect: TSelectImageHandler,
        protected onChange: TCropHandler,
        protected onCropChange?: (cropData: ICropData) => void,
    ) {
        fileInput.getEl()?.addEventListener('input', this.handleFileUpload, {capture: true});
    }

    protected changeBlob = (data: Blob | null): void => {
        if  (data) {
            this.fileInput.clearErrors();
        }

        this.onChange(data, this.cropData);
    }

    protected handleSaveCrop = () => {
        if (this.selectedFile) {
            // New upload: send the ORIGINAL file (+ crop rectangle), not the crop.
            this.changeBlob(this.selectedFile);
        } else {
            // Existing photo: only the crop rectangle changed — keep the stored
            // original on the server and just persist the new crop info.
            this.onCropChange?.(this.cropData);
        }
    };

    protected handleCropEvent = (cropParams: Cropper.CropEvent) => {
        if (this.lastCrop) {
            clearTimeout(this.lastCrop);
        }

        const details = cropParams.detail;

        this.cropData = {
            x: Math.round(details.x),
            y: Math.round(details.y),
            width: Math.round(details.width),
            height: Math.round(details.height),
        };

        this.lastCrop = setTimeout(this.handleSaveCrop, 400) as unknown as number;
    };

    public selectImageData = (imageData: string | null): Promise<unknown> => {
        const timeout = 150;

        if (this.cropper) {
            this.cropper?.replace(imageData);
            this.onSelect(imageData);

            return resolveTimeout<void>(timeout);
        }

        this.cropper = new Cropper(this.previewImage.getEl(), {
            ...this.cropperOptions,
            crop: this.handleCropEvent,
            checkCrossOrigin: false,
            checkOrientation: false,
        });

        if (imageData) {
            this.cropper?.replace(imageData);
            this.onSelect(imageData);
        }

        return resolveTimeout<void>(timeout);
    }

    public handleFileUpload = (): void => {
        const inputElement = this.fileInput.getEl();
        const file = inputElement.files?.[0];

        if (!file || !file.type.startsWith('image/')) {
            this.selectedFile = null;
            this.onChange(null, null);

            return;
        }

        this.selectedFile = file;

        const reader = new FileReader();

        reader.onload = (event: ProgressEvent<FileReader>) => {
            const img = this.previewImage.getEl();

            img.src = event.target?.result as string;
            this.selectImageData(img.src);
        };

        reader.readAsDataURL(file);
    }

    public getCropper = (): Cropper => {
        return this.cropper;
    }

    public getCropData = (): ICropData => {
        return this.cropData;
    }
}

export const makeUploadImage = (
    fileInput: DomEl<HTMLInputElement>,
    previewImage: DomEl<HTMLImageElement>,
    cropperOptions: Record<string, number | boolean | string>,
    onSelect: TSelectImageHandler,
    onChange: TCropHandler,
    onCropChange?: (cropData: ICropData) => void,
): ImageUploader => {
    return new ImageUploader(fileInput, previewImage, cropperOptions, onSelect, onChange, onCropChange);
}
