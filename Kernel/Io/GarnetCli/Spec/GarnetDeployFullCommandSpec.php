<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetDeployFullCommand;
    use ReflectionMethod;

    describe('GarnetDeployFullCommand', function (): void {
        describe('::preflightLayout (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->preflightLayout = new ReflectionMethod(GarnetDeployFullCommand::class, 'preflightLayout');
            });

            // NOTE: ::resolveLayout() is not covered here — it calls
            // GarnetEnv::requireAppName(), which does a raw `echo` + `exit(1)`
            // (not a catchable exception) when no app is active. Asserting
            // that path would kill the whole Kahlan process rather than fail
            // one spec, so it's intentionally left untested at this layer —
            // exercised instead by the real `php garnet deploy:full --help`
            // and scratch-directory SSH runs performed manually.

            it('preflightLayout passes silently when every required key is present', function (): void {
                $layout = [
                    'remote_path' => '/var/www/example/data/www',
                    'public_dir' => 'public',
                    'public_name' => 'myapp',
                    'framework_dir' => 'garnet-framework',
                    'app_dir' => 'garnet-app-myapp',
                    'runtime_dir' => 'garnet-runtime-myapp',
                ];

                // No exception / no exit — reaching this line at all is the
                // assertion; a failing preflight would exit(1) the whole
                // Kahlan process instead of returning here.
                $this->preflightLayout->invoke(null, $layout);
                expect(true)->toBe(true);
            });
        });

        /**
         * Регрессия на реальную аварию: выкладка кода сменила хеш в имени
         * бандла, сам бандл на хост не доехал, страница попросила файл,
         * которого нет, получила 404 — ни один остров не гидратировался, и
         * личный кабинет был белым около 12 часов. Всё остальное при этом
         * оставалось зелёным: сервер отвечал 200, PHP-ошибок не было,
         * JS-ошибок тоже (скрипт попросту не загрузился), а анонимные
         * страницы работали, потому что им хватало других бандлов.
         *
         * Гейт в деплое берёт пути ассетов из Gen-классов собранного релиза
         * и требует, чтобы каждый файл нашёлся на хосте. Здесь закрепляется
         * именно сбор путей: если он перестанет что-то замечать, проверка
         * станет молча-зелёной — то есть ровно такой же бесполезной, как все
         * сигналы во время аварии.
         */
        describe('::collectBuiltAssetPaths (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->collect = new ReflectionMethod(GarnetDeployFullCommand::class, 'collectBuiltAssetPaths');
                $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-gen-' . bin2hex(random_bytes(4));
                mkdir($this->dir . DIRECTORY_SEPARATOR . 'nested', 0o777, true);

                // Пишет Gen-класс того же вида, что генерирует сборка: набор
                // статических методов, возвращающих путь к собранному файлу.
                $this->writeGen = function (string $path, array $assets): void {
                    $body = '<?php class X {';

                    foreach ($assets as $i => $asset) {
                        $body .= " public static function a{$i}(): string { return '{$asset}'; }";
                    }
                    file_put_contents($path, $body . ' }');
                };
            });

            afterEach(function (): void {
                array_map('unlink', glob($this->dir . '/nested/*') ?: []);
                array_map('unlink', glob($this->dir . '/*.php') ?: []);
                @rmdir($this->dir . DIRECTORY_SEPARATOR . 'nested');
                @rmdir($this->dir);
            });

            it('собирает пути из Gen-классов, включая вложенные каталоги', function (): void {
                ($this->writeGen)($this->dir . '/FooGen.php', ['/assets/app/gen/js/foreground.foreground.0424.gen.js']);
                ($this->writeGen)($this->dir . '/nested/BarGen.php', ['/assets/framework/gen/css/framework.a6d4.gen.css']);

                $found = $this->collect->invoke(null, $this->dir);
                sort($found);

                expect($found)->toBe([
                    '/assets/app/gen/js/foreground.foreground.0424.gen.js',
                    '/assets/framework/gen/css/framework.a6d4.gen.css',
                ]);
            });

            it('не теряет бандл приложения — именно его отсутствие и было аварией', function (): void {
                ($this->writeGen)($this->dir . '/AppGen.php', ['/assets/app/gen/js/foreground.foreground.042499cb.gen.js']);

                expect($this->collect->invoke(null, $this->dir))
                    ->toBe(['/assets/app/gen/js/foreground.foreground.042499cb.gen.js']);
            });

            it('схлопывает повторы: один файл проверяется один раз', function (): void {
                ($this->writeGen)($this->dir . '/OneGen.php', ['/assets/a/gen/js/x.gen.js']);
                ($this->writeGen)($this->dir . '/TwoGen.php', ['/assets/a/gen/js/x.gen.js']);

                expect($this->collect->invoke(null, $this->dir))->toBe(['/assets/a/gen/js/x.gen.js']);
            });

            it('игнорирует значения вне /assets/ — это не выкладываемые файлы', function (): void {
                ($this->writeGen)($this->dir . '/MixedGen.php', ['/assets/a/gen/js/x.gen.js', '/system/bookings']);

                expect($this->collect->invoke(null, $this->dir))->toBe(['/assets/a/gen/js/x.gen.js']);
            });

            it('не заглядывает в файлы, которые не Gen-классы', function (): void {
                ($this->writeGen)($this->dir . '/Helper.php', ['/assets/a/gen/js/ignored.gen.js']);

                expect($this->collect->invoke(null, $this->dir))->toBe([]);
            });
        });

        /**
         * Регрессия на вторую аварию, устроенную уже самим гейтом. Проверка
         * ассетов получила путь, собранный из `public_name` вместо
         * `public_dir`: `public_name` — это имя приложения внутри URL
         * (`/assets/<public_name>/...`), а не каталог на диске. Путь не
         * существовал, `test -f` не нашёл ни одного файла, гейт отчитался
         * «9 из 9 ассетов нет» и оставил боевой сайт в maintenance — при
         * том что выкладка прошла безупречно и все девять файлов лежали на
         * месте. Ложная тревога такого гейта стоит ровно столько же, сколько
         * авария, которую он должен был предотвращать.
         */
        describe('::remotePublicPath (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->remotePublic = new ReflectionMethod(GarnetDeployFullCommand::class, 'remotePublicPath');
                $this->layout = [
                    'remote_path' => '/var/www/u1780595/data/www',
                    'public_dir' => 'slotbook.ru',
                    'public_name' => 'slotbook',
                ];
            });

            it('строит путь из public_dir — каталога, а не из имени в URL ассетов', function (): void {
                expect($this->remotePublic->invoke(null, $this->layout))
                    ->toBe('/var/www/u1780595/data/www/slotbook.ru');
            });

            it('не подставляет public_name: именно это оставило прод в maintenance', function (): void {
                $path = $this->remotePublic->invoke(null, $this->layout);

                // Два разных утверждения об одном пути: он оканчивается
                // каталогом и НЕ оканчивается именем приложения. Проверять
                // только вхождение подстроки мало — '/slotbook' лежит внутри
                // '/slotbook.ru', и наивная проверка зелена на обоих.
                expect(str_ends_with($path, '/slotbook.ru'))->toBe(true);
                expect(str_ends_with($path, '/slotbook'))->toBe(false);
            });

            it('не удваивает слеш, если remote_path заканчивается на него', function (): void {
                $layout = $this->layout;
                $layout['remote_path'] = '/var/www/u1780595/data/www/';

                expect($this->remotePublic->invoke(null, $layout))
                    ->toBe('/var/www/u1780595/data/www/slotbook.ru');
            });
        });
    });
}
