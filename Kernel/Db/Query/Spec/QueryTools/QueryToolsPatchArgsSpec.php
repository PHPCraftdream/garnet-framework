<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;

describe('DbTools patchArgs function', function (): void {
    it('should return the original SQL and args when there are no parameters', function (): void {
        $sql = 'SELECT * FROM users';
        $args = [];
        expect(QueryTools::patchArgsNamed($sql, $args))->toEqual([$sql, $args]);
    });

    it('should replace ordered parameters with named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id = ? AND email = ?';
        $args = [10, 'test@example.com'];
        [$newSql, $newArgs] = QueryTools::patchArgsNamed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id = :p0 AND email = :p1';
        $expectedArgs = ['p0' => 10, 'p1' => 'test@example.com'];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });

    it('should replace ordered parameter arrays with multiple named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id IN (?)';
        $args = [[1, 2, 3]];
        [$newSql, $newArgs] = QueryTools::patchArgsNamed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id IN (:p00, :p01, :p02)';
        $expectedArgs = ['p00' => 1, 'p01' => 2, 'p02' => 3];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });

    it('should not change named parameters', function (): void {
        $sql = 'INSERT INTO users (id, email) VALUES (:id, :email)';
        $args = ['id' => 10, 'email' => 'test@example.com'];
        expect(QueryTools::patchArgsNamed($sql, $args))->toEqual([$sql, $args]);
    });

    it('should replace named parameter arrays with multiple named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id IN (:ids) AND age IN (?)';
        $args = ['ids' => [1, 2, 3], [35, 36]];
        [$newSql, $newArgs] = QueryTools::patchArgsNamed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id IN (:ids0, :ids1, :ids2) AND age IN (:p00, :p01)';
        $expectedArgs = ['p00' => 35, 'p01' => 36, 'ids0' => 1, 'ids1' => 2, 'ids2' => 3];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });

    // ################################################################################################################

    it('should replace ordered parameters with named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id = ? AND email = ?';
        $args = [10, 'test@example.com'];
        [$newSql, $newArgs] = QueryTools::patchArgsIndexed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id = ? AND email = ?';
        $expectedArgs = [10, 'test@example.com'];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });

    it('should replace ordered parameter arrays with multiple named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id IN (?)';
        $args = [[1, 2, 3]];
        [$newSql, $newArgs] = QueryTools::patchArgsIndexed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id IN (?, ?, ?)';
        $expectedArgs = [1, 2, 3];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });

    it('should not change named parameters', function (): void {
        $sql = 'INSERT INTO users (id, email) VALUES (:id, :email)';
        $args = ['id' => 10, 'email' => 'test@example.com'];

        $expectedSql = 'INSERT INTO users (id, email) VALUES (?, ?)';
        $expectedArgs = [10, 'test@example.com'];

        expect(QueryTools::patchArgsIndexed($sql, $args))->toEqual([$expectedSql, $expectedArgs]);
    });

    it('should replace named parameter arrays with multiple named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id IN (:ids) AND age IN (?)';
        $args = ['ids' => [1, 2, 3], [35, 36]];
        [$newSql, $newArgs] = QueryTools::patchArgsIndexed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id IN (?, ?, ?) AND age IN (?, ?)';
        $expectedArgs = [1, 2, 3, 35, 36];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });

    it('should replace named parameter arrays with multiple named parameters', function (): void {
        $sql = 'SELECT * FROM users WHERE id IN (:ids) AND age IN (?)';
        $args = ['ids' => [1, 2, 3], [35, 36]];
        [$newSql, $newArgs] = QueryTools::patchArgsIndexed($sql, $args);

        $expectedSql = 'SELECT * FROM users WHERE id IN (?, ?, ?) AND age IN (?, ?)';
        $expectedArgs = [1, 2, 3, 35, 36];

        expect($newSql)->toBe($expectedSql);
        expect($newArgs)->toBe($expectedArgs);
    });
});
