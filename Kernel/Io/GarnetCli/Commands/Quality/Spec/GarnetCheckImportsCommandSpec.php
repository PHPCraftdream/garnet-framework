<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Quality\Spec {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Quality\GarnetCheckImportsCommand;

    /**
     * Проверка, которая появилась после падения прода: точки входа
     * (`run_web.php`) не входят в пути phpstan, и старый импорт в них
     * оставался незамеченным всеми гейтами до первого HTTP-запроса.
     *
     * Здесь проверяется именно разбор и арифметика PSR-4 — то, на чём
     * команда может ошибиться молча: пропустить импорт, посчитать чужой
     * файл своим или объявить сломанным то, чего в дереве нет по
     * устройству.
     */
    describe('GarnetCheckImportsCommand', function (): void {
        describe('::importsIn — читаются импорты шапки, а не тела класса', function (): void {
            it('берёт полные имена и игнорирует подключение трейта', function (): void {
                $src = <<<'PHP'
                    <?php
                    namespace App\Thing;

                    use App\Other\Helper;
                    use App\Other\Second as Alias;
                    use Closure;

                    class Thing {
                        use SomeTrait;
                    }
                    PHP;
                $found = GarnetCheckImportsCommand::importsIn($src);
                expect($found)->toContain('App\\Other\\Helper');
                expect($found)->toContain('App\\Other\\Second');
                // `Closure` и `SomeTrait` — без namespace: PSR-4 их не адресует.
                expect($found)->not->toContain('Closure');
                expect($found)->not->toContain('SomeTrait');
            });

            it('не заглядывает в тело класса — там `use` означает трейт', function (): void {
                $src = <<<'PHP'
                    <?php
                    namespace App;

                    class A {
                        use App\Traits\Inside;
                    }
                    PHP;
                expect(GarnetCheckImportsCommand::importsIn($src))->toBe([]);
            });
        });

        describe('::fileFor — путь считается по самому длинному префиксу', function (): void {
            it('выбирает более точный префикс, а не первый подошедший', function (): void {
                $map = [
                    'PHPCraftdream\\Garnet\\' => '/fw',
                    'PHPCraftdream\\Garnet\\Kernel\\' => '/fw/Kernel',
                ];
                expect(GarnetCheckImportsCommand::fileFor('PHPCraftdream\\Garnet\\Kernel\\Io\\X', $map))
                    ->toBe('/fw/Kernel/Io/X.php');
            });

            it('понимает пустой префикс (psr-4 на корень приложения)', function (): void {
                expect(GarnetCheckImportsCommand::fileFor('App\\Common\\Tables\\Bookings', ['' => '/app']))
                    ->toBe('/app/App/Common/Tables/Bookings.php');
            });

            it('возвращает null для чужого имени: его размещение не наше дело', function (): void {
                expect(GarnetCheckImportsCommand::fileFor('Twig\\Environment', ['App\\' => '/app']))
                    ->toBe(null);
            });
        });

        describe('::isKnownAbsent — сгенерированного класса в дереве нет по устройству', function (): void {
            it('узнаёт мост ассетов по концу короткого имени', function (): void {
                expect(GarnetCheckImportsCommand::isKnownAbsent('PHPCraftdream\\Garnet\\Bundle\\FrameworkJsGen'))
                    ->toBe(true);
                expect(GarnetCheckImportsCommand::isKnownAbsent('App\\Foreground\\ForegroundCssGen'))
                    ->toBe(true);
            });

            it('не прощает рукописный класс с Gen в середине имени', function (): void {
                expect(GarnetCheckImportsCommand::isKnownAbsent('App\\Common\\GenericTable'))->toBe(false);
                expect(GarnetCheckImportsCommand::isKnownAbsent('App\\Tools\\Generator'))->toBe(false);
            });
        });

        describe('::isSkipped — чужой и собранный код не проверяем', function (): void {
            it('исключает vendor и сборку на любой глубине', function (): void {
                expect(GarnetCheckImportsCommand::isSkipped('vendor/a/B.php'))->toBe(true);
                expect(GarnetCheckImportsCommand::isSkipped('Front/node_modules/x/y.php'))->toBe(true);
                expect(GarnetCheckImportsCommand::isSkipped('Public/index.php'))->toBe(false);
            });

            it('не исключает каталог, чьё имя лишь начинается с исключённого', function (): void {
                expect(GarnetCheckImportsCommand::isSkipped('vendors/A.php'))->toBe(false);
                expect(GarnetCheckImportsCommand::isSkipped('distribution/A.php'))->toBe(false);
            });
        });

        describe('::scan — на настоящем дереве фреймворка', function (): void {
            it('проверяет сотни импортов и не находит ни одного без файла', function (): void {
                $result = GarnetCheckImportsCommand::scan(dirname(__DIR__, 6));
                expect($result['counted'])->toBeGreaterThan(500);
                expect($result['broken'])->toBe([]);
            });
        });
    });
}
