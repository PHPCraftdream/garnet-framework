import {ICropData, TCropHandler, TSelectImageHandler} from '@common/Support/Models';
import {DomEl} from '@common/Dom/El/DomEl';
import {ImageUploader} from '@common/Utils/Upload/UploadPriview';

/** Creates an image uploader for a file input and preview image. */
export const makeUploadImage = (
    fileInput: DomEl<HTMLInputElement>,
    previewImage: DomEl<HTMLImageElement>,
    cropperOptions: Record<string, number | boolean | string>,
    onSelect: TSelectImageHandler,
    onChange: TCropHandler,
    onCropChange?: (cropData: ICropData) => void,
): ImageUploader => {
    return new ImageUploader(fileInput, previewImage, cropperOptions, onSelect, onChange, onCropChange);
};
