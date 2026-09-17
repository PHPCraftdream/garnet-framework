<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Сколько в дереве файлов, которые уже нельзя держать в голове.
     *
     * Правило простое и не про красоту: файл, который не читается целиком,
     * читают по кускам — и тогда правка в одном куске ломает другой, потому
     * что связь между ними никто не удержал. Пороги: 500 строк для фронта,
     * 1000 для бэкенда. Файл сверх порога должен стать папкой из нескольких
     * файлов. Каталог сверх семи элементов — раскладкой по темам: список, в
     * котором больше семи пунктов, перестаёт читаться как список.
     *
     * Команда существует, чтобы «сколько осталось» был воспроизводимым
     * числом, а не впечатлением от разового замера. Выход 1 при нарушениях
     * — чтобы правило можно было поставить в гейт, а не надеяться на память.
     *
     * Исключения не прячутся: они перечислены здесь с причиной, и каталог,
     * попавший в список, в отчёте помечен как исключённый, а не выкинут
     * молча — иначе следующий читатель примет их за недоделку.
     */
    class GarnetSizeCheckCommand {
        /** Фронтовый файл сверх этого числа строк должен стать папкой. */
        public const FRONT_MAX_LINES = 500;

        /** Бэкендовый — сверх этого. */
        public const BACK_MAX_LINES = 1000;

        /** Элементов в каталоге, после которых нужна раскладка по темам. */
        public const MAX_DIR_ENTRIES = 7;

        /** @var list<string> Расширения, которые считаем фронтом. */
        public const FRONT_EXT = ['ts', 'tsx', 'js', 'jsx', 'mjs', 'css', 'scss'];

        /** @var list<string> Расширения, которые считаем бэкендом. */
        public const BACK_EXT = ['php'];

        /**
         * Каталоги, которые правило не касается, и почему. Имя сверяется по
         * одному сегменту пути, а не по суффиксу: 'dist' исключает любой
         * dist на любой глубине и не задевает 'distribution'.
         *
         * @var array<string, string>
         */
        public const SKIP_DIRS = [
            'vendor' => 'чужой код, ставится пакетным менеджером',
            'node_modules' => 'то же для фронта',
            '.git' => 'служебное дерево',
            'dist' => 'результат сборки',
            'Public' => 'собранные ассеты с хешами в именах',
            'I18nGen' => 'генерируется из файлов данных',
            'test-results' => 'артефакты прогона',
            'playwright-report' => 'то же',
            '.auth' => 'сохранённые состояния входа, создаются прогоном',
            '.agents' => 'клетки агентов: переписка и память, не код',
            'WorkDir' => 'рабочие данные приложения на диске',
            '.worktrees' => 'временные рабочие копии git',
            '.idea' => 'настройки редактора',
            '.vscode' => 'то же',
        ];

        /**
         * Каталоги, к которым правило семи элементов не применяется, с
         * причиной. Сверяется по пути относительно корня замера.
         *
         * @var array<string, string>
         */
        public const DIR_LIMIT_EXCEPTIONS = [
            '.' => 'корень: composer.json, package.json, garnet и прочее инструменты ищут по фиксированным путям',
            'Migrations/Items' => 'нумерованные миграции: их порядок и есть структура',
        ];

        /** Файл с исключениями, специфичными для конкретного дерева. */
        public const EXCEPTIONS_FILE = '.size-check.json';

        /**
         * Исключения для одного дерева: встроенные плюс объявленные в
         * `.size-check.json` его корня.
         *
         * Почему файлом, а не константой здесь: «нумерованные главы аудита»
         * или «перечислимый набор персон» — решения КОНКРЕТНОГО репозитория,
         * и фреймворку о них знать неоткуда. Решение остаётся там, где его
         * приняли, вместе с причиной, а не превращается в молчаливое
         * исключение в чужом коде.
         *
         * Формат: {"dir_exceptions": {"путь/от/корня": "причина"}}.
         * Причина обязательна: исключение без причины неотличимо от
         * недоделки, а именно это различие и важно следующему читателю.
         *
         * @return array<string, string>
         */
        public static function dirExceptions(string $root): array {
            $file = rtrim(str_replace('\\', '/', $root), '/') . '/' . self::EXCEPTIONS_FILE;

            if (!is_file($file)) {
                return self::DIR_LIMIT_EXCEPTIONS;
            }
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;

            if (!is_array($data) || !is_array($data['dir_exceptions'] ?? null)) {
                return self::DIR_LIMIT_EXCEPTIONS;
            }
            $extra = [];

            foreach ($data['dir_exceptions'] as $path => $reason) {
                if (is_string($path) && is_string($reason) && trim($reason) !== '') {
                    $extra[$path] = $reason;
                }
            }

            return $extra + self::DIR_LIMIT_EXCEPTIONS;
        }

        public static function run(array $args = []): void {
            $json = in_array('--json', $args, true);
            $roots = self::resolveRoots($args);

            if ($roots === []) {
                fwrite(STDERR, "size:check: не найдено ни одного корня для замера\n");

                exit(1);
            }

            $report = [];

            foreach ($roots as $name => $root) {
                $report[$name] = self::scan($root);
            }

            if ($json) {
                echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
            } else {
                self::printReport($report);
            }

            $violations = 0;

            foreach ($report as $part) {
                $violations += count($part['files']) + count($part['dirs']);
            }

            exit($violations === 0 ? 0 : 1);
        }

        /**
         * Корни замера: явные аргументы-пути, иначе приложение и фреймворк.
         *
         * @return array<string, string>
         */
        private static function resolveRoots(array $args): array {
            $explicit = array_values(array_filter(
                $args,
                static fn (string $a): bool => $a !== '' && !str_starts_with($a, '-'),
            ));

            if ($explicit !== []) {
                $roots = [];

                foreach ($explicit as $path) {
                    $real = realpath($path);

                    if ($real !== false && is_dir($real)) {
                        $roots[basename($real)] = $real;
                    }
                }

                return $roots;
            }

            $roots = [];
            $appName = GarnetEnv::readAppName();

            if ($appName !== '') {
                $appDir = GarnetEnv::getAppDir($appName);

                if ($appDir !== '' && is_dir($appDir)) {
                    $roots[$appName] = $appDir;
                }
            }

            if (GarnetRunner::$frameworkDir !== '' && is_dir(GarnetRunner::$frameworkDir)) {
                $roots['framework'] = GarnetRunner::$frameworkDir;
            }

            return $roots;
        }

        /**
         * @return array{files: list<array{path: string, lines: int, limit: int, kind: string}>,
         *               dirs: list<array{path: string, entries: int}>,
         *               excluded: list<array{path: string, entries: int, reason: string}>,
         *               counted: array{files: int, dirs: int}}
         */
        public static function scan(string $root): array {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            $dirExceptions = self::dirExceptions($root);
            $files = [];
            $dirs = [];
            $excluded = [];
            $countedFiles = 0;
            $countedDirs = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            /** @var array<string, int> $dirEntries */
            $dirEntries = ['.' => self::countEntries($root)];

            foreach ($iterator as $item) {
                $path = str_replace('\\', '/', (string)$item);
                $rel = ltrim(substr($path, strlen($root)), '/');

                if (self::isSkipped($rel)) {
                    continue;
                }

                if ($item->isDir()) {
                    $dirEntries[$rel] = self::countEntries($path);

                    continue;
                }

                if (!$item->isFile()) {
                    continue;
                }
                $limit = self::limitFor($path);

                if ($limit === null) {
                    continue;
                }
                $countedFiles++;
                $lines = self::countLines($path);

                if ($lines > $limit) {
                    $files[] = [
                        'path' => $rel,
                        'lines' => $lines,
                        'limit' => $limit,
                        'kind' => $limit === self::FRONT_MAX_LINES ? 'front' : 'back',
                    ];
                }
            }

            foreach ($dirEntries as $rel => $entries) {
                $countedDirs++;

                if ($entries <= self::MAX_DIR_ENTRIES) {
                    continue;
                }

                if (isset($dirExceptions[$rel])) {
                    $excluded[] = ['path' => $rel, 'entries' => $entries, 'reason' => $dirExceptions[$rel]];

                    continue;
                }
                $dirs[] = ['path' => $rel, 'entries' => $entries];
            }

            usort($files, static fn (array $a, array $b): int => $b['lines'] <=> $a['lines']);
            usort($dirs, static fn (array $a, array $b): int => $b['entries'] <=> $a['entries']);

            return [
                'files' => $files,
                'dirs' => $dirs,
                'excluded' => $excluded,
                'counted' => ['files' => $countedFiles, 'dirs' => $countedDirs],
            ];
        }

        /** Порог для файла по расширению, либо null — файл не код. */
        public static function limitFor(string $path): ?int {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($ext, self::FRONT_EXT, true)) {
                return self::FRONT_MAX_LINES;
            }

            if (in_array($ext, self::BACK_EXT, true)) {
                return self::BACK_MAX_LINES;
            }

            return null;
        }

        /**
         * Лежит ли путь в исключённом каталоге. Сверяется посегментно:
         * suffix-сравнение спутало бы 'dist' и 'distribution'.
         */
        public static function isSkipped(string $rel): bool {
            foreach (explode('/', $rel) as $segment) {
                if ($segment !== '' && isset(self::SKIP_DIRS[$segment])) {
                    return true;
                }
            }

            return false;
        }

        /** Элементы каталога: файлы и подкаталоги, кроме исключённых и скрытых служебных. */
        private static function countEntries(string $dir): int {
            $n = 0;

            foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $entry) {
                $name = $entry->getFilename();

                if (isset(self::SKIP_DIRS[$name])) {
                    continue;
                }

                if (str_starts_with($name, '.')) {
                    continue;
                }
                $n++;
            }

            return $n;
        }

        private static function countLines(string $path): int {
            $h = @fopen($path, 'rb');

            if ($h === false) {
                return 0;
            }
            $n = 0;

            while (fgets($h) !== false) {
                $n++;
            }
            fclose($h);

            return $n;
        }

        private static function printReport(array $report): void {
            echo '=== size:check — порог ' . self::FRONT_MAX_LINES . ' строк для фронта, '
                . self::BACK_MAX_LINES . ' для бэкенда; каталог не больше ' . self::MAX_DIR_ENTRIES . " элементов\n\n";

            foreach ($report as $name => $part) {
                echo "--- {$name}: проверено {$part['counted']['files']} файлов кода, "
                    . "{$part['counted']['dirs']} каталогов\n";

                if ($part['files'] === []) {
                    echo "    файлов сверх порога: нет\n";
                } else {
                    echo '    файлов сверх порога: ' . count($part['files']) . "\n";

                    foreach ($part['files'] as $f) {
                        printf("      %5d (порог %d, %s)  %s\n", $f['lines'], $f['limit'], $f['kind'], $f['path']);
                    }
                }

                if ($part['dirs'] === []) {
                    echo "    каталогов сверх семи элементов: нет\n";
                } else {
                    echo '    каталогов сверх семи элементов: ' . count($part['dirs']) . "\n";

                    foreach ($part['dirs'] as $d) {
                        printf("      %3d  %s\n", $d['entries'], $d['path']);
                    }
                }

                foreach ($part['excluded'] as $e) {
                    printf("    исключён: %s (%d) — %s\n", $e['path'], $e['entries'], $e['reason']);
                }
                echo "\n";
            }
        }
    }
}
