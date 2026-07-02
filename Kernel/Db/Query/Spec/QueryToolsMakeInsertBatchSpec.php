<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;

describe('DbTools makeInsertBatch function', function (): void {
    it('should create a valid SQL query with multiple rows', function (): void {
        $table = 'users';
        $queryData = [
            [
                'name' => 'John',
                'age' => 25,
                'gender' => 'male'
            ],
            [
                'name' => 'Jane',
                'age' => 30,
                'gender' => 'female'
            ]
        ];
        $onDuplicateKey = 'name = VALUES(name)';

        [$sql, $params] = QueryTools::makeInsertBatchNamed($table, $queryData, $onDuplicateKey, true);

        $values = '(:name0, :age0, :gender0), (:name1, :age1, :gender1)';

        expect($sql)->toBe("INSERT DELAYED INTO users (`name`, `age`, `gender`) VALUES {$values} ON DUPLICATE KEY UPDATE {$onDuplicateKey}");
        expect($params)->toBe([
            ':name0' => 'John',
            ':age0' => 25,
            ':gender0' => 'male',
            ':name1' => 'Jane',
            ':age1' => 30,
            ':gender1' => 'female'
        ]);
    });

    it('should create a valid SQL query with single field table', function (): void {
        $table = 'orders';
        $queryData = [
            [
                'product_name' => 'Product 1',
                'price' => 100
            ],
            [
                'product_name' => 'Product 2',
                'price' => 200
            ]
        ];

        [$sql, $params] = QueryTools::makeInsertBatchNamed($table, $queryData, null, false);

        expect($sql)->toBe('INSERT INTO orders (`product_name`, `price`) VALUES (:product_name0, :price0), (:product_name1, :price1)');
        expect($params)->toBe([
            ':product_name0' => 'Product 1',
            ':price0' => 100,
            ':product_name1' => 'Product 2',
            ':price1' => 200
        ]);
    });

    // ################################################################################################################

    it('should create a valid SQL query with multiple rows', function (): void {
        $table = 'users';
        $queryData = [
            [
                'name' => 'John',
                'age' => 25,
                'gender' => 'male'
            ],
            [
                'name' => 'Jane',
                'age' => 30,
                'gender' => 'female'
            ]
        ];
        $onDuplicateKey = 'name = VALUES(name)';

        [$sql, $params] = QueryTools::makeInsertBatchIndexed($table, $queryData, $onDuplicateKey, true);

        $values = '(?, ?, ?), (?, ?, ?)';

        expect($sql)->toBe("INSERT DELAYED INTO users (`name`, `age`, `gender`) VALUES {$values} ON DUPLICATE KEY UPDATE {$onDuplicateKey}");
        expect($params)->toBe([
            'John',
            25,
            'male',
            'Jane',
            30,
            'female'
        ]);
    });

    it('should create a valid SQL query with single field table', function (): void {
        $table = 'orders';
        $queryData = [
            [
                'product_name' => 'Product 1',
                'price' => 100
            ],
            [
                'product_name' => 'Product 2',
                'price' => 200
            ]
        ];

        [$sql, $params] = QueryTools::makeInsertBatchIndexed($table, $queryData, null, false);

        expect($sql)->toBe('INSERT INTO orders (`product_name`, `price`) VALUES (?, ?), (?, ?)');
        expect($params)->toBe([
            'Product 1',
            100,
            'Product 2',
            200
        ]);
    });
});
