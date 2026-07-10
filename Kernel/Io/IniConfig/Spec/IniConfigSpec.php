<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IniConfig\Spec {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IIniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;

    // Test class extending IniConfig for testing
    class TestIniConfig extends IniConfig {
        protected function __construct(string $filePath, string $name) {
            parent::__construct($filePath, $name);
        }

        public static function createTestInstance(string $iniContent, string $name): TestIniConfig {
            $tempFile = sys_get_temp_dir() . '/' . $name . '_' . uniqid() . '.ini';
            file_put_contents($tempFile, $iniContent);

            return new TestIniConfig($tempFile, $name);
        }
    }

    describe('IniConfig', function (): void {
        // Several nested `beforeEach` blocks below reset IniConfig's static
        // `initParams`/`items` to test define()/get()/db()/app()/email() in
        // isolation from whatever real .ini paths TestsInit/init.php defined
        // at bootstrap. None of them restored the original values, so once
        // this file's specs ran, every OTHER spec file that ran afterward
        // (in kahlan's single shared process) saw a permanently wiped
        // ENV_DB/ENV_APP/ENV_EMAIL — surfacing as spurious "Env not found"
        // failures in unrelated specs (e.g. AccountSpec.php) depending on
        // file load order. Snapshot once here and restore once after this
        // file's own specs finish, so the reset stays contained to this file.
        $realInitParams = null;
        $realItems = null;

        beforeAll(function () use (&$realInitParams, &$realItems): void {
            $reflection = new ReflectionClass(IniConfig::class);
            $initParamsProp = $reflection->getProperty('initParams');
            $initParamsProp->setAccessible(true);
            $realInitParams = $initParamsProp->getValue();

            $itemsProp = $reflection->getProperty('items');
            $itemsProp->setAccessible(true);
            $realItems = $itemsProp->getValue();
        });

        afterAll(function () use (&$realInitParams, &$realItems): void {
            $reflection = new ReflectionClass(IniConfig::class);
            $initParamsProp = $reflection->getProperty('initParams');
            $initParamsProp->setAccessible(true);
            $initParamsProp->setValue($realInitParams);

            $itemsProp = $reflection->getProperty('items');
            $itemsProp->setAccessible(true);
            $itemsProp->setValue($realItems);
        });

        describe('Constants', function (): void {
            it('has ENV_APP constant', function (): void {
                expect(IniConfig::ENV_APP)->toBe('ENV_APP');
            });

            it('has ENV_DB constant', function (): void {
                expect(IniConfig::ENV_DB)->toBe('ENV_DB');
            });

            it('has ENV_EMAIL constant', function (): void {
                expect(IniConfig::ENV_EMAIL)->toBe('ENV_EMAIL');
            });
        });

        describe('define()', function (): void {
            beforeEach(function (): void {
                // Reset static properties
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);

                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setAccessible(true);
                $itemsProp->setValue([]);
            });

            it('defines file path for environment', function (): void {
                $testFile = sys_get_temp_dir() . '/test_' . uniqid() . '.ini';
                file_put_contents($testFile, 'test_key = test_value');

                IniConfig::define($testFile, 'TEST_ENV');

                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $params = $initParamsProp->getValue();

                expect($params['TEST_ENV'])->toBe($testFile);

                unlink($testFile);
            });
        });

        describe('defineAppIni()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);
            });

            it('defines app ini file path', function (): void {
                $testFile = sys_get_temp_dir() . '/app_' . uniqid() . '.ini';
                file_put_contents($testFile, 'app_key = app_value');

                IniConfig::defineAppIni($testFile);

                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $params = $initParamsProp->getValue();

                expect($params[IniConfig::ENV_APP])->toBe($testFile);

                unlink($testFile);
            });
        });

        describe('get()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);

                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setAccessible(true);
                $itemsProp->setValue([]);
            });

            it('throws exception when environment not defined', function (): void {
                expect(function (): void {
                    IniConfig::get('UNDEFINED_ENV');
                })->toThrow();
            });

            it('throws exception when file does not exist', function (): void {
                IniConfig::define('/nonexistent/file.ini', 'TEST_ENV');

                expect(function (): void {
                    IniConfig::get('TEST_ENV');
                })->toThrow();
            });
        });

        describe('Constructor', function (): void {
            it('is protected', function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $constructor = $reflection->getConstructor();

                expect($constructor->isProtected())->toBe(true);
            });
        });

        describe('Interface implementation', function (): void {
            it('implements IIniConfig', function (): void {
                $iniContent = "key1 = value1\nkey2 = value2";
                $config = TestIniConfig::createTestInstance($iniContent, 'test1');

                expect($config)->toBeAnInstanceOf(IIniConfig::class);
                expect($config)->toBeAnInstanceOf(TestIniConfig::class);
            });
        });

        describe('Initial properties', function (): void {
            it('has data array initialized as empty', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test2');

                $reflection = new ReflectionClass($config);
                $dataProp = $reflection->getProperty('data');
                $dataProp->setAccessible(true);

                expect($dataProp->getValue($config))->toBe([]);
            });

            it('has ready property initialized as false', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test3');

                $reflection = new ReflectionClass($config);
                $readyProp = $reflection->getProperty('ready');
                $readyProp->setAccessible(true);

                expect($readyProp->getValue($config))->toBe(false);
            });
        });

        describe('paramString()', function (): void {
            it('returns string value from config', function (): void {
                $iniContent = 'name = test' . PHP_EOL . 'type = string';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_string');

                expect($config->paramString('name'))->toBe('test');
                expect($config->paramString('type'))->toBe('string');
            });

            it('returns default value when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_string2');

                expect($config->paramString('missing', 'default'))->toBe('default');
            });

            it('throws exception when key not found without default', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_string3');

                expect(function () use ($config): void {
                    $config->paramString('missing');
                })->toThrow();
            });
        });

        describe('paramInt()', function (): void {
            it('returns integer value from config', function (): void {
                $iniContent = 'count = 42' . PHP_EOL . 'index = 0';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_int');

                expect($config->paramInt('count'))->toBe(42);
                expect($config->paramInt('index'))->toBe(0);
            });

            it('returns default value when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_int2');

                expect($config->paramInt('missing', 10))->toBe(10);
            });

            it('throws exception when value is not integer', function (): void {
                $iniContent = 'text = not_a_number';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_int3');

                expect(function () use ($config): void {
                    $config->paramInt('text');
                })->toThrow();
            });

            it('throws exception when value is float', function (): void {
                $iniContent = 'decimal = 12.5';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_int4');

                expect(function () use ($config): void {
                    $config->paramInt('decimal');
                })->toThrow();
            });
        });

        describe('paramBool()', function (): void {
            it('returns true for positive integer', function (): void {
                $iniContent = 'enabled = 1';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool');

                expect($config->paramBool('enabled'))->toBe(true);
            });

            it('returns false for zero', function (): void {
                $iniContent = 'disabled = 0';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool2');

                expect($config->paramBool('disabled'))->toBe(false);
            });

            it('returns true for string "true"', function (): void {
                $iniContent = 'flag = true';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool3');

                expect($config->paramBool('flag'))->toBe(true);
            });

            it('returns false for string "false" (case-sensitive)', function (): void {
                $iniContent = 'flag = false';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool4');

                expect($config->paramBool('flag'))->toBe(false);
            });

            it('returns default value when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool5');

                expect($config->paramBool('missing'))->toBe(false);
                expect($config->paramBool('missing', true))->toBe(true);
            });

            it('returns true for any positive integer', function (): void {
                $iniContent = 'num = 5';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool6');

                expect($config->paramBool('num'))->toBe(true);
            });

            it('returns false for negative integer', function (): void {
                $iniContent = 'negative = -1';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_bool7');

                expect($config->paramBool('negative'))->toBe(false);
            });
        });

        describe('paramArray()', function (): void {
            it('returns array value from config', function (): void {
                $iniContent = 'items[] = one' . PHP_EOL . 'items[] = two' . PHP_EOL . 'items[] = three';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_array');

                $result = $config->paramArray('items');
                expect($result)->toBeAn('array');
                expect(count($result))->toBe(3);
            });

            it('returns default empty array when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_array2');

                $result = $config->paramArray('missing');
                expect($result)->toBe([]);
            });

            it('wraps scalar value in array', function (): void {
                $iniContent = 'single = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_array3');

                $result = $config->paramArray('single');
                expect($result)->toBe(['value']);
            });

            it('returns provided default array when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_array4');

                $result = $config->paramArray('missing', ['a', 'b']);
                expect($result)->toBe(['a', 'b']);
            });
        });

        describe('paramWithFlag()', function (): void {
            it('returns value and true flag when key exists', function (): void {
                $iniContent = 'option = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_flag');

                [$value, $hasValue] = $config->paramWithFlag('option');
                expect($value)->toBe('value');
                expect($hasValue)->toBe(true);
            });

            it('returns default and false flag when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_flag2');

                [$value, $hasValue] = $config->paramWithFlag('missing', 'default');
                expect($value)->toBe('default');
                expect($hasValue)->toBe(false);
            });

            it('returns null and false flag when key not found with no default', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_flag3');

                [$value, $hasValue] = $config->paramWithFlag('missing');
                expect($value)->toBe(null);
                expect($hasValue)->toBe(false);
            });
        });

        describe('set()', function (): void {
            it('sets a new parameter value', function (): void {
                $iniContent = 'existing = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_set');

                $config->set('new_key', 'new_value');
                expect($config->param('new_key'))->toBe('new_value');
            });

            it('overwrites existing parameter value', function (): void {
                $iniContent = 'key = old_value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_set2');

                $config->set('key', 'new_value');
                expect($config->param('key'))->toBe('new_value');
            });

            it('sets integer values', function (): void {
                $iniContent = '';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_set3');

                $config->set('number', 42);
                expect($config->param('number'))->toBe(42);
            });

            it('sets array values', function (): void {
                $iniContent = '';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_set4');

                $array = ['a', 'b', 'c'];
                $config->set('items', $array);
                expect($config->param('items'))->toBe($array);
            });
        });

        describe('all()', function (): void {
            it('returns all config parameters', function (): void {
                $iniContent = 'key1 = value1' . PHP_EOL . 'key2 = value2' . PHP_EOL . 'key3 = value3';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_all');

                $result = $config->all();
                expect(count($result))->toBe(3);
                expect($result['key1'])->toBe('value1');
                expect($result['key2'])->toBe('value2');
                expect($result['key3'])->toBe('value3');
            });

            it('includes values set via set()', function (): void {
                $iniContent = 'original = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_all2');

                $config->set('new_key', 'new_value');
                $result = $config->all();

                expect(isset($result['original']))->toBe(true);
                expect(isset($result['new_key']))->toBe(true);
                expect($result['new_key'])->toBe('new_value');
            });

            it('returns empty array for empty config', function (): void {
                $iniContent = '';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_all3');

                $result = $config->all();
                expect($result)->toBe([]);
            });
        });

        describe('defineDbIni()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);
            });

            it('defines db ini file path', function (): void {
                $testFile = sys_get_temp_dir() . '/db_' . uniqid() . '.ini';
                file_put_contents($testFile, 'db_key = db_value');

                IniConfig::defineDbIni($testFile);

                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $params = $initParamsProp->getValue();

                expect($params[IniConfig::ENV_DB])->toBe($testFile);

                unlink($testFile);
            });
        });

        describe('db()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);

                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setAccessible(true);
                $itemsProp->setValue([]);
            });

            it('returns db config instance', function (): void {
                $testFile = sys_get_temp_dir() . '/db_' . uniqid() . '.ini';
                file_put_contents($testFile, 'host = localhost' . PHP_EOL . 'port = 3306');

                IniConfig::defineDbIni($testFile);
                $dbConfig = IniConfig::db();

                expect($dbConfig)->toBeAnInstanceOf(IIniConfig::class);
                expect($dbConfig->param('host'))->toBe('localhost');
                expect($dbConfig->param('port'))->toBe('3306');

                unlink($testFile);
            });

            it('throws exception when db not defined', function (): void {
                expect(function (): void {
                    IniConfig::db();
                })->toThrow();
            });
        });

        describe('defineEmailIni()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);
            });

            it('defines email ini file path', function (): void {
                $testFile = sys_get_temp_dir() . '/email_' . uniqid() . '.ini';
                file_put_contents($testFile, 'email_key = email_value');

                IniConfig::defineEmailIni($testFile);

                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $params = $initParamsProp->getValue();

                expect($params[IniConfig::ENV_EMAIL])->toBe($testFile);

                unlink($testFile);
            });
        });

        describe('email()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);

                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setAccessible(true);
                $itemsProp->setValue([]);
            });

            it('returns email config instance', function (): void {
                $testFile = sys_get_temp_dir() . '/email_' . uniqid() . '.ini';
                file_put_contents($testFile, 'from = test@example.com' . PHP_EOL . 'smtp = smtp.gmail.com');

                IniConfig::defineEmailIni($testFile);
                $emailConfig = IniConfig::email();

                expect($emailConfig)->toBeAnInstanceOf(IIniConfig::class);
                expect($emailConfig->param('from'))->toBe('test@example.com');
                expect($emailConfig->param('smtp'))->toBe('smtp.gmail.com');

                unlink($testFile);
            });

            it('throws exception when email not defined', function (): void {
                expect(function (): void {
                    IniConfig::email();
                })->toThrow();
            });
        });

        describe('sshIdentityFile()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);

                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setAccessible(true);
                $itemsProp->setValue([]);
            });

            it('returns empty string when identity_file is blank', function (): void {
                $f = sys_get_temp_dir() . '/ssh_' . uniqid() . '.ini';
                file_put_contents($f, 'identity_file = ""');
                IniConfig::defineSshIni($f);
                expect(IniConfig::sshIdentityFile())->toBe('');
                unlink($f);
            });

            it('expands leading tilde', function (): void {
                $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
                $f = sys_get_temp_dir() . '/ssh_' . uniqid() . '.ini';
                file_put_contents($f, 'identity_file = "~/.ssh/mykey"');
                IniConfig::defineSshIni($f);
                expect(IniConfig::sshIdentityFile())->toBe($home . '/.ssh/mykey');
                unlink($f);
            });

            it('returns absolute path unchanged', function (): void {
                $f = sys_get_temp_dir() . '/ssh_' . uniqid() . '.ini';
                file_put_contents($f, 'identity_file = "/etc/ssh/mykey"');
                IniConfig::defineSshIni($f);
                expect(IniConfig::sshIdentityFile())->toBe('/etc/ssh/mykey');
                unlink($f);
            });

            it('resolves relative path from ssh.ini directory', function (): void {
                $dir = sys_get_temp_dir();
                $f = $dir . '/ssh_' . uniqid() . '.ini';
                file_put_contents($f, 'identity_file = "ssh_key"');
                IniConfig::defineSshIni($f);
                $expected = $dir . DIRECTORY_SEPARATOR . 'ssh_key';
                expect(IniConfig::sshIdentityFile())->toBe($expected);
                unlink($f);
            });
        });

        describe('app()', function (): void {
            beforeEach(function (): void {
                $reflection = new ReflectionClass(IniConfig::class);
                $initParamsProp = $reflection->getProperty('initParams');
                $initParamsProp->setAccessible(true);
                $initParamsProp->setValue([]);

                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setAccessible(true);
                $itemsProp->setValue([]);
            });

            it('returns app config instance', function (): void {
                $testFile = sys_get_temp_dir() . '/app_' . uniqid() . '.ini';
                file_put_contents($testFile, 'name = MyApp' . PHP_EOL . 'debug = true');

                IniConfig::defineAppIni($testFile);
                $appConfig = IniConfig::app();

                expect($appConfig)->toBeAnInstanceOf(IIniConfig::class);
                expect($appConfig->param('name'))->toBe('MyApp');
                expect($appConfig->param('debug'))->toBe('1');

                unlink($testFile);
            });

            it('throws exception when app not defined', function (): void {
                expect(function (): void {
                    IniConfig::app();
                })->toThrow();
            });
        });

        describe('param()', function (): void {
            it('returns parameter value', function (): void {
                $iniContent = 'key1 = value1' . PHP_EOL . 'key2 = value2';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_param');

                expect($config->param('key1'))->toBe('value1');
                expect($config->param('key2'))->toBe('value2');
            });

            it('returns default value when key not found', function (): void {
                $iniContent = 'key = value';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_param2');

                expect($config->param('missing'))->toBe(null);
                expect($config->param('missing', 'default'))->toBe('default');
            });

            it('returns integer values as strings (from INI file)', function (): void {
                $iniContent = 'number = 42';
                $config = TestIniConfig::createTestInstance($iniContent, 'test_param3');

                $result = $config->param('number');
                expect($result)->toBe('42'); // INI parses numbers as strings
            });
        });
    });
}
