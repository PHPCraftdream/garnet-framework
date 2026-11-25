<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Render\I18n {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;

    class I18nFrontendDumper {
        /**
         * Write typed frontend translations for a bundle.
         *
         * @param array $langData Translation maps indexed by language code.
         * @param string $dir Existing output directory.
         * @param string $bundleName Bundle identifier used in the export name.
         * @param bool $isFrameworkBundle Whether this is the framework bundle.
         * @throws CommonException When the frontend template cannot be read.
         */
        public static function dump(array $langData, string $dir, string $bundleName, bool $isFrameworkBundle): void {
            $dir = realpath($dir);

            if (!$dir || !is_dir($dir)) {
                return;
            }

            $classNames = [];
            $methods = [];

            foreach ($langData as $lang => $data) {
                $className = "I18nData{$lang}";
                $fileName = "{$className}.ts";
                $fields = [
                    '    /** Language code for this translation set. */',
                    '    lang: ' . self::formatStringLiteral((string)$lang, '    lang: ') . ',',
                ];
                $hasTranslations = false;

                foreach ($data as $key => $value) {
                    if (mb_strtolower((string)$key) === 'lang') {
                        continue;
                    }

                    $key = (string)$key;
                    $fields[] = "    /** Translation text for `{$key}`. */";
                    $fields[] = "    {$key}: " . self::formatStringLiteral((string)$value, "    {$key}: ") . ',';
                    $methods[$key] = $key;
                    $hasTranslations = true;
                }

                if (!$hasTranslations) {
                    continue;
                }

                $classNames[] = $className;
                $content = "/** Translation data for {$lang}. */\nexport const {$className} = {\n" . join("\n", $fields) . "\n} as const;\n";
                file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, $content);
            }

            $imports = array_map(fn ($cl) => "import {{$cl}} from './{$cl}';", $classNames);
            $imports[] = "import {I18nBase} from '@common/Utils/I18nBase';";
            $imports = join("\n", $imports);
            $className = "I18n{$bundleName}";

            // The package root is four levels above this directory.
            $baseDir = dirname(__DIR__, 4);
            $templatePath = $baseDir . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'CodeFiles' . DIRECTORY_SEPARATOR . 'TrTs.template';
            $classTemplate = @file_get_contents($templatePath);

            if (!$classTemplate) {
                throw new CommonException('Fail on read template');
            }

            $tr = str_replace('[[imports]]', $imports, $classTemplate);
            $tr = str_replace('[[bundleName]]', $bundleName, $tr);
            $tr = str_replace('[[classArr]]', join(', ', $classNames), $tr);
            $tr = str_replace('[[className]]', $className, $tr);
            $tr = str_replace('[[classNames]]', join(', ', $classNames), $tr);

            if (empty($methods)) {
                return;
            }

            $methods = array_values($methods);
            $methods = array_map(
                fn ($method) => "    /** Translation lookup for `{$method}`. */\n    {$method}: t = (a = []) => this.t('{$method}', a);",
                $methods
            );

            $tr = str_replace('[[methods]]', join("\n", $methods), $tr);
            file_put_contents($dir . DIRECTORY_SEPARATOR . "{$className}.ts", $tr);
        }

        /**
         * Format a string literal as concatenated, value-preserving lines.
         */
        private static function formatStringLiteral(string $value, string $prefix): string {
            $characters = mb_str_split($value, 1, 'UTF-8');
            $chunks = [];
            $chunk = '';

            foreach ($characters as $character) {
                $candidate = $chunk . $character;
                $encoded = json_encode($candidate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded === false) {
                    $encoded = "''";
                }

                $firstLineLength = mb_strlen($prefix . $encoded . ' +', 'UTF-8');
                $continuationLength = mb_strlen('        ' . $encoded . ' +', 'UTF-8');

                if ($chunk !== '' && max($firstLineLength, $continuationLength) > 120) {
                    $chunks[] = $chunk;
                    $chunk = $character;
                } else {
                    $chunk = $candidate;
                }
            }

            if ($chunk !== '' || $chunks === []) {
                $chunks[] = $chunk;
            }

            $literals = array_map(
                static fn ($part) => json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: "''",
                $chunks
            );

            return join(" +\n        ", $literals);
        }
    }
}
