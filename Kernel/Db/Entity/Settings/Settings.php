<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Settings {
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ISettings;

    class Settings implements ISettings {
        protected bool $read = false;

        protected bool $changed = false;

        protected array $data = [];

        protected array $changedData = [];

        protected array $unsetData = [];

        protected array $originalData = [];

        /**
         * @var static
         */
        protected static ?Settings $instance = null;

        protected function __construct() {
        }

        public static function get(): static {
            if (empty(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }

        protected function processReadData(array $data): void {
            if (empty($data)) {
                return;
            }

            foreach ($data as $item) {
                $param = $item['param'] ?? null;
                $value = $item['value'] ?? null;

                if (empty($param)) {
                    continue;
                }

                $this->data[$param] = $value;
            }

            $this->originalData = $this->data;
            $this->read = true;
        }

        public function readAsync(): void {
            if ($this->read) {
                return;
            }

            SettingsTable::get()->selectAllAsync(callback: function ($data): void {
                $this->processReadData($data);
            });
        }

        public function read(): void {
            if ($this->read) {
                return;
            }

            $data = SettingsTable::get()->selectAll();

            $this->processReadData($data);
        }

        /**
         * @param string $param
         * @param string $default
         * @return string
         */
        public function getValue(string $param, string $default = ''): string {
            $this->read();

            return $this->data[$param] ?? $default;
        }

        public function setValue(string $param, string $value): void {
            $this->read();
            $oldValue = $this->data[$param] ?? null;

            if ($oldValue === $value) {
                unset($this->changedData[$param]);
                $this->changed = !empty($this->changedData);

                return;
            }

            $originalValue = $this->originalData[$param] ?? null;

            $this->data[$param] = $value;
            $this->changed = true;

            if ($originalValue !== $value) {
                $this->changedData[$param] = $value;
            }
        }

        public function unsetValue(string $name): void {
            if (!array_key_exists($name, $this->data)) {
                return;
            }

            unset($this->data[$name], $this->changedData[$name]);

            $this->unsetData[$name] = true;

            // changed is true if we have changedData OR if we have unsetData that was in originalData
            $hasOriginalUnset = false;

            foreach ($this->unsetData as $key => $val) {
                if (array_key_exists($key, $this->originalData)) {
                    $hasOriginalUnset = true;

                    break;
                }
            }

            $this->changed = !empty($this->changedData) || $hasOriginalUnset;
        }

        public function getAllData(): array {
            return $this->data;
        }

        public function flush(): void {
            $this->flushChanged();
            $this->flushUnset();
        }

        protected function flushUnset(): void {
            if (empty($this->unsetData)) {
                return;
            }
            $params = array_keys($this->unsetData);
            $delete = SettingsTable::get()->newDelete();
            $delete->where('`param` IN (:params)', ['params' => $params]);
            $queryEx = SettingsTable::get()->getQueryEx();
            $queryEx->exDeleteAsync($delete);
        }

        protected function flushChanged(): void {
            if (!$this->changed || empty($this->changedData)) {
                return;
            }

            $queryData = [];

            foreach ($this->changedData as $param => $value) {
                $queryData[] = [
                    'param' => $param,
                    'value' => $value,
                ];
            }

            [$sql, $params] = QueryTools::makeInsertBatchNamed(
                SettingsTable::get()->getTableName(),
                $queryData,
                'value = VALUES(value)',
                true,
            );

            $queryEx = SettingsTable::get()->getQueryEx();
            $queryEx->exAsync($sql, $params);
        }
    }
}
