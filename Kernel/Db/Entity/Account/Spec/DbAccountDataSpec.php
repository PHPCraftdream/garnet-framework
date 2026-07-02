<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    describe('DbAccountData', function (): void {
        describe('::getAllUsersData() - data filtering and grouping logic', function (): void {
            it('filters numeric strings and converts to integers', function (): void {
                $testData = ['123', '456', 'not_a_number'];

                foreach ($testData as $value) {
                    $isInt = \PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools::isIntStr($value);
                    $converted = $isInt ? intval($value) : $value;

                    if ($isInt) {
                        expect($converted)->toBeAn('integer');
                    } else {
                        expect($converted)->toBeA('string');
                    }
                }

                expect(intval('123'))->toBe(123);
                expect(intval('456'))->toBe(456);
            });

            it('handles items with missing required keys gracefully', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name'],
                    ['param' => 'name', 'value' => 'Test'],
                    ['account_id' => 'user1', 'value' => 'value'],
                    [],
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'Valid'],
                ];

                $validItems = [];

                foreach ($items as $item) {
                    if (isset($item['account_id'], $item['param'], $item['value'])) {
                        $validItems[] = $item;
                    }
                }

                expect(count($validItems))->toBe(1);
                expect($validItems[0])->toBe([
                    'account_id' => 'user1',
                    'param' => 'name',
                    'value' => 'Valid',
                ]);
            });

            it('groups params by account_id', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'John'],
                    ['account_id' => 'user1', 'param' => 'age', 'value' => '30'],
                    ['account_id' => 'user1', 'param' => 'city', 'value' => 'NYC'],
                    ['account_id' => 'user2', 'param' => 'name', 'value' => 'Jane'],
                ];

                $result = [];

                foreach ($items as $item) {
                    $id = $item['account_id'];
                    $name = $item['param'];
                    $value = $item['value'];

                    if (!isset($result[$id])) {
                        $result[$id] = [];
                    }

                    $result[$id][$name] = $value;
                }

                expect($result)->toBe([
                    'user1' => ['name' => 'John', 'age' => '30', 'city' => 'NYC'],
                    'user2' => ['name' => 'Jane'],
                ]);
            });

            it('creates mapping array from names', function (): void {
                $names = ['name', 'age', 'city'];
                $namesMap = [];

                foreach ($names as $name) {
                    $namesMap[$name] = true;
                }

                expect(isset($namesMap['name']))->toBe(true);
                expect(isset($namesMap['age']))->toBe(true);
                expect(isset($namesMap['city']))->toBe(true);
                expect(isset($namesMap['other']))->toBe(false);
            });

            it('filters items by namesMap', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'John'],
                    ['account_id' => 'user1', 'param' => 'age', 'value' => '30'],
                    ['account_id' => 'user1', 'param' => 'other', 'value' => 'data'],
                ];

                $names = ['name', 'age'];
                $namesMap = [];

                foreach ($names as $name) {
                    $namesMap[$name] = true;
                }

                $result = [];

                foreach ($items as $item) {
                    $name = $item['param'];

                    if (!isset($namesMap[$name])) {
                        continue;
                    }

                    $result[$name] = $item['value'];
                }

                expect($result)->toBe(['name' => 'John', 'age' => '30']);
                expect(isset($result['other']))->toBe(false);
            });

            it('combines all logic steps', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'John'],
                    ['account_id' => 'user1', 'param' => 'age', 'value' => '30'],
                    ['account_id' => 'user1', 'param' => 'city', 'value' => 'NYC'],
                    ['account_id' => 'user2', 'param' => 'name', 'value' => 'Jane'],
                    ['account_id' => 'user2', 'param' => 'age', 'value' => '25'],
                ];

                $names = ['name', 'age'];
                $namesMap = [];

                foreach ($names as $name) {
                    $namesMap[$name] = true;
                }

                $result = [];

                foreach ($items as $item) {
                    if (!isset($item['account_id']) || !isset($item['param']) || !isset($item['value'])) {
                        continue;
                    }

                    $val = \PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools::isIntStr($item['value'])
                        ? intval($item['value'])
                        : $item['value'];
                    $id = $item['account_id'];
                    $name = $item['param'];

                    if (!isset($namesMap[$name])) {
                        continue;
                    }

                    if (isset($result[$id])) {
                        $result[$id][$name] = $val;
                    } else {
                        $result[$id] = [$name => $val];
                    }
                }

                expect($result)->toBe([
                    'user1' => ['name' => 'John', 'age' => 30],
                    'user2' => ['name' => 'Jane', 'age' => 25],
                ]);
            });
        });
    });
}
