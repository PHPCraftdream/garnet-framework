<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Support\Utils {
    use Aura\SqlQuery\Common\SelectInterface;
    use Closure;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\PageData;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams;

    class PaginationHelper {
        // Default page size shared with the frontend (`DEFAULT_PAGE_SIZE` in
        // Framework/Bundle/Front/Common/Utils/pagination.ts). When the client doesn't send
        // a `perPage` (legacy callers, smoke tests, etc.) the API should use
        // the same value the UI dropdown defaults to.
        public const DEFAULT_PER_PAGE = 10;

        public const MAX_PER_PAGE = 100;

        public const MIN_PER_PAGE = 10;

        // Request-log viewer pages large per-day buckets — needs a higher ceiling
        // than the generic admin grid (MAX_PER_PAGE).
        public const MAX_PER_PAGE_LARGE = 1000;

        // Error-log viewer ceiling — entries are heavier (raw messages), so we cap
        // it tighter than the request log to avoid blowing up response size.
        public const MAX_PER_PAGE_MEDIUM = 500;

        /**
         * Read page/perPage from POST (JSON body) or GET query params.
         *
         * @param IGlobalReqParams $globals
         * @param int $defaultPerPage
         * @return array{page: int, perPage: int}
         */
        public static function readPageParams(IGlobalReqParams $globals, int $defaultPerPage = self::DEFAULT_PER_PAGE): array {
            $page = (int)($globals->readPostValue('page', 0) ?: $globals->readGetValue('page', 1));
            $perPage = (int)($globals->readPostValue('perPage', 0) ?: $globals->readGetValue('perPage', $defaultPerPage));

            if ($page < 1) {
                $page = 1;
            }

            if ($perPage < 1) {
                $perPage = $defaultPerPage;
            }

            if ($perPage > self::MAX_PER_PAGE) {
                $perPage = self::MAX_PER_PAGE;
            }

            return ['page' => $page, 'perPage' => $perPage];
        }

        /**
         * Fetch a page of results from a DbTable with custom perPage.
         * Uses getCount + selectAll with LIMIT/OFFSET instead of selectPage (which uses table's fixed pageSize).
         *
         * @param DbTable $table
         * @param int $page
         * @param int $perPage
         * @param Closure|null $queryCallback Applied to both count and select queries
         * @return PageData
         */
        public static function fetchPage(DbTable $table, int $page, int $perPage, ?Closure $queryCallback = null): PageData {
            $count = $table->getCount($queryCallback);
            $pageData = new PageData($page, $count, $perPage);

            $items = $table->selectAll(function ($query) use ($queryCallback, $pageData, $perPage): void {
                if ($queryCallback) {
                    $queryCallback($query);
                }
                $query->limit($perPage);
                $query->offset($pageData->offset);
            });

            $pageData->pageItems = $items;
            $pageData->pageItemsCount = count($items);

            return $pageData;
        }

        /**
         * Apply a generic OR-of-LIKE search across `$searchFields` and an
         * ORDER BY validated against `$sortFields` (unknown/absent sortField
         * falls back to `$defaultOrder`) to a query — the same two knobs
         * every AdminGrid-backed list needs, so every controller doesn't
         * reinvent them.
         *
         * @param string[] $searchFields
         * @param string[] $sortFields
         */
        public static function applySearchAndSort(
            SelectInterface $q,
            string $query,
            array $searchFields,
            ?string $sortField,
            string $sortDir,
            array $sortFields,
            string $defaultOrder = 'id DESC',
        ): void {
            if ($query !== '' && !empty($searchFields)) {
                $conds = [];
                $binds = [];

                foreach ($searchFields as $i => $field) {
                    $conds[] = "{$field} LIKE :search_{$i}";
                    $binds["search_{$i}"] = '%' . $query . '%';
                }
                $q->where('(' . implode(' OR ', $conds) . ')', $binds);
            }

            if ($sortField !== null && in_array($sortField, $sortFields, true)) {
                $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
                $q->orderBy(["{$sortField} {$dir}"]);
            } else {
                $q->orderBy([$defaultOrder]);
            }
        }

        /**
         * Read `query`/`sortField`/`sortDir` from POST — the trio every
         * AdminGrid-backed list reads alongside page/perPage.
         *
         * @return array{query: string, sortField: ?string, sortDir: string}
         */
        public static function readSearchSortParams(IGlobalReqParams $globals): array {
            $query = trim((string)$globals->readPostValue('query', ''));
            $sortFieldRaw = trim((string)$globals->readPostValue('sortField', ''));
            $sortDir = (string)$globals->readPostValue('sortDir', 'asc');

            return [
                'query' => $query,
                'sortField' => $sortFieldRaw !== '' ? $sortFieldRaw : null,
                'sortDir' => strtolower($sortDir) === 'desc' ? 'desc' : 'asc',
            ];
        }

        /**
         * Convert PageData to a JSON-friendly response array.
         *
         * @param PageData $pageData
         * @param array $extras Additional keys to merge into the response
         * @return array{items: array, page: int, perPage: int, total: int, totalPages: int}
         */
        public static function toPageResponse(PageData $pageData, array $extras = []): array {
            return array_merge([
                'items' => $pageData->pageItems,
                'page' => $pageData->page,
                'perPage' => $pageData->pageSize,
                'total' => $pageData->count,
                'totalPages' => $pageData->pagesCount,
            ], $extras);
        }
    }
}
