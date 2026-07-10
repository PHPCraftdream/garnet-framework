<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Interfaces\II18n;
use PHPCraftdream\Garnet\Kernel\Io\I18n\GarnetI18n;
use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;

class TestGarnetI18n extends GarnetI18n {
    protected static ?II18n $instance = null;

    public static function getInstance(): II18n {
        if (static::$instance === null) {
            static::$instance = new static();
            Logger::define(sys_get_temp_dir(), 'ERROR_LOGGER');
        }

        return static::$instance;
    }

    public function initData(): II18n {
        $this->addLangData('EN', [
            'hello' => 'Hello %s',
            'goodbye' => 'Goodbye',
            'world' => 'World',
        ]);

        $this->addLangData('RU', [
            'hello' => 'Привет %s',
            'goodbye' => 'До свидания',
            'world' => 'Мир',
        ]);

        return $this;
    }

    public static function resetInstance(): void {
        static::$instance = null;
    }
}

describe('GarnetI18n', function (): void {
    beforeEach(function (): void {
        // Reset static state
        $reflection = new ReflectionClass(Logger::class);
        $paramsProp = $reflection->getProperty('params');
        $paramsProp->setAccessible(true);
        $paramsProp->setValue(null, []);

        $loggersProp = $reflection->getProperty('loggers');
        $loggersProp->setAccessible(true);
        $loggersProp->setValue(null, []);

        TestGarnetI18n::resetInstance();

        // Re-define the logger after reset
        Logger::define(sys_get_temp_dir(), 'ERROR_LOGGER');
    });

    describe('lang property', function (): void {
        it('returns default language and sets new language', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            expect($i18n->getLang())->toBe('RU');

            $i18n->setLang('EN');
            expect($i18n->getLang())->toBe('EN');
        });
    });

    describe('isInitiated()', function (): void {
        it('tracks init state correctly', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            expect($i18n->isInitiated())->toBe(false);

            $i18n->setInitiated();
            expect($i18n->isInitiated())->toBe(true);

            TestGarnetI18n::resetInstance();
            TestGarnetI18n::init();
            expect(TestGarnetI18n::getInstance()->isInitiated())->toBe(true);
        });
    });

    describe('addLangData()', function (): void {
        it('adds language data', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            $i18n->addLangData('DE', ['test' => 'Test']);

            $data = $i18n->getLangData();
            expect(isset($data['DE']['test']))->toBe(true);
        });
    });

    describe('getLangData()', function (): void {
        it('returns empty array initially and all language data', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            expect($i18n->getLangData())->toBe([]);

            $i18n->addLangData('EN', ['key' => 'value']);
            $i18n->addLangData('RU', ['key' => 'значение']);

            $data = $i18n->getLangData();
            expect(count($data))->toBe(2);
        });
    });

    describe('tr()', function (): void {
        beforeEach(function (): void {
            TestGarnetI18n::init();
        });

        it('translates strings in English and Russian with and without arguments', function (): void {
            $i18n = TestGarnetI18n::getInstance();

            $i18n->setLang('EN');
            expect($i18n->tr('goodbye'))->toBe('Goodbye');
            expect($i18n->tr('hello', ['World']))->toBe('Hello World');

            $i18n->setLang('RU');
            expect($i18n->tr('goodbye'))->toBe('До свидания');
            expect($i18n->tr('hello', ['Мир']))->toBe('Привет Мир');
        });

        it('returns id for missing translations', function (): void {
            $i18n = TestGarnetI18n::getInstance();

            $i18n->setLang('FR');
            expect($i18n->tr('goodbye'))->toBe('goodbye');

            $i18n->setLang('EN');
            expect($i18n->tr('missing'))->toBe('missing');

            $i18n->addLangData('EN', ['array_key' => ['not' => 'string']]);
            expect($i18n->tr('array_key'))->toBe('array_key');
        });
    });

    describe('__call()', function (): void {
        beforeEach(function (): void {
            TestGarnetI18n::init();
        });

        it('translates using magic method with and without arguments', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            $i18n->setLang('EN');

            expect($i18n->goodbye())->toBe('Goodbye');
            expect($i18n->hello('World'))->toBe('Hello World');
        });
    });

    describe('t() static', function (): void {
        beforeEach(function (): void {
            TestGarnetI18n::init();
        });

        it('translates using static helper with and without arguments', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            $i18n->setLang('EN');
            expect(TestGarnetI18n::t('goodbye'))->toBe('Goodbye');

            $i18n->setLang('RU');
            expect(TestGarnetI18n::t('hello', ['Мир']))->toBe('Привет Мир');
        });
    });

    describe('init()', function (): void {
        it('calls initData on first call and not on subsequent calls', function (): void {
            $i18n = TestGarnetI18n::getInstance();
            expect($i18n->getLangData())->toBe([]);

            TestGarnetI18n::init();
            expect($i18n->getLangData())->not->toBe([]);

            $data1 = TestGarnetI18n::getInstance()->getLangData();
            TestGarnetI18n::init();
            $data2 = TestGarnetI18n::getInstance()->getLangData();
            expect($data1)->toBe($data2);
        });
    });
});
