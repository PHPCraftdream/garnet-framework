<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity {
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IEntityConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Forms\Updater;

    abstract class BaseEntity implements IEntityConfig {
        protected static IEntityConfig $config;

        public static function getEntityConfig(): IEntityConfig {
            if (!empty(static::$config)) {
                return static::$config;
            }

            static::$config = new static();

            return static::$config;
        }

        public function idField(): string {
            return 'id';
        }

        public function selectFields(): array {
            return ['id'];
        }

        public function manageGridFields(): array {
            return ['id'];
        }

        public function manageFormFields(): array {
            return ['id'];
        }

        public function viewFields(): array {
            return ['id'];
        }

        public function editFields(): array {
            return ['id'];
        }

        public function dataFields(): array {
            return [];
        }

        public function getGridInfo(): array {
            return [
                'idColumn' => $this->idField(),
                'fields' => $this->getFieldsInfo(),
                'gridFields' => $this->manageGridFields(),
                'detailsFields' => $this->manageFormFields(),
            ];
        }

        public function filterKeys(array $src, ?array $keys = null): array {
            if (empty($keys)) {
                return $src;
            }

            $newResult = [];

            foreach ($keys as $fieldName) {
                if (array_key_exists($fieldName, $src)) {
                    $newResult[$fieldName] = $src[$fieldName];
                }
            }

            return $newResult;
        }

        public function saveOne(array $postData, array $fields, ?SaveFilesParams $saveFiles = null): SaveEntityResult {
            $fInfo = $this->getFieldsInfo($fields);
            $dataFields = $this->dataFields();
            $addData = null;

            // Filter postData to only include fields in $fields
            $filteredPostData = $this->filterKeys($postData, $fields);

            $paramsUpdate = new Updater($filteredPostData, $saveFiles->files ?? []);
            $paramsUpdate->validateByFieldsInfo($fInfo, fn ($info) => empty($info['dataParam']));

            if (!empty($saveFiles)) {
                $paramsUpdate->processUploadPhoto($fInfo, $saveFiles->prevData, $saveFiles->baseDir);
            }

            if (!empty($dataFields)) {
                $addData = new Updater($filteredPostData);
                $addData->validateByFieldsInfo($this->getFieldsInfo($dataFields));
            }

            return new SaveEntityResult(
                $paramsUpdate,
                $addData
            );
        }
    }
}
