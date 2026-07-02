<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils {
    use Closure;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\PageData;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;

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
