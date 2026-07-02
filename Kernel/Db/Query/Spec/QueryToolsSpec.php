<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;

describe('QueryTools', function (): void {
    describe('makeInsertBatchNamed()', function (): void {
        it('generates INSERT SQL with named parameters', function (): void {
            $data = [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25],
            ];

            [$sql, $params] = QueryTools::makeInsertBatchNamed('users', $data);

            expect($sql)->toContain('INSERT INTO users');
            expect($sql)->toContain('(`name`, `age`)');
            expect($sql)->toContain('VALUES');
            expect(count($params))->toBe(4);
        });

        it('handles single record and named parameter format', function (): void {
            $data = [['field1' => 'value1']];

            [$sql, $params] = QueryTools::makeInsertBatchNamed('table', $data);

            expect($sql)->toContain(':field10');
            expect($params[':field10'])->toBe('value1');
        });

        it('handles empty data', function (): void {
            [$sql, $params] = QueryTools::makeInsertBatchNamed('test', []);

            expect($sql)->toContain('INSERT INTO test ()');
            expect($params)->toBe([]);
        });

        it('supports ON DUPLICATE KEY UPDATE clause', function (): void {
            $data = [['name' => 'Test']];

            [$sql] = QueryTools::makeInsertBatchNamed('test', $data, 'name = VALUES(name)');

            expect($sql)->toContain('ON DUPLICATE KEY UPDATE name = VALUES(name)');
        });

        it('handles INSERT DELAYED flag', function (): void {
            $data = [['name' => 'Test']];

            [$sqlDelayed] = QueryTools::makeInsertBatchNamed('test', $data, null, true);
            [$sqlRegular] = QueryTools::makeInsertBatchNamed('test', $data, null, false);

            expect($sqlDelayed)->toContain('INSERT DELAYED INTO');
            expect($sqlRegular)->toContain('INSERT INTO');
            expect($sqlRegular)->not->toContain('INSERT DELAYED');
        });

        it('handles records with different field sets', function (): void {
            $data = [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25, 'city' => 'NYC'],
                ['name' => 'Bob', 'city' => 'LA'],
            ];

            [$sql, $params] = QueryTools::makeInsertBatchNamed('users', $data);

            expect($sql)->toContain('`name`, `age`, `city`');
            expect(count($params))->toBe(7);
            expect($params[':city1'])->toBe('NYC');
            expect($params[':city2'])->toBe('LA');
        });

        it('handles special characters in values', function (): void {
            $data = [['name' => "O'Reilly", 'desc' => 'Test "quote"']];

            [$sql, $params] = QueryTools::makeInsertBatchNamed('test', $data);

            expect($params[':name0'])->toBe("O'Reilly");
            expect($params[':desc0'])->toBe('Test "quote"');
        });

        it('handles NULL values', function (): void {
            $data = [['name' => 'Test', 'value' => null]];

            [$sql, $params] = QueryTools::makeInsertBatchNamed('test', $data);

            expect($params[':value0'])->toBe(null);
        });

        it('handles large batch of records', function (): void {
            $data = [];

            for ($i = 0; $i < 100; $i++) {
                $data[] = ['id' => $i, 'name' => "User{$i}"];
            }

            [$sql, $params] = QueryTools::makeInsertBatchNamed('users', $data);

            expect(count($params))->toBe(200);
            expect($params[':id0'])->toBe(0);
            expect($params[':id99'])->toBe(99);
        });
    });

    describe('makeInsertBatchIndexed()', function (): void {
        it('generates INSERT with positional placeholders', function (): void {
            $data = [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25],
            ];

            [$sql, $params] = QueryTools::makeInsertBatchIndexed('users', $data);

            expect($sql)->toContain('INSERT INTO users');
            expect($sql)->toContain('VALUES');
            expect($sql)->toContain(', (?, ?)');
            expect(count($params))->toBe(4);
        });

        it('handles single record and NULL values', function (): void {
            $data = [
                ['name' => 'Test', 'value' => null],
            ];

            [$sql, $params] = QueryTools::makeInsertBatchIndexed('test', $data);

            expect($sql)->toContain('INSERT INTO test');
            expect(count($params))->toBe(2);
            expect($params[1])->toBe(null);
        });

        it('handles missing fields in later records', function (): void {
            $data = [
                ['name' => 'John', 'age' => 30, 'city' => 'NYC'],
                ['name' => 'Jane', 'age' => 25],
                ['name' => 'Bob', 'age' => 35, 'city' => 'LA'],
            ];

            [$sql, $params] = QueryTools::makeInsertBatchIndexed('users', $data);

            expect($sql)->toContain('`name`, `age`, `city`');
            expect(count($params))->toBe(9);
            expect($params[2])->toBe('NYC');
            expect($params[5])->toBe(null);
            expect($params[8])->toBe('LA');
        });

        it('supports ON DUPLICATE KEY UPDATE with indexed', function (): void {
            $data = [['id' => 1, 'name' => 'Test']];

            [$sql] = QueryTools::makeInsertBatchIndexed('test', $data, 'name = VALUES(name)');

            expect($sql)->toContain('ON DUPLICATE KEY UPDATE name = VALUES(name)');
        });

        it('handles INSERT DELAYED with indexed', function (): void {
            $data = [['name' => 'Test']];

            [$sqlDelayed] = QueryTools::makeInsertBatchIndexed('test', $data, null, true);
            [$sqlRegular] = QueryTools::makeInsertBatchIndexed('test', $data, null, false);

            expect($sqlDelayed)->toContain('INSERT DELAYED INTO');
            expect($sqlRegular)->toContain('INSERT INTO');
            expect($sqlRegular)->not->toContain('INSERT DELAYED');
        });
    });

    describe('escapeSqlParam()', function (): void {
        it('converts types to string representation', function (): void {
            expect(QueryTools::escapeSqlParam(42))->toBe('42');
            expect(QueryTools::escapeSqlParam(3.14))->toBe('3.14');
            expect(QueryTools::escapeSqlParam(true))->toBe('1');
            expect(QueryTools::escapeSqlParam(false))->toBe('0');
        });

        it('escapes special characters', function (): void {
            expect(QueryTools::escapeSqlParam("it's"))->toBe("it\\'s");
            expect(QueryTools::escapeSqlParam('say "hello"'))->toBe('say \\"hello\\"');
            expect(QueryTools::escapeSqlParam('back\\slash'))->toBe('back\\\\slash');
            expect(QueryTools::escapeSqlParam("hello\tworld"))->toBe('hello\\tworld');
            expect(QueryTools::escapeSqlParam("line1\nline2"))->toBe('line1\\nline2');
        });

        it('handles non-printable characters and whitespace', function (): void {
            expect(QueryTools::escapeSqlParam("test\x00"))->toBe('test ');
            expect(QueryTools::escapeSqlParam('test    multiple   spaces'))->toBe('test multiple spaces');
        });

        it('handles encoding and edge cases', function (): void {
            expect(QueryTools::escapeSqlParam("\x80\x81"))->toBe(''); // Non-UTF-8
            expect(QueryTools::escapeSqlParam(''))->toBe('');
            expect(QueryTools::escapeSqlParam('Héllo wörld'))->toBe('Héllo wörld');
        });
    });

    describe('buildSql()', function (): void {
        it('returns SQL unchanged when no args', function (): void {
            $sql = 'SELECT * FROM users';
            $result = QueryTools::buildSql($sql, []);
            expect($result)->toBe($sql);
        });

        it('replaces placeholders with escaped values', function (): void {
            // Positional placeholders
            $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
            $result = QueryTools::buildSql($sql, [42, 'John']);
            expect($result)->toBe('SELECT * FROM users WHERE id = "42" AND name = "John"');

            // Named parameters
            $sql = 'SELECT * FROM users WHERE name = :name';
            $result = QueryTools::buildSql($sql, ['name' => 'John']);
            expect($result)->toBe('SELECT * FROM users WHERE name = "John"');
        });

        it('handles NULL values', function (): void {
            $sql = 'SELECT * FROM users WHERE age = :age';
            $result = QueryTools::buildSql($sql, ['age' => null]);
            expect($result)->toBe('SELECT * FROM users WHERE age = NULL');
        });

        it('escapes string values properly', function (): void {
            $sql = 'INSERT INTO users (name) VALUES (?)';
            $result = QueryTools::buildSql($sql, ["O'Reilly"]);
            expect($result)->toBe('INSERT INTO users (name) VALUES ("O\\\'Reilly")');

            $sql = 'SELECT * FROM users WHERE name = ?';
            $testValue = 'Test"Value';
            $result = QueryTools::buildSql($sql, [$testValue]);
            expect($result)->toBe('SELECT * FROM users WHERE name = "Test\\"Value"');
        });

        it('handles boolean values', function (): void {
            $sql = 'SELECT * FROM users WHERE active = ?';
            expect(QueryTools::buildSql($sql, [true]))->toBe('SELECT * FROM users WHERE active = "1"');
            expect(QueryTools::buildSql($sql, [false]))->toBe('SELECT * FROM users WHERE active = "0"');
        });

        it('handles mixed placeholder types', function (): void {
            $sql = 'SELECT * FROM users WHERE id = :id AND name = ? AND age = :age';
            $result = QueryTools::buildSql($sql, ['id' => 1, 'John', 'age' => 25]);
            expect($result)->toBe('SELECT * FROM users WHERE id = "1" AND name = "John" AND age = "25"');
        });

        it('leaves placeholders unchanged when not in args', function (): void {
            $sql = 'SELECT * FROM users WHERE id = :id AND name = :name';
            $result = QueryTools::buildSql($sql, ['id' => 1]);
            expect($result)->toBe('SELECT * FROM users WHERE id = "1" AND name = :name');
        });

        it('handles named parameter with colon prefix', function (): void {
            $sql = 'SELECT * FROM users WHERE id = :id';
            $result = QueryTools::buildSql($sql, [':id' => 42]);
            expect($result)->toBe('SELECT * FROM users WHERE id = "42"');
        });

        it('handles multiple placeholders of same type', function (): void {
            $sql = 'SELECT * FROM users WHERE id IN (?, ?, ?)';
            $result = QueryTools::buildSql($sql, [1, 2, 3]);
            expect($result)->toBe('SELECT * FROM users WHERE id IN ("1", "2", "3")');
        });
    });

    describe('fieldVal()', function (): void {
        it('generates field assignment with different value types', function (): void {
            expect(QueryTools::fieldVal('name', 'John'))->toBe('`name` = "John"');
            expect(QueryTools::fieldVal('name', 'Test"Value'))->toBe('`name` = "Test\\"Value"');
            expect(QueryTools::fieldVal('age', 30))->toBe('`age` = "30"');
            expect(QueryTools::fieldVal('price', 19.99))->toBe('`price` = "19.99"');
        });

        it('escapes special characters', function (): void {
            expect(QueryTools::fieldVal('name', "O'Reilly"))->toBe('`name` = "O\\\'Reilly"');
            expect(QueryTools::fieldVal('path', 'C:\\Users\\test'))->toBe('`path` = "C:\\\\Users\\\\test"');
            expect(QueryTools::fieldVal('text', "line1\nline2"))->toBe('`text` = "line1\\nline2"');
        });
    });

    describe('fieldValIn()', function (): void {
        it('generates IN clause with various value types', function (): void {
            expect(QueryTools::fieldValIn('status', ['active', 'inactive']))
                ->toBe('`status` IN ("active", "inactive")');

            expect(QueryTools::fieldValIn('name', ['Test"Value', 'Doe']))
                ->toBe('`name` IN ("Test\\"Value", "Doe")');

            expect(QueryTools::fieldValIn('id', [1, 2, 3]))
                ->toBe('`id` IN ("1", "2", "3")');

            expect(QueryTools::fieldValIn('id', []))
                ->toBe('`id` IN ()');

            expect(QueryTools::fieldValIn('status', ['pending']))
                ->toBe('`status` IN ("pending")');
        });

        it('escapes special characters in array values', function (): void {
            expect(QueryTools::fieldValIn('name', ["O'Reilly", 'John']))
                ->toBe('`name` IN ("O\\\'Reilly", "John")');

            expect(QueryTools::fieldValIn('path', ['C:\\Users', 'D:\\Data']))
                ->toBe('`path` IN ("C:\\\\Users", "D:\\\\Data")');
        });

        it('handles mixed value types in array', function (): void {
            expect(QueryTools::fieldValIn('value', ['text', 123, 45.67]))
                ->toBe('`value` IN ("text", "123", "45.67")');
        });
    });

    describe('patchArgsNamed()', function (): void {
        it('replaces ? with named parameters', function (): void {
            $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
            [$newSql, $params] = QueryTools::patchArgsNamed($sql, [1, 'John']);

            expect($newSql)->toContain(':p0');
            expect($newSql)->toContain(':p1');
            expect($params['p0'])->toBe(1);
            expect($params['p1'])->toBe('John');
        });

        it('preserves existing named parameters', function (): void {
            $sql = 'SELECT * FROM users WHERE id = :id AND name = ?';
            [$newSql, $params] = QueryTools::patchArgsNamed($sql, ['id' => 1, 'John']);

            expect($newSql)->toContain(':id');
            expect($newSql)->toContain(':p0');
            expect($params['id'])->toBe(1);
        });

        it('expands array values for ? placeholders', function (): void {
            $sql = 'WHERE id IN ?';
            [$newSql, $params] = QueryTools::patchArgsNamed($sql, [[1, 2, 3]]);

            expect($newSql)->toContain('IN :p00, :p01, :p02');
            expect($params['p00'])->toBe(1);
            expect($params['p01'])->toBe(2);
            expect($params['p02'])->toBe(3);
        });

        it('handles empty array for ? placeholder', function (): void {
            $sql = 'WHERE id IN ?';
            [$newSql, $params] = QueryTools::patchArgsNamed($sql, [[]]);

            expect($newSql)->toBe('WHERE id IN ');
            expect($params)->toBe([]);
        });

        it('expands array values for named parameters', function (): void {
            $sql = 'WHERE id IN :ids';
            [$newSql, $params] = QueryTools::patchArgsNamed($sql, ['ids' => [1, 2, 3]]);

            expect($newSql)->toContain('IN :ids0, :ids1, :ids2');
            expect($params['ids0'])->toBe(1);
            expect($params['ids1'])->toBe(2);
            expect($params['ids2'])->toBe(3);
        });

        it('handles NULL values in positional args', function (): void {
            $sql = 'WHERE age = ?';
            [$newSql, $params] = QueryTools::patchArgsNamed($sql, [null]);

            expect($newSql)->toBe($sql);
            expect($params)->toBe([]);
        });
    });

    describe('patchArgsIndexed()', function (): void {
        it('replaces named parameters with positional placeholders', function (): void {
            $sql = 'SELECT * FROM users WHERE id = :id AND name = :name';
            [$newSql, $params] = QueryTools::patchArgsIndexed($sql, ['id' => 1, 'name' => 'John']);

            expect($newSql)->toContain('SELECT * FROM users WHERE id = ?');
            expect(count($params))->toBe(2);
        });

        it('expands array values for named parameters', function (): void {
            $sql = 'WHERE id IN :ids';
            [$newSql, $params] = QueryTools::patchArgsIndexed($sql, ['ids' => [1, 2, 3]]);

            expect($newSql)->toContain('IN ?, ?, ?');
            expect(count($params))->toBe(3);
        });

        it('handles NULL values', function (): void {
            $sql = 'WHERE age = :age';
            [$newSql, $params] = QueryTools::patchArgsIndexed($sql, ['age' => null]);

            expect(count($params))->toBe(1);
            expect($params[0])->toBe(null);
        });

        it('handles mixed positional and named parameters', function (): void {
            $sql = 'WHERE id = ? AND name = :name';
            [$newSql, $params] = QueryTools::patchArgsIndexed($sql, [1, 'name' => 'John']);

            expect($newSql)->toContain('WHERE id = ? AND name = ?');
            expect(count($params))->toBe(2);
            expect($params[0])->toBe(1);
            expect($params[1])->toBe('John');
        });

        it('replaces named parameter with ? when not in args', function (): void {
            $sql = 'WHERE id = :id';
            [$newSql, $params] = QueryTools::patchArgsIndexed($sql, ['other' => 'value']);

            expect($newSql)->toContain('WHERE id = ?');
            expect(count($params))->toBe(1);
            expect($params[0])->toBe(null);
        });

        it('expands arrays for positional parameters', function (): void {
            $sql = 'WHERE id IN ?';
            [$newSql, $params] = QueryTools::patchArgsIndexed($sql, [[1, 2, 3]]);

            expect($newSql)->toContain('IN ?, ?, ?');
            expect(count($params))->toBe(3);
            expect($params[0])->toBe(1);
            expect($params[2])->toBe(3);
        });
    });
});
