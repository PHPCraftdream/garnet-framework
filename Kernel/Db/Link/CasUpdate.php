<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link;

use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
use Throwable;

/**
 * Helper for compare-and-swap (CAS) UPDATE operations that need to check
 * `affected_rows` after the query. Used to replace transactions with
 * idempotent atomic-update patterns.
 *
 * The framework's DbPool reuses links across async queries, so we cannot
 * reliably read `affected_rows` from the shared pool. Each CAS call
 * uses its own link to keep `affected_rows` deterministic.
 */
class CasUpdate {
    /**
     * Execute a SQL UPDATE/INSERT/DELETE and return affected_rows.
     */
    public static function exec(string $sql, array $params = []): int {
        $link = static::getLink();
        $link->query($sql, $params);

        return $link->getLastAffectedRows();
    }

    /**
     * Detect MySQL duplicate-key error in a thrown exception.
     * Works for both DbException-wrapped and raw mysqli errors.
     */
    public static function isDuplicateKeyError(Throwable $e): bool {
        if ($e->getCode() === 1062) {
            return true;
        }

        return str_contains($e->getMessage(), 'Duplicate entry');
    }

    protected static ?IDbMySQLiLink $sharedLink = null;

    /**
     * Get a dedicated link for CAS operations. Reuses one link across
     * calls within a single request to avoid connection churn.
     */
    protected static function getLink(): IDbMySQLiLink {
        if (static::$sharedLink === null || static::$sharedLink->isBusy()) {
            static::$sharedLink = DbPool::get()->newLink();
        }

        return static::$sharedLink;
    }
}
