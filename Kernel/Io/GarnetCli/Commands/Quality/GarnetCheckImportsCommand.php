<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Quality {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Указывает ли каждый `use` на файл, который PSR-4 обязан там держать.
     *
     * Проверка существует из-за конкретного случая: после переноса классов
     * по тематическим каталогам `run_web.php` остался с тремя старыми
     * импортами. cs-fixer его прочитал, сборка не запустила, спеки поднимают
     * свой каркас, а в phpstan.neon точки входа не входят — весь гейт был
     * зелёным, и каждый HTTP-запрос отдавал «Class not found». Об этом
     * сообщил прод.
     *
     * Инвариант дешёвый и не зависит от того, какие пути кто-то перечислил
     * в конфиге анализатора: имя класса и путь файла связаны PSR-4, значит
     * связь можно проверить арифметикой, а не выполнением кода.
     *
     * Чего проверка НЕ делает: не грузит классы (иначе понадобился бы
     * рабочий автолоадер и все зависимости) и не разбирает PHP — она читает
     * строки `use ...;` из шапки файла. Этого достаточно: именно там живут
     * импорты, и именно они ломаются при переносе.
     */
    class GarnetCheckImportsCommand {
        /**
         * Каталоги, внутрь которых не заходим (чужой и сгенерированный код).
         *
         * `Public/` в списке нет намеренно: там лежит docroot-точка входа, а
         * точки входа — ровно тот случай, из-за которого эта проверка и
         * появилась.
         */
        public const SKIP_DIRS = [
            'vendor', 'node_modules', '.git', 'dist', 'test-results',
            'playwright-report', 'WorkDir', '.auth', '.agents', '.worktrees', 'I18nGen',
        ];

        /**
         * Классы, которых в дереве нет по устройству, и почему.
         *
         * @var array<string, string>
         */
        public const KNOWN_ABSENT = [
            'Gen' => 'мост ассетов: класс пишет сборка фронта, в репозитории его нет',
        ];

        public static function run(array $args = []): void {
            $json = in_array('--json', $args, true);
            $roots = self::resolveRoots($args);

            if ($roots === []) {
                fwrite(STDERR, "check:imports: не найдено ни одного корня для проверки\n");

                exit(1);
            }
            $report = [];

            foreach ($roots as $name => $root) {
                $report[$name] = self::scan($root, $roots);
            }

            if ($json) {
                echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
            } else {
                self::printReport($report);
            }
            $broken = 0;

            foreach ($report as $part) {
                $broken += count($part['broken']);
            }

            exit($broken === 0 ? 0 : 1);
        }

        /**
         * Корни: явные аргументы, иначе приложение и фреймворк.
         *
         * @return array<string, string>
         */
        private static function resolveRoots(array $args): array {
            $roots = [];

            foreach (array_filter($args, static fn (string $a): bool => $a !== '' && !str_starts_with($a, '-')) as $path) {
                $real = realpath($path);

                if ($real !== false && is_dir($real)) {
                    $roots[basename($real)] = str_replace('\\', '/', $real);
                }
            }

            if ($roots !== []) {
                return $roots;
            }
            $appName = GarnetEnv::readAppName();

            if ($appName !== '') {
                $appDir = GarnetEnv::getAppDir($appName);

                if ($appDir !== '' && is_dir($appDir)) {
                    $roots[$appName] = str_replace('\\', '/', $appDir);
                }
            }

            if (GarnetRunner::$frameworkDir !== '' && is_dir(GarnetRunner::$frameworkDir)) {
                $roots['framework'] = str_replace('\\', '/', GarnetRunner::$frameworkDir);
            }

            return $roots;
        }

        /**
         * Карта «префикс namespace → каталог» по composer.json корня, плюс
         * пакет фреймворка: приложение ссылается и на его классы.
         *
         * @return array<string, string>
         */
        public static function psr4Map(string $root, array $allRoots = []): array {
            $map = [];
            $file = rtrim($root, '/') . '/composer.json';
            $raw = is_file($file) ? @file_get_contents($file) : false;
            $data = is_string($raw) ? json_decode($raw, true) : null;

            if (is_array($data)) {
                foreach (['autoload', 'autoload-dev'] as $section) {
                    /** @var array<string, string|list<string>> $psr4 */
                    $psr4 = $data[$section]['psr-4'] ?? [];

                    foreach ($psr4 as $prefix => $paths) {
                        foreach ((array)$paths as $path) {
                            $dir = rtrim($root, '/') . '/' . trim(str_replace('\\', '/', (string)$path), './');
                            $map[$prefix] = rtrim($dir, '/');
                        }
                    }
                }
            }
            $vendorFramework = rtrim($root, '/') . '/vendor/phpcraftdream/garnet-framework';

            if (is_dir($vendorFramework)) {
                $map += self::psr4Map($vendorFramework);
            }

            foreach ($allRoots as $other) {
                if ($other !== $root && is_file(rtrim($other, '/') . '/composer.json')) {
                    $map += self::psr4Map($other);
                }
            }

            return $map;
        }

        /** Путь файла, который по PSR-4 обязан держать этот класс, либо null. */
        public static function fileFor(string $fqn, array $psr4): ?string {
            $best = null;

            foreach ($psr4 as $prefix => $dir) {
                if ($prefix === '' || str_starts_with($fqn, $prefix)) {
                    if ($best === null || strlen($prefix) > strlen($best[0])) {
                        $best = [$prefix, $dir];
                    }
                }
            }

            if ($best === null) {
                return null;
            }
            $tail = substr($fqn, strlen($best[0]));

            return $best[1] . '/' . str_replace('\\', '/', $tail) . '.php';
        }

        /** Известно ли, что такого файла нет по устройству. */
        public static function isKnownAbsent(string $fqn): bool {
            $short = substr($fqn, strrpos($fqn, '\\') === false ? 0 : strrpos($fqn, '\\') + 1);

            foreach (array_keys(self::KNOWN_ABSENT) as $suffix) {
                if (str_ends_with($short, $suffix)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @return list<string> Полные имена из строк `use ...;` шапки файла.
         */
        public static function importsIn(string $source): array {
            $head = $source;
            $stop = preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s/m', $source, $m, PREG_OFFSET_CAPTURE);

            if ($stop === 1) {
                $head = substr($source, 0, $m[0][1]);
            }
            preg_match_all('/^\s*use\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*(?:as\s+\w+\s*)?;/m', $head, $found);

            return array_values(array_filter(
                $found[1],
                static fn (string $fqn): bool => str_contains($fqn, '\\'),
            ));
        }

        /**
         * @return array{broken: list<array{file: string, line: int, class: string}>,
         *               counted: int}
         */
        public static function scan(string $root, array $allRoots = []): array {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            $psr4 = self::psr4Map($root, $allRoots);
            $broken = [];
            $counted = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                $path = str_replace('\\', '/', (string)$item);
                $rel = ltrim(substr($path, strlen($root)), '/');

                if (self::isSkipped($rel)) {
                    continue;
                }

                if (!$item->isFile() || !str_ends_with($path, '.php')) {
                    continue;
                }
                $source = @file_get_contents($path);

                if ($source === false) {
                    continue;
                }
                // Классы, объявленные в самом файле (стабы фикстур), лежат не
                // по PSR-4 пути — и не должны.
                preg_match_all('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $own);
                $ownShort = array_flip($own[1]);

                foreach (self::importsIn($source) as $fqn) {
                    $short = substr($fqn, strrpos($fqn, '\\') + 1);

                    if (isset($ownShort[$short]) || self::isKnownAbsent($fqn)) {
                        continue;
                    }
                    $expected = self::fileFor($fqn, $psr4);

                    if ($expected === null) {
                        continue;
                    }
                    $counted++;

                    if (is_file($expected)) {
                        continue;
                    }
                    $broken[] = ['file' => $rel, 'line' => self::lineOf($source, $fqn), 'class' => $fqn];
                }
            }

            usort($broken, static fn (array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

            return ['broken' => $broken, 'counted' => $counted];
        }

        public static function isSkipped(string $rel): bool {
            foreach (explode('/', $rel) as $segment) {
                if ($segment !== '' && in_array($segment, self::SKIP_DIRS, true)) {
                    return true;
                }
            }

            return false;
        }

        private static function lineOf(string $source, string $fqn): int {
            $pos = strpos($source, 'use ' . $fqn);

            return $pos === false ? 0 : substr_count($source, "\n", 0, $pos) + 1;
        }

        private static function printReport(array $report): void {
            echo "=== check:imports — каждый `use` должен указывать на файл, который PSR-4 обязан там держать\n\n";

            foreach ($report as $name => $part) {
                echo "--- {$name}: проверено {$part['counted']} импортов\n";

                if ($part['broken'] === []) {
                    echo "    импортов без файла: нет\n\n";

                    continue;
                }
                echo '    импортов без файла: ' . count($part['broken']) . "\n";

                foreach ($part['broken'] as $b) {
                    printf("      %s:%d → %s\n", $b['file'], $b['line'], $b['class']);
                }
                echo "\n";
            }
        }
    }
}
