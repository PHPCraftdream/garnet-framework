<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\I18n\Spec {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use PHPCraftdream\Garnet\Kernel\Io\I18n\I18nFrontendDumper;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    describe('I18nFrontendDumper', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_i18n_test_' . uniqid();
            mkdir($this->tempDir, 0o777, true);

            $this->langData = [
                'RU' => [
                    'hello' => 'Привет',
                    'goodbye' => 'До свидания',
                    'welcome' => 'Добро пожаловать',
                ],
                'EN' => [
                    'hello' => 'Hello',
                    'goodbye' => 'Goodbye',
                    'welcome' => 'Welcome',
                ],
            ];

            $this->templateDir = dirname(__DIR__, 3) . '/Templates/CodeFiles/';
        });

        afterEach(function (): void {
            if (is_dir($this->tempDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    if ($fileinfo->isDir()) {
                        rmdir($fileinfo->getPathname());
                    } else {
                        unlink($fileinfo->getPathname());
                    }
                }
                rmdir($this->tempDir);
            }
        });

        describe('dump()', function (): void {
            it('returns early when directory does not exist', function (): void {
                $nonExistentDir = $this->tempDir . '/non_existent';

                I18nFrontendDumper::dump($this->langData, $nonExistentDir, 'TestBundle', false);

                expect(is_dir($nonExistentDir))->toBe(false);
            });

            it('returns early when realpath returns false', function (): void {
                $symlinkDir = $this->tempDir . '/broken_symlink';

                I18nFrontendDumper::dump($this->langData, $symlinkDir, 'TestBundle', false);

                expect(is_dir($symlinkDir))->toBe(false);
            });

            it('creates individual language data files', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                expect(file_exists($this->tempDir . '/I18nDataRU.ts'))->toBe(true);
                expect(file_exists($this->tempDir . '/I18nDataEN.ts'))->toBe(true);
            });

            it('creates language data files with correct content', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                $ruContent = file_get_contents($this->tempDir . '/I18nDataRU.ts');
                expect($ruContent)->toContain('export class I18nDataRU');
                expect($ruContent)->toContain("static lang = 'RU';");
                expect($ruContent)->toContain("static hello = 'Привет';");
                expect($ruContent)->toContain("static goodbye = 'До свидания';");
                expect($ruContent)->toContain("static welcome = 'Добро пожаловать';");

                $enContent = file_get_contents($this->tempDir . '/I18nDataEN.ts');
                expect($enContent)->toContain('export class I18nDataEN');
                expect($enContent)->toContain("static lang = 'EN';");
                expect($enContent)->toContain("static hello = 'Hello';");
                expect($enContent)->toContain("static goodbye = 'Goodbye';");
                expect($enContent)->toContain("static welcome = 'Welcome';");
            });

            it('escapes single quotes in translation values', function (): void {
                $langData = [
                    'EN' => [
                        "it's" => "It's working",
                        "don't" => "Don't worry",
                    ],
                ];

                I18nFrontendDumper::dump($langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nDataEN.ts');
                expect($content)->toContain("static it's = 'It\\'s working';");
                expect($content)->toContain("static don't = 'Don\\'t worry';");
            });

            it('skips case-insensitive lang key in language data files', function (): void {
                $langData = [
                    'EN' => [
                        'lang' => 'English',
                        'hello' => 'Hello',
                        'Lang' => 'Lang MixedCase',
                        'LANG' => 'LANG Uppercase',
                    ],
                ];

                I18nFrontendDumper::dump($langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nDataEN.ts');
                expect($content)->toContain("static lang = 'EN';");
                expect($content)->not->toContain("static lang = 'English';");
                expect($content)->not->toContain("static Lang = 'Lang MixedCase';");
                expect($content)->not->toContain("static LANG = 'LANG Uppercase';");
                expect($content)->toContain("static hello = 'Hello';");
            });

            it('creates main I18n class file', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                expect(file_exists($this->tempDir . '/I18nTestBundle.ts'))->toBe(true);
            });

            it('creates main I18n class with correct imports', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nTestBundle.ts');
                expect($content)->toContain("import {I18nDataRU} from './I18nDataRU';");
                expect($content)->toContain("import {I18nDataEN} from './I18nDataEN';");
                expect($content)->toContain("import {I18nBase} from '@common/Utils/I18nBase';");
            });

            it('creates main I18n class with correct template replacements', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nTestBundle.ts');
                expect($content)->toContain('type t = (args?: (string|number)[]) => string;');
                expect($content)->toContain('class I18n extends I18nBase {');
                expect($content)->toContain('export const I18nTestBundle = new I18n([I18nDataRU, I18nDataEN]);');
            });

            it('creates translation methods in main I18n class', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nTestBundle.ts');
                expect($content)->toContain("    hello: t = (a = []) => this.t('hello', a);");
                expect($content)->toContain("    goodbye: t = (a = []) => this.t('goodbye', a);");
                expect($content)->toContain("    welcome: t = (a = []) => this.t('welcome', a);");
            });

            it('returns early when methods array is empty', function (): void {
                $langData = [
                    'EN' => [
                        'lang' => 'English',
                    ],
                ];

                I18nFrontendDumper::dump($langData, $this->tempDir, 'TestBundle', false);

                expect(file_exists($this->tempDir . '/I18nTestBundle.ts'))->toBe(false);
                expect(file_exists($this->tempDir . '/I18nDataEN.ts'))->toBe(false);
            });

            it('works correctly for framework bundle', function (): void {
                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'Framework', true);

                expect(file_exists($this->tempDir . '/I18nFramework.ts'))->toBe(true);
                expect(file_exists($this->tempDir . '/I18nDataRU.ts'))->toBe(true);
                expect(file_exists($this->tempDir . '/I18nDataEN.ts'))->toBe(true);

                $content = file_get_contents($this->tempDir . '/I18nFramework.ts');
                expect($content)->toContain('export const I18nFramework = new I18n([I18nDataRU, I18nDataEN]);');
            });

            it('throws exception when template file cannot be read', function (): void {
                $backupDir = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'CodeFiles' . DIRECTORY_SEPARATOR;
                $templatePath = $backupDir . 'TrTs.template';
                $backupPath = $backupDir . 'TrTs.template.bak';

                // Temporarily remove template to trigger exception
                if (file_exists($templatePath)) {
                    rename($templatePath, $backupPath);
                }

                try {
                    // Need to have valid data with methods to trigger template read
                    expect(function (): void {
                        I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);
                    })->toThrow(new CommonException('Fail on read template'));
                } finally {
                    // Restore template
                    if (file_exists($backupPath)) {
                        rename($backupPath, $templatePath);
                    }
                }
            });

            it('handles multiple languages with different keys', function (): void {
                $langData = [
                    'RU' => [
                        'hello' => 'Привет',
                        'greet' => 'Здравствуй',
                    ],
                    'EN' => [
                        'hello' => 'Hello',
                        'farewell' => 'Farewell',
                    ],
                    'FR' => [
                        'bonjour' => 'Bonjour',
                        'aurevoir' => 'Au revoir',
                    ],
                ];

                I18nFrontendDumper::dump($langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nTestBundle.ts');

                expect($content)->toContain("    hello: t = (a = []) => this.t('hello', a);");
                expect($content)->toContain("    greet: t = (a = []) => this.t('greet', a);");
                expect($content)->toContain("    farewell: t = (a = []) => this.t('farewell', a);");
                expect($content)->toContain("    bonjour: t = (a = []) => this.t('bonjour', a);");
                expect($content)->toContain("    aurevoir: t = (a = []) => this.t('aurevoir', a);");
            });

            it('handles special characters in translation values', function (): void {
                $langData = [
                    'EN' => [
                        'newlines' => "Line1\nLine2\nLine3",
                        'tabs' => "Tab1\tTab2",
                    ],
                ];

                I18nFrontendDumper::dump($langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nDataEN.ts');
                expect($content)->toContain("static newlines = 'Line1\nLine2\nLine3';");
                expect($content)->toContain("static tabs = 'Tab1\tTab2';");
            });

            it('overwrites existing files', function (): void {
                // Create initial file
                file_put_contents($this->tempDir . '/I18nTestBundle.ts', 'old content');
                file_put_contents($this->tempDir . '/I18nDataRU.ts', 'old content');

                I18nFrontendDumper::dump($this->langData, $this->tempDir, 'TestBundle', false);

                $content = file_get_contents($this->tempDir . '/I18nTestBundle.ts');
                expect($content)->not->toContain('old content');
                expect($content)->toContain('I18nTestBundle');
            });
        });
    });
}
