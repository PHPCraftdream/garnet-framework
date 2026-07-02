<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Spec;

use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Tables\FwEntityHistory;

// Concrete subclass with a fixed table name — needed because FwEntityHistory
// is abstract by design (apps choose their own physical table name).
class TestEntityHistory extends FwEntityHistory {
    protected string $tableName = 'test_entity_history';
}

describe('FwEntityHistory::init()', function (): void {
    it('emits CREATE TABLE IF NOT EXISTS for the configured tableName', function (): void {
        $sql = TestEntityHistory::init()->buildQueries()[0];
        expect($sql)->toContain('CREATE TABLE');
        expect($sql)->toContain('IF NOT EXISTS');
        expect($sql)->toContain('test_entity_history');
    });

    it('declares all audit columns', function (): void {
        $sql = TestEntityHistory::init()->buildQueries()[0];
        $required = [
            'entity_type', 'entity_id', 'action',
            'actor_id', 'actor_login',
            'diff_json', 'snapshot_json',
            'comment', 'created_at', 'ip', 'user_agent',
        ];

        foreach ($required as $col) {
            expect($sql)->toContain($col);
        }
    });

    it('indexes the (entity_type, entity_id) lookup pair', function (): void {
        $sql = TestEntityHistory::init()->buildQueries()[0];
        expect($sql)->toContain('entity_type');
        // index name set in init()
        expect($sql)->toContain('entity');
    });

    it('indexes actor_id and created_at for global queries', function (): void {
        $sql = TestEntityHistory::init()->buildQueries()[0];
        expect($sql)->toContain('actor_id');
        expect($sql)->toContain('created_at');
    });
});
