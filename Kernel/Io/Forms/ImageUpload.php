<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    use Exception;
    use Gumlet\ImageResize;
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;

    class ImageUpload {
        protected int $saveFormat = IMAGETYPE_PNG;

        protected int $quality = 90;

        protected string $fileName;

        public string|null $error = null;

        public ImageResize|null $lastInfo = null;

        public function __construct(protected string|null $imgSrcPath, string $resultPrefix = '') {
            $this->fileName = $resultPrefix . '_' . uniqid();
        }

        protected function getExt(): string {
            return match ($this->saveFormat) {
                IMAGETYPE_GIF => 'gif',
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_BMP => 'bmp',
                default => 'jpg',
            };
        }

        protected function touchDir(string $dir): void {
            if (!empty($this->error)) {
                return;
            }

            if (!is_dir($dir)) {
                // Suppressed: an unwritable/invalid path (e.g. permission
                // denied) is an expected, handled failure mode here — the
                // is_dir() check right below reports it via $this->error,
                // not a warning. Without @, the raw E_WARNING escapes to
                // whatever error handler is installed (kahlan's strict
                // mode turns it into a fatal test failure; production
                // logs would get needless noise for a case we already
                // handle).
                @mkdir($dir, 0o775, true);
            }

            if (!is_dir($dir)) {
                $this->error = FwI18n::t('Common_ImgProcError') . ' #2';
            }
        }

        /**
         * @param string $destPath
         * @param int $size
         * @return string
         * @throws LoggerException
         */
        public function saveSizedToLongSide(string $destPath, int $size = 0): string {
            $this->touchDir($destPath);
            $fileName = $this->fileName . '.' . $this->getExt();

            if (!empty($this->error) || $this->imgSrcPath === null) {
                return $fileName;
            }

            try {
                $filePath = rtrim($destPath, '\\/') . DIRECTORY_SEPARATOR . $fileName;
                $img = new ImageResize($this->imgSrcPath);

                if ($size) {
                    $img->resizeToLongSide($size);
                }

                $img->save($filePath, $this->saveFormat, $this->quality);

                $this->lastInfo = $img;
            } catch (Exception $e) {
                Logger::silentGet(Logger::ERROR_LOGGER)?->write('upload_image', ErrorCatcher::getExceptionStrResult($e));
                $this->error = FwI18n::t('Common_ImgSavingError') . ' #1';
            }

            return $fileName;
        }

        /**
         * @param string $destPath
         * @param ImageCrop $crop
         * @param string $suffix
         * @return string
         * @throws LoggerException
         */
        public function saveWidthCropped(string $destPath, ImageCrop $crop, string $suffix): string {
            $this->touchDir($destPath);
            $fileName = $this->fileName . '_' . $suffix . '.' . $this->getExt();

            if (!empty($this->error)) {
                return $fileName;
            }

            try {
                $img = $this->lastInfo;

                if ($img === null) {
                    if ($this->imgSrcPath === null) {
                        return $fileName;
                    }
                    $img = new ImageResize($this->imgSrcPath);
                }

                $scale = $img->getDestHeight() / $img->getSourceHeight();

                $crop->scale($scale);
                $filePath = rtrim($destPath, '\\/') . DIRECTORY_SEPARATOR . $fileName;
                $img->freecrop($crop->w, $crop->w, $crop->x, $crop->y);
                $img->save($filePath, $this->saveFormat, $this->quality);

                $this->lastInfo = $img;
            } catch (Exception $e) {
                Logger::silentGet(Logger::ERROR_LOGGER)?->write('upload_image', ErrorCatcher::getExceptionStrResult($e));
                $this->error = FwI18n::t('Common_ImgSavingError') . ' #2';
            }

            return $fileName;
        }

        /**
         * @param ImageUploadParams $p
         * @param Updater $v
         * @param int $longSideSize
         * @return void
         * @throws LoggerException
         */
        public static function saveImage(ImageUploadParams $p, Updater $v, int $longSideSize = 0): void {
            if (!empty($v->getErrors())) {
                return;
            }

            $uploadDir = trim($p->uploadDir, '\\/') . DIRECTORY_SEPARATOR;
            /** @var callable(string): bool $removeFile */
            $removeFile = fn ($file) => is_file($delFile = $uploadDir . $file) && unlink($delFile);

            $crop = $p->cropParams;
            $imgCrop = $crop?->imgCrop;
            $prevImgCrop = $crop?->prevImgCrop;

            // remove the original and the crop
            if (empty($p->uploadTmpFile) && $crop !== null && empty($imgCrop)) {
                $v->set($p->fileNameField, null);
                $v->set($crop->fileNameField, null);
                $v->set($crop->infoField, null);

                $removeFile($p->prevFileName);
                $removeFile($crop->prevFileName);

                return;
            }

            // replace the crop
            if (empty($p->uploadTmpFile) && !empty($imgCrop) && !empty($prevImgCrop)) {
                if ($imgCrop->isEqual($prevImgCrop)) {
                    return;
                }

                $upl = new ImageUpload($uploadDir . $p->prevFileName, $p->fileNameField);
                $photoCrop = $upl->saveWidthCropped($p->uploadDir, $imgCrop, 'sq');

                if (!empty($upl->error)) {
                    $v->addError($p->fileNameField, $upl->error);

                    return;
                }

                $v->set($crop->fileNameField, $photoCrop);
                $v->set($crop->infoField, $imgCrop->json());

                $removeFile($crop->prevFileName);

                return;
            }

            // upload the original and create the crop
            if (!empty($p->uploadTmpFile)) {
                $upl = new ImageUpload($p->uploadTmpFile, $p->fileNameField);

                $photo = $upl->saveSizedToLongSide($p->uploadDir, $longSideSize);
                $v->set($p->fileNameField, $photo);

                if (!empty($upl->error)) {
                    $v->addError($p->fileNameField, $upl->error);

                    return;
                }

                if ($crop === null) {
                    return;
                }

                if (empty($imgCrop) && !empty($upl->lastInfo)) {
                    $lastInfo = $upl->lastInfo;

                    $w = $lastInfo->getDestWidth();
                    $h = $lastInfo->getDestHeight();

                    $side = min($w, $h);
                    $max = max($w, $h);
                    $padding = intval(($max - $side) / 2);

                    $imgCrop = ($w > $h)
                        ? new ImageCrop($padding, 0, $side, $side)
                        : new ImageCrop(0, $padding, $side, $side);
                }

                if ($imgCrop === null) {
                    return;
                }

                $photoCrop = $upl->saveWidthCropped($p->uploadDir, $imgCrop, 'sq');
                $v->set($crop->fileNameField, $photoCrop);
                $v->set($crop->infoField, $imgCrop->json());

                if (!empty($upl->error)) {
                    $v->addError($p->fileNameField, $upl->error);

                    return;
                }

                $removeFile($p->prevFileName);
                $removeFile($crop->prevFileName);
            }

            // remove the photo
            if (array_key_exists($p->fileNameField, $v->saveData)) {
                $saveValue = $v->saveData[$p->fileNameField];

                if ($saveValue === null || strtolower(trim($saveValue)) === 'null') {
                    $v->set($p->fileNameField, null);
                    $removeFile($p->prevFileName);

                    // remove the crop
                    if ($crop !== null) {
                        $v->set($crop->fileNameField, null);
                        $v->set($crop->infoField, null);
                        $removeFile($crop->prevFileName);
                    }
                }
            }
        }

        /**
         * @param int $quality
         */
        public function setQuality(int $quality): void {
            $this->quality = $quality;
        }

        /**
         * @param int $saveFormat
         */
        public function setSaveFormat(int $saveFormat): void {
            $this->saveFormat = $saveFormat;
        }
    }
}
