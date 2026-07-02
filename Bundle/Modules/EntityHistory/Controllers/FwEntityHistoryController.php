<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\EntityHistoryService;
    use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Tables\FwEntityHistory;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    /**
     * Generic JSON endpoint that returns the recent history of any
     * registered entity. Apps subclass this and supply:
     *
     *  - the concrete history table class (subclass of FwEntityHistory)
     *  - the list of entity-type strings the caller is allowed to view
     *  - moderator/owner gating via the inherited base
     *
     * Single endpoint by design: post__list. The frontend is fully
     * generic — it only passes (entity_type, entity_id) and renders
     * whatever rows the controller returns.
     */
    abstract class FwEntityHistoryController extends FwDashboardController {
        /**
         * @return class-string<FwEntityHistory>
         */
        abstract protected static function historyTableClass(): string;

        /**
         * Whitelist of entity_type tokens that may be queried via this
         * endpoint. Anything not listed returns 400. Empty array = allow
         * everything (use only for owner-only contexts).
         *
         * @return array<int, string>
         */
        protected static function allowedEntityTypes(): array {
            return [];
        }

        public static function post__list(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $entityType = trim((string)$globals->readPostValue('entity_type', ''));
            $entityId = trim((string)$globals->readPostValue('entity_id', ''));
            $limit = max(1, min(500, (int)$globals->readPostValue('limit', '100')));
            $offset = max(0, (int)$globals->readPostValue('offset', '0'));

            if ($entityType === '' || $entityId === '') {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $allowed = static::allowedEntityTypes();

            if (!empty($allowed) && !in_array($entityType, $allowed, true)) {
                return ControllerTools::JSON(['error' => 'Unknown entity type'], status: 400);
            }

            $rows = EntityHistoryService::list(
                tableClass: static::historyTableClass(),
                entityType: $entityType,
                entityId: $entityId,
                limit: $limit,
                offset: $offset,
            );

            // Resolve actor display data in one batch query.
            $actorIds = array_values(array_unique(array_filter(array_map(
                static fn ($r) => (int)($r['actor_id'] ?? 0),
                $rows,
            ))));
            $actorMap = static::resolveActorMap($actorIds);

            foreach ($rows as &$row) {
                $aid = (int)($row['actor_id'] ?? 0);
                $row['actor_name'] = $actorMap[$aid]['name'] ?? '';
                $row['actor_login_resolved'] = $actorMap[$aid]['login'] ?? (string)($row['actor_login'] ?? '');
            }

            return ControllerTools::JSON([
                'success' => true,
                'rows' => $rows,
            ]);
        }

        /**
         * @param array<int, int> $actorIds
         * @return array<int, array<string, mixed>>
         */
        protected static function resolveActorMap(array $actorIds): array {
            if (empty($actorIds)) {
                return [];
            }

            $accounts = Account::getAccounts(
                selectCallback: static function (\Aura\SqlQuery\Common\SelectInterface $select) use ($actorIds): void {
                    $select->resetCols();
                    $select->cols(['id', 'login', 'name']);
                    $select->where('id IN (?)', [array_map('intval', $actorIds)]);
                },
                accountDataFields: [],
            );

            $map = [];

            foreach ($accounts as $a) {
                $map[(int)$a['id']] = $a;
            }

            return $map;
        }
    }
}
