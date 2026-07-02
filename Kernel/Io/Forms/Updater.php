<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\FsTools;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;

    class Updater {
        public array $errors = [];

        public array $commonErrors = [];

        public array $resultData = [];

        public function __construct(public array $saveData, public array $files = []) {
        }

        public function getErrors(): array {
            $result = [];

            if (!empty($this->errors)) {
                $result['errors'] = $this->errors;
            }

            if (!empty($this->commonErrors)) {
                $result['commonErrors'] = $this->commonErrors;
            }

            return $result;
        }

        public function hasErrors(): bool {
            return !empty($this->errors) || !empty($this->commonErrors);
        }

        public function addError(string $name, string $value): void {
            if (empty($this->errors[$name])) {
                $this->errors[$name] = [$value];

                return;
            }

            if (is_array($this->errors[$name])) {
                $this->errors[$name][] = $value;
            }
        }

        public function set(string $name, mixed $value): void {
            $this->resultData[$name] = $value;
        }

        protected static function parseStringValidator(string $v): array|false {
            $result = null;
            $res = preg_match_all('#^(\w+)(\[(.+?)])?$#', $v, $result);

            if (!$res) {
                return false;
            }

            /* @phpstan-ignore-next-line */
            if (empty($result) || empty($result[1][0])) {
                return false;
            }

            $name = $result[1][0];
            $args = empty($result[3][0]) ? [] : explode(',', $result[3][0]);

            return [$name, $args];
        }

        protected function parseValidator(callable|array|string $v, array $argsAdd = []): array {
            $obj = $this;
            $call = $v;

            if (is_array($call) && !empty($call[0]) && !empty($call[1])) {
                $obj = $call[0];
                $call = $call[1];
            }

            if (is_string($call)) {
                $res = static::parseStringValidator($call);

                if (!$res) {
                    return [$call, $argsAdd];
                }

                [$name, $args] = $res;

                return [[$obj, $name], [...$args, ...$argsAdd]];
            }

            return [[$obj, $call], $argsAdd];
        }

        public function validateByFieldsInfo(array $fieldsInfo, callable $filter = null): void {
            foreach ($fieldsInfo as $name => $info) {
                $data = $this->saveData[$name] ?? null;

                if (empty($info)) {
                    continue;
                }

                if (!empty($filter) && !$filter($info)) {
                    continue;
                }

                if (($info['type'] ?? '') === 'photo') {
                    continue;
                }

                $validation = $info['validation'] ?? null;
                $readOnly = $info['readOnly'] ?? null;

                if ($readOnly) {
                    continue;
                }

                if (!empty($validation) && is_array($validation)) {
                    foreach ($validation as $rule) {
                        $this->v($name, $rule);
                    }
                } else {
                    $this->resultData[$name] = $data;
                }
            }
        }

        public function processUploadPhoto(array $fieldsInfo, array $prevObj, string $uploadBaseDir): void {
            foreach ($fieldsInfo as $name => $info) {
                if (($info['type'] ?? '') !== 'photo') {
                    continue;
                }

                $cropInfoField = $info['cropInfo'] ?? null;
                $cropField = $info['cropName'] ?? null;
                $uploadPathAdd = $info['uploadPath'] ?? throw new CommonException('Empty uploadPathAdd');

                $uploadPathAdd = preg_replace_callback(
                    '#({(\w+)})#',
                    fn ($matches) => $prevObj[$matches[2]] ?? throw new CommonException('Unknown upload param: ' . $matches[2]),
                    $uploadPathAdd
                );

                $imgCrop = null;

                if (!empty($this->saveData[$cropInfoField]) && !empty($cropField)) {
                    $crop = $this->saveData[$cropInfoField] ?? null;
                    $imgCrop = is_array($crop) ? ImageCrop::fromPost($crop) : null;
                }

                $prevCropInfo = $prevObj[$cropInfoField] ?? null;

                if (is_string($prevCropInfo)) {
                    $prevCropInfo = StrTools::jsonRead($prevCropInfo);
                }

                $prevImgCrop = empty($prevCropInfo) ? null : ImageCrop::fromPost([
                    'x' => $prevCropInfo['x'] ?? 0,
                    'y' => $prevCropInfo['y'] ?? 0,
                    'width' => $prevCropInfo['w'] ?? 0,
                    'height' => $prevCropInfo['h'] ?? 0,
                ]);

                $cropParams = empty($cropField) || empty($cropInfoField) ? null : new ImageCropParams(
                    fileNameField: $cropField,
                    infoField: $cropInfoField,
                    imgCrop: $imgCrop,
                    prevImgCrop: $prevImgCrop,
                    prevFileName: $prevObj[$cropField] ?? null,
                );

                $file = $this->files[$name] ?? null;
                $uploadParams = new ImageUploadParams(
                    uploadDir: FsTools::makeDirPath([$uploadBaseDir, $uploadPathAdd]),
                    fileNameField: $name,
                    uploadTmpFile: empty($file['tmp_name']) ? null : $file['tmp_name'],
                    prevFileName: $prevObj[$name] ?? null,
                    cropParams: $cropParams,
                );

                ImageUpload::saveImage($uploadParams, $this, 900);
            }
        }

        public function v(string $name, callable|array|string $validator, mixed ...$args): void {
            [$call, $newArgs] = $this->parseValidator($validator, $args);

            $value = array_key_exists($name, $this->saveData) ? $this->saveData[$name] : null;
            $result = $call($value, ...$newArgs);

            if ($result === true) {
                $this->resultData[$name] = $value;

                return;
            }

            if (is_string($result)) {
                if (empty($this->errors[$name])) {
                    $this->errors[$name] = [];
                }

                $this->errors[$name][] = $result;

                return;
            }

            $this->errors[$name][] = FwI18n::t('Common_IncorrectValue');
        }

        public function required(string|null $value): bool|string {
            if (empty($value)) {
                return FwI18n::t('Common_RequiredValue');
            }

            return true;
        }

        public function minLen(string|null $value, int|string $len): bool|string {
            if (mb_strlen($value . '') < intval($len)) {
                return FwI18n::t('Common_MinLength', [$len]);
            }

            return true;
        }

        public function maxLen(string|null $value, int|string $len): bool|string {
            if (mb_strlen($value . '') > intval($len)) {
                return FwI18n::t('Common_MaxLength', [$len]);
            }

            return true;
        }

        public function len(string|null $value, int|string $minLen, int|string $maxLen): bool|string {
            $len = mb_strlen($value . '');

            if ($len > intval($maxLen) || $len < intval($minLen)) {
                return FwI18n::t('Common_Len', [$minLen, $maxLen]);
            }

            return true;
        }

        public function in_array(string|null $value, mixed ...$values): bool|string {
            if (in_array($value, $values, true)) {
                return true;
            }

            return FwI18n::t('Common_IncorrectValue');
        }

        public function nameSymbols(string|null $value): bool|string {
            if ($value !== null && !preg_match('/^[\p{L} ,]+$/u', $value)) {
                return FwI18n::t('Common_IncorrectValue');
            }

            return true;
        }

        public function simpleText(string|null $value): bool|string {
            if (!preg_match('/^[\p{L}\p{P}\p{So} -~]*$/u', $value . '')) {
                return FwI18n::t('Common_IncorrectValue');
            }

            return true;
        }

        public function tzExists(string|null $value): bool|string {
            $tzList = timezone_identifiers_list();

            if (in_array($value, $tzList, true)) {
                return true;
            }

            return FwI18n::t('Common_IncorrectValue');
        }

        public function email(string|null $value): bool|string {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return true;
            }

            return FwI18n::t('Common_IncorrectValue');
        }

        public function int(mixed $value): bool|string {
            if (is_numeric($value) || $value === null || $value === '') {
                return true;
            }

            return FwI18n::t('Common_IncorrectValue');
        }

        public function minVal(mixed $value, int|string $min): bool|string {
            $numValue = is_numeric($value) ? floatval($value) : INF;

            if ($numValue < intval($min)) {
                return FwI18n::t('Common_Min', [$min]);
            }

            return true;
        }

        public function maxVal(mixed $value, int|string $max): bool|string {
            $numValue = is_numeric($value) ? floatval($value) : -INF;

            if ($numValue > intval($max)) {
                return FwI18n::t('Common_Max', [$max]);
            }

            return true;
        }

        public function getData(): array {
            return $this->saveData;
        }

        // Alias methods for backward compatibility
        public function min(mixed $value, int|string $min = 0): bool|string {
            return $this->minVal($value, $min);
        }

        public function max(mixed $value, int|string $max = PHP_INT_MAX): bool|string {
            return $this->maxVal($value, $max);
        }
    }
}
