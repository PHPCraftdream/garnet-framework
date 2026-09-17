<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Quality\Spec {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Quality\GarnetSizeCheckCommand;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * size:check — замер, а не впечатление.
     *
     * Спеки держат ровно те свойства, ошибка в которых сделала бы замер
     * бесполезным: не перепутать фронт с бэкендом (пороги разные), не
     * исключить лишнее по совпадению подстроки, не спрятать исключение
     * молча и не принять генерируемый каталог за нарушителя.
     */
    describe('GarnetSizeCheckCommand', function (): void {
        describe('::limitFor — порог зависит от того, чей это файл', function (): void {
            it('даёт 500 строк фронтовым расширениям', function (): void {
                foreach (['a.ts', 'b.tsx', 'c.js', 'd.jsx', 'e.mjs', 'f.css', 'g.scss'] as $name) {
                    expect(GarnetSizeCheckCommand::limitFor($name))->toBe(GarnetSizeCheckCommand::FRONT_MAX_LINES);
                }
            });

            it('даёт 1000 строк php', function (): void {
                expect(GarnetSizeCheckCommand::limitFor('Foo.php'))->toBe(GarnetSizeCheckCommand::BACK_MAX_LINES);
            });

            it('не считает кодом всё остальное — иначе в отчёт полезут дампы и картинки', function (): void {
                foreach (['dump.sql', 'logo.png', 'notes.md', 'data.json', 'page.twig', 'noext'] as $name) {
                    expect(GarnetSizeCheckCommand::limitFor($name))->toBe(null);
                }
            });

            it('не зависит от регистра расширения', function (): void {
                expect(GarnetSizeCheckCommand::limitFor('A.PHP'))->toBe(GarnetSizeCheckCommand::BACK_MAX_LINES);
                expect(GarnetSizeCheckCommand::limitFor('B.TSX'))->toBe(GarnetSizeCheckCommand::FRONT_MAX_LINES);
            });

            it('не мерит сгенерированный мост ассетов — его длину выбирает не человек', function (): void {
                expect(GarnetSizeCheckCommand::limitFor('Bundle/FrameworkCssGen.php'))->toBe(null);
                expect(GarnetSizeCheckCommand::limitFor('Foreground/ForegroundJsGen.php'))->toBe(null);
            });

            it('но мерит файл, чьё имя лишь содержит Gen', function (): void {
                // Суффикс, а не подстрока: GenericTable.php писали руками.
                expect(GarnetSizeCheckCommand::limitFor('Common/Tables/GenericTable.php'))
                    ->toBe(GarnetSizeCheckCommand::BACK_MAX_LINES);
                expect(GarnetSizeCheckCommand::limitFor('Front/Utils/GenUtils.ts'))
                    ->toBe(GarnetSizeCheckCommand::FRONT_MAX_LINES);
            });
        });

        describe('::isGenerated — сгенерированное не участвует и в счёте элементов', function (): void {
            it('узнаёт мост ассетов по концу имени', function (): void {
                expect(GarnetSizeCheckCommand::isGenerated('FrameworkJsGen.php'))->toBe(true);
                expect(GarnetSizeCheckCommand::isGenerated('ForegroundCssGen.php'))->toBe(true);
            });

            it('не путает с рукописным файлом', function (): void {
                // Иначе после сборки корень бандла «прирастает» двумя файлами,
                // и правило семи элементов начинает зависеть от того, собирали
                // ли фронт в этом дереве.
                expect(GarnetSizeCheckCommand::isGenerated('GenerateThings.php'))->toBe(false);
                expect(GarnetSizeCheckCommand::isGenerated('Gen.ts'))->toBe(false);
            });
        });

        describe('::isSkipped — исключения по сегменту пути, а не по подстроке', function (): void {
            it('исключает чужой и генерируемый код на любой глубине', function (): void {
                expect(GarnetSizeCheckCommand::isSkipped('vendor/foo/Bar.php'))->toBe(true);
                expect(GarnetSizeCheckCommand::isSkipped('Front/node_modules/x/y.js'))->toBe(true);
                expect(GarnetSizeCheckCommand::isSkipped('Front/I18nGen/I18nDataRU.ts'))->toBe(true);
                expect(GarnetSizeCheckCommand::isSkipped('Public/assets/app/gen/js/a.gen.js'))->toBe(true);
                expect(GarnetSizeCheckCommand::isSkipped('Tests/.auth/admin-0.json'))->toBe(true);
            });

            it('не исключает каталог, чьё имя лишь НАЧИНАЕТСЯ с исключённого', function (): void {
                // Суффиксное сравнение спутало бы 'dist' и 'distribution' —
                // и тихо выкинуло бы из замера живой код.
                expect(GarnetSizeCheckCommand::isSkipped('distribution/Foo.php'))->toBe(false);
                expect(GarnetSizeCheckCommand::isSkipped('vendors/Foo.php'))->toBe(false);
                expect(GarnetSizeCheckCommand::isSkipped('PublicPages/Foo.php'))->toBe(false);
            });

            it('обычный путь не исключён', function (): void {
                expect(GarnetSizeCheckCommand::isSkipped('Foreground/Controllers/BookingsController.php'))->toBe(false);
                expect(GarnetSizeCheckCommand::isSkipped(''))->toBe(false);
            });
        });

        describe('::dirExceptions — исключения объявляет сам репозиторий', function (): void {
            beforeEach(function (): void {
                $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_exc_' . bin2hex(random_bytes(4));
                mkdir($this->root, 0o777, true);
                $this->file = $this->root . DIRECTORY_SEPARATOR . GarnetSizeCheckCommand::EXCEPTIONS_FILE;
            });

            afterEach(function (): void {
                @unlink($this->file);
                @rmdir($this->root);
            });

            it('без файла отдаёт только встроенные', function (): void {
                expect(GarnetSizeCheckCommand::dirExceptions($this->root))
                    ->toBe(GarnetSizeCheckCommand::DIR_LIMIT_EXCEPTIONS);
            });

            it('добавляет объявленные в дереве, не теряя встроенных', function (): void {
                file_put_contents($this->file, json_encode([
                    'dir_exceptions' => ['docs/audits/handover' => 'нумерованные главы аудита'],
                ]));
                $res = GarnetSizeCheckCommand::dirExceptions($this->root);

                expect($res['docs/audits/handover'])->toBe('нумерованные главы аудита');
                expect(isset($res['Migrations/Items']))->toBe(true);
            });

            it('отбрасывает исключение без причины — оно неотличимо от недоделки', function (): void {
                file_put_contents($this->file, json_encode([
                    'dir_exceptions' => ['some/dir' => '', 'other/dir' => '   '],
                ]));
                $res = GarnetSizeCheckCommand::dirExceptions($this->root);

                expect(isset($res['some/dir']))->toBe(false);
                expect(isset($res['other/dir']))->toBe(false);
            });

            it('битый файл не ломает замер', function (): void {
                file_put_contents($this->file, '{ это не json');

                expect(GarnetSizeCheckCommand::dirExceptions($this->root))
                    ->toBe(GarnetSizeCheckCommand::DIR_LIMIT_EXCEPTIONS);
            });
        });

        describe('::scan — на настоящем дереве', function (): void {
            beforeEach(function (): void {
                $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_size_' . bin2hex(random_bytes(4));
                mkdir($this->root . DIRECTORY_SEPARATOR . 'Deep', 0o777, true);
                mkdir($this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'pkg', 0o777, true);

                $put = static function (string $path, int $lines): void {
                    file_put_contents($path, str_repeat("x\n", $lines));
                };

                // Бэкенд: 1200 строк — сверх порога; 900 — в пределах.
                $put($this->root . DIRECTORY_SEPARATOR . 'Fat.php', 1200);
                $put($this->root . DIRECTORY_SEPARATOR . 'Thin.php', 900);
                // Фронт: 600 сверх порога, 400 в пределах.
                $put($this->root . DIRECTORY_SEPARATOR . 'Fat.tsx', 600);
                $put($this->root . DIRECTORY_SEPARATOR . 'Thin.tsx', 400);
                // Чужой код той же толщины — в отчёт попасть не должен.
                $put($this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'pkg'
                    . DIRECTORY_SEPARATOR . 'Huge.php', 5000);
                // Не код — тоже не должен.
                $put($this->root . DIRECTORY_SEPARATOR . 'dump.sql', 9000);
            });

            afterEach(function (): void {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST,
                );

                foreach ($it as $item) {
                    $item->isDir() ? @rmdir((string)$item) : @unlink((string)$item);
                }
                @rmdir($this->root);
            });

            it('находит ровно файлы сверх своего порога', function (): void {
                $res = GarnetSizeCheckCommand::scan($this->root);
                $paths = array_column($res['files'], 'path');

                expect($paths)->toContain('Fat.php');
                expect($paths)->toContain('Fat.tsx');
                expect($paths)->not->toContain('Thin.php');
                expect($paths)->not->toContain('Thin.tsx');
            });

            it('не заглядывает в исключённые каталоги и не считает не-код', function (): void {
                $res = GarnetSizeCheckCommand::scan($this->root);
                $paths = array_column($res['files'], 'path');

                expect($paths)->not->toContain('vendor/pkg/Huge.php');
                expect($paths)->not->toContain('dump.sql');
            });

            it('сортирует по толщине: сначала то, что читать тяжелее всего', function (): void {
                $res = GarnetSizeCheckCommand::scan($this->root);
                expect($res['files'][0]['path'])->toBe('Fat.php');
                expect($res['files'][0]['lines'])->toBe(1200);
            });

            it('называет число проверенных файлов — замер без знаменателя ничего не значит', function (): void {
                $res = GarnetSizeCheckCommand::scan($this->root);
                // Fat.php, Thin.php, Fat.tsx, Thin.tsx — четыре файла кода.
                expect($res['counted']['files'])->toBe(4);
            });

            it('видит каталог сверх семи элементов', function (): void {
                for ($i = 0; $i < 9; $i++) {
                    file_put_contents($this->root . DIRECTORY_SEPARATOR . 'Deep' . DIRECTORY_SEPARATOR . "F{$i}.php", "x\n");
                }
                $res = GarnetSizeCheckCommand::scan($this->root);
                $fat = array_column($res['dirs'], 'entries', 'path');

                expect($fat['Deep'])->toBe(9);
            });

            it('пустой каталог не считает элементом — иначе правило зависит от того, собирали ли фронт', function (): void {
                // Семь файлов — предел. Восьмой элемент — пустой каталог,
                // какой materialises время выполнения (Bundle/Front/Assets,
                // корни кэшей): нарушением он быть не должен.
                for ($i = 0; $i < 7; $i++) {
                    file_put_contents($this->root . DIRECTORY_SEPARATOR . 'Deep' . DIRECTORY_SEPARATOR . "F{$i}.php", "x\n");
                }
                mkdir($this->root . DIRECTORY_SEPARATOR . 'Deep' . DIRECTORY_SEPARATOR . 'Assets', 0o777, true);

                $res = GarnetSizeCheckCommand::scan($this->root);
                $counted = array_column($res['dirs'], 'entries', 'path');

                expect(isset($counted['Deep']))->toBe(false);
                expect(GarnetSizeCheckCommand::isEmptyDir(
                    $this->root . DIRECTORY_SEPARATOR . 'Deep' . DIRECTORY_SEPARATOR . 'Assets'
                ))->toBe(true);
            });

            it('каталог с файлом считает как обычно', function (): void {
                for ($i = 0; $i < 7; $i++) {
                    file_put_contents($this->root . DIRECTORY_SEPARATOR . 'Deep' . DIRECTORY_SEPARATOR . "F{$i}.php", "x\n");
                }
                $sub = $this->root . DIRECTORY_SEPARATOR . 'Deep' . DIRECTORY_SEPARATOR . 'Assets';
                mkdir($sub, 0o777, true);
                file_put_contents($sub . DIRECTORY_SEPARATOR . 'logo.svg', '<svg/>');

                $res = GarnetSizeCheckCommand::scan($this->root);
                $counted = array_column($res['dirs'], 'entries', 'path');

                expect($counted['Deep'])->toBe(8);
                expect(GarnetSizeCheckCommand::isEmptyDir($sub))->toBe(false);
            });

            it('корень в нарушители не пишет, а помечает исключённым — с причиной', function (): void {
                for ($i = 0; $i < 9; $i++) {
                    file_put_contents($this->root . DIRECTORY_SEPARATOR . "Extra{$i}.php", "x\n");
                }
                $res = GarnetSizeCheckCommand::scan($this->root);

                expect(array_column($res['dirs'], 'path'))->not->toContain('.');
                $excluded = array_column($res['excluded'], 'reason', 'path');
                expect(isset($excluded['.']))->toBe(true);
                expect($excluded['.'])->toContain('корень');
            });
        });
    });
}
