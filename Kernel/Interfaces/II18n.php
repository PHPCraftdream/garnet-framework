<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface II18n {
        public function tr(string $id, array $args = []): string;

        public function isInitiated(): bool;

        public function setInitiated(): II18n;

        public function initData(): II18n;

        public function getLangData(): array;

        public function addLangData(string $lang, array $data): II18n;

        public function getLang(): string;

        public function setLang(string $lang): II18n;

        public function __call(string $name, array $arguments): string;
    }
}
