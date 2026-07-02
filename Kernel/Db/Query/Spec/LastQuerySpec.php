<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Query\LastQuery;

describe('LastQuery', function (): void {
    describe('constructor', function (): void {
        it('creates with SQL and params', function (): void {
            $lastQuery = new LastQuery('SELECT * FROM users WHERE id = ?', [1]);
            expect($lastQuery->getSql())->toBe('SELECT * FROM users WHERE id = ?');
            expect($lastQuery->getParams())->toBe([1]);
        });

        it('creates with empty params', function (): void {
            $lastQuery = new LastQuery('SELECT * FROM users', []);
            expect($lastQuery->getSql())->toBe('SELECT * FROM users');
            expect($lastQuery->getParams())->toBe([]);
        });
    });

    describe('getSql()', function (): void {
        it('returns the SQL query', function (): void {
            $lastQuery = new LastQuery('INSERT INTO users (name) VALUES (?)', ['test']);
            expect($lastQuery->getSql())->toBe('INSERT INTO users (name) VALUES (?)');
        });
    });

    describe('getParams()', function (): void {
        it('returns the query parameters', function (): void {
            $lastQuery = new LastQuery('SELECT * FROM users WHERE id = ? AND status = ?', [1, 'active']);
            expect($lastQuery->getParams())->toBe([1, 'active']);
        });

        it('returns empty array when no params', function (): void {
            $lastQuery = new LastQuery('SELECT 1', []);
            expect($lastQuery->getParams())->toBe([]);
        });
    });
});
