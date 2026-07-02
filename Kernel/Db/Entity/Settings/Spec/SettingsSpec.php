<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\Spec {
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\Settings;
    use ReflectionClass;

    describe('Settings', function (): void {
        beforeEach(function (): void {
            $reflection = new ReflectionClass(Settings::class);
            $instanceProperty = $reflection->getProperty('instance');
            $instanceProperty->setAccessible(true);
            $instanceProperty->setValue(null);
        });

        describe('get()', function (): void {
            it('returns singleton instance', function (): void {
                $settings1 = Settings::get();
                $settings2 = Settings::get();

                expect($settings1)->toBe($settings2);
            });
        });

        describe('getValue()', function (): void {
            beforeEach(function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);

                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, []);

                $readProperty = $reflection->getProperty('read');
                $readProperty->setAccessible(true);
                $readProperty->setValue($settings, true);

                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);
                $changedProperty->setValue($settings, false);

                $changedDataProperty = $reflection->getProperty('changedData');
                $changedDataProperty->setAccessible(true);
                $changedDataProperty->setValue($settings, []);

                $unsetDataProperty = $reflection->getProperty('unsetData');
                $unsetDataProperty->setAccessible(true);
                $unsetDataProperty->setValue($settings, []);

                $originalDataProperty = $reflection->getProperty('originalData');
                $originalDataProperty->setAccessible(true);
                $originalDataProperty->setValue($settings, []);
            });

            it('returns default value when param not exists', function (): void {
                $settings = Settings::get();

                $value = $settings->getValue('nonexistent', 'default_value');

                expect($value)->toBe('default_value');
            });

            it('returns empty string when param not exists and no default', function (): void {
                $settings = Settings::get();

                $value = $settings->getValue('nonexistent');

                expect($value)->toBe('');
            });

            it('returns stored value when param exists', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, ['test_param' => 'test_value']);

                $value = $settings->getValue('test_param');

                expect($value)->toBe('test_value');
            });
        });

        describe('setValue()', function (): void {
            beforeEach(function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);

                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, []);

                $readProperty = $reflection->getProperty('read');
                $readProperty->setAccessible(true);
                $readProperty->setValue($settings, true);

                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);
                $changedProperty->setValue($settings, false);

                $changedDataProperty = $reflection->getProperty('changedData');
                $changedDataProperty->setAccessible(true);
                $changedDataProperty->setValue($settings, []);

                $unsetDataProperty = $reflection->getProperty('unsetData');
                $unsetDataProperty->setAccessible(true);
                $unsetDataProperty->setValue($settings, []);

                $originalDataProperty = $reflection->getProperty('originalData');
                $originalDataProperty->setAccessible(true);
                $originalDataProperty->setValue($settings, []);
            });

            it('sets value for parameter', function (): void {
                $settings = Settings::get();

                $settings->setValue('test_param', 'test_value');

                $value = $settings->getValue('test_param');

                expect($value)->toBe('test_value');
            });

            it('does not mark as changed when value is same', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);

                $settings->setValue('test_param', 'test_value');
                $changedValue1 = $changedProperty->getValue($settings);

                $settings->setValue('test_param', 'test_value');
                $changedValue2 = $changedProperty->getValue($settings);

                expect($changedValue1)->toBe(true);
                expect($changedValue2)->toBe(false);
            });

            it('marks as changed when value changes', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);

                $settings->setValue('test_param', 'initial_value');

                expect($changedProperty->getValue($settings))->toBe(true);
            });

            it('tracks changed data in changedData array', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $changedDataProperty = $reflection->getProperty('changedData');
                $changedDataProperty->setAccessible(true);

                $settings->setValue('test_param', 'test_value');

                expect($changedDataProperty->getValue($settings))->toBe(['test_param' => 'test_value']);
            });
        });

        describe('unsetValue()', function (): void {
            beforeEach(function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);

                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, []);

                $readProperty = $reflection->getProperty('read');
                $readProperty->setAccessible(true);
                $readProperty->setValue($settings, true);

                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);
                $changedProperty->setValue($settings, false);

                $changedDataProperty = $reflection->getProperty('changedData');
                $changedDataProperty->setAccessible(true);
                $changedDataProperty->setValue($settings, []);

                $unsetDataProperty = $reflection->getProperty('unsetData');
                $unsetDataProperty->setAccessible(true);
                $unsetDataProperty->setValue($settings, []);

                $originalDataProperty = $reflection->getProperty('originalData');
                $originalDataProperty->setAccessible(true);
                $originalDataProperty->setValue($settings, []);
            });

            it('unsets value when parameter exists', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, ['test_param' => 'test_value']);

                $settings->unsetValue('test_param');

                $data = $dataProperty->getValue($settings);
                expect(array_key_exists('test_param', $data))->toBe(false);
            });

            it('does nothing when parameter does not exist', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, []);

                $unsetDataProperty = $reflection->getProperty('unsetData');
                $unsetDataProperty->setAccessible(true);

                $settings->unsetValue('nonexistent');

                expect($unsetDataProperty->getValue($settings))->toBe([]);
            });

            it('marks as unchanged when no changed data remains', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);

                $settings->setValue('test_param', 'test_value');
                $settings->unsetValue('test_param');

                expect($changedProperty->getValue($settings))->toBe(false);
            });

            it('tracks unset data in unsetData array', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, ['test_param' => 'test_value']);

                $unsetDataProperty = $reflection->getProperty('unsetData');
                $unsetDataProperty->setAccessible(true);

                $settings->unsetValue('test_param');

                expect($unsetDataProperty->getValue($settings))->toBe(['test_param' => true]);
            });
        });

        describe('getAllData()', function (): void {
            beforeEach(function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);

                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, []);

                $readProperty = $reflection->getProperty('read');
                $readProperty->setAccessible(true);
                $readProperty->setValue($settings, true);

                $changedProperty = $reflection->getProperty('changed');
                $changedProperty->setAccessible(true);
                $changedProperty->setValue($settings, false);

                $changedDataProperty = $reflection->getProperty('changedData');
                $changedDataProperty->setAccessible(true);
                $changedDataProperty->setValue($settings, []);

                $unsetDataProperty = $reflection->getProperty('unsetData');
                $unsetDataProperty->setAccessible(true);
                $unsetDataProperty->setValue($settings, []);

                $originalDataProperty = $reflection->getProperty('originalData');
                $originalDataProperty->setAccessible(true);
                $originalDataProperty->setValue($settings, []);
            });

            it('returns all data', function (): void {
                $settings = Settings::get();

                $reflection = new ReflectionClass($settings);
                $dataProperty = $reflection->getProperty('data');
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($settings, ['param1' => 'value1', 'param2' => 'value2']);

                $data = $settings->getAllData();

                expect($data)->toBe(['param1' => 'value1', 'param2' => 'value2']);
            });

            it('returns empty array when no data', function (): void {
                $settings = Settings::get();

                $data = $settings->getAllData();

                expect($data)->toBe([]);
            });
        });
    });
}
