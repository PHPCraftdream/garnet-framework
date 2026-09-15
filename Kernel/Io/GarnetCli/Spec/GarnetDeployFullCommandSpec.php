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
         * бандла, сам бандл на хост не доехал, страница попросила
         * `foreground.<новый хеш>.gen.js` → 404 → ни один остров не
         * гидратировался, и личный кабинет был белым ~12 часов. Всё
         * остальное при этом оставалось зелёным: сервер отвечал 200,
         * PHP-ошибок не было, JS-ошибок тоже (скрипт не загрузился), а
         * анонимные страницы работали.
         *
         * Гейт в деплое собирает ссылки со страницы и требует, чтобы каждая
         * отдавалась. Здесь закрепляется именно сбор ссылок — та его часть,
         * которую можно проверить без сети: если он перестанет что-то
         * замечать, проверка станет молча-зелёной, то есть ровно такой же
         * бесполезной, как сигналы во время аварии.
         */
        describe('::extractAssetUrls (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->extract = new ReflectionMethod(GarnetDeployFullCommand::class, 'extractAssetUrls');
            });

            it('собирает и скрипты, и стили', function (): void {
                $html = '<html><head>'
                    . '<link rel="stylesheet" href="/assets/framework/gen/css/framework.a6d4.gen.css">'
                    . '</head><body>'
                    . '<script src="/assets/framework/gen/js/vendor-react.f1d4.gen.js"></script>'
                    . '<script async src="/assets/myapp/gen/js/foreground.foreground.0424.gen.js"></script>'
                    . '</body></html>';

                expect($this->extract->invoke(null, $html))->toBe([
                    '/assets/framework/gen/css/framework.a6d4.gen.css',
                    '/assets/framework/gen/js/vendor-react.f1d4.gen.js',
                    '/assets/myapp/gen/js/foreground.foreground.0424.gen.js',
                ]);
            });

            it('не теряет бандл приложения — именно его отсутствие и было аварией', function (): void {
                $html = '<script async src="/assets/myapp/gen/js/foreground.foreground.042499cb.gen.js"></script>';

                expect($this->extract->invoke(null, $html))
                    ->toBe(['/assets/myapp/gen/js/foreground.foreground.042499cb.gen.js']);
            });

            it('схлопывает повторы: один и тот же файл проверяется один раз', function (): void {
                $html = '<script src="/assets/a/gen/js/x.gen.js"></script>'
                    . '<script src="/assets/a/gen/js/x.gen.js"></script>';

                expect($this->extract->invoke(null, $html))->toBe(['/assets/a/gen/js/x.gen.js']);
            });

            it('игнорирует ссылки вне /assets/ — чужой CDN не наша зона ответственности', function (): void {
                $html = '<a href="/system/bookings">Брони</a>'
                    . '<script src="https://cdn.example.com/lib.js"></script>'
                    . '<script src="/assets/a/gen/js/x.gen.js"></script>';

                expect($this->extract->invoke(null, $html))->toBe(['/assets/a/gen/js/x.gen.js']);
            });

            it('возвращает пустой список, когда ассетов нет вовсе', function (): void {
                expect($this->extract->invoke(null, '<html><body>ничего</body></html>'))->toBe([]);
            });
        });
    });
}
