import {ImageUploader, makeUploadImage} from '@common/Utils/Upload/UploadPriview';
import {cropperOptions} from '@common/Utils/Upload/Cropper';
import {DomEl} from '@common/Dom/DomEl';
import {eachEl} from '@common/Dom/EachEl';
import {ICropData} from '@common/Models';
import {TCropInfo} from '@common/Dom/GridTable/Models';

interface IResult {
    inputFile: DomEl<HTMLInputElement>,
    uploader: ImageUploader,
}

interface IParams {
    photoBlock: DomEl<HTMLElement>;
    onChange: (data: Blob | null, cropInfo: ICropData) => void,
    photo?: string | null;
    crop?: TCropInfo | false;
    readOnly?: boolean;
}

export const componentUploadPhotoHandler = (params: IParams): Promise<IResult> => {
    const {
        photoBlock,
        onChange,
        crop,
        photo,
        readOnly
    } = params;

    const uploadBtn = photoBlock.get<HTMLElement>('.upload-img-btn');
    const crpOpt = cropperOptions();

    if (crop === false) {
        crpOpt.autoCrop = false;
    }

    return new Promise((resolve) => {
        eachEl(
            photoBlock.get<HTMLImageElement>('.image-src'),
            photoBlock.get<HTMLElement>('.crop-block'),
            photoBlock.get<HTMLInputElement>('.input-file'),
        )?.((imgSrc, cropBlock, inputFile) => {
            const inputEl = inputFile.getEl();
            const uploader = makeUploadImage(
                inputFile,
                imgSrc,
                crpOpt,
                () => {
                    uploadBtn.toggle(false);
                    cropBlock.toggle(true);
                },
                (data: Blob | null) => {
                    const isNull = data === null;
                    uploadBtn.toggle(isNull);

                    isNull && cropBlock.toggle(false);
                    onChange(data, isNull ? null : uploader.getCropData());
                },
                // Existing photo, crop-only change: keep the stored original
                // (no new file) and just persist the updated crop rectangle.
                (cropInfo) => {
                    onChange(null, cropInfo);
                },
            );

            if (crop !== false) {
                inputFile.setDisableHandler(() => uploader.getCropper()?.disable());
                inputFile.setEnableHandler(() => uploader.getCropper()?.enable());
            }

            if (!readOnly) {
                photoBlock.get<HTMLElement>('.del-img-btn')?.then((domEl, el) => {
                    el.addEventListener('click', () => {
                        inputEl.value = null;
                        uploader.handleFileUpload();
                    });
                });

                uploadBtn?.getEl()?.addEventListener('click', () => {
                    inputEl.click();
                });

                photoBlock.get<HTMLElement>('.change-img-btn')?.then((domEl, el) => {
                    el.addEventListener('click', () => {
                        inputEl.click();
                    });
                });
            }

            if (!photo) {
                uploadBtn?.toggle(true);
            }

            if (photo) {
                uploader.selectImageData(photo).then(() => {
                    uploadBtn?.toggle(false);

                    if (crop) {
                        uploader.getCropper().setData({
                            x: crop.x || 0,
                            y: crop.y || 0,
                            width: crop.w,
                            height: crop.h,
                        });
                    }
                });
            }

            resolve({inputFile, uploader});
        });
    });
}
