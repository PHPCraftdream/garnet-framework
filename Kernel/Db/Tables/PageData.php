<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables {
    class PageData {
        public const DEFAULT_PAGE_SIZE = 20;

        public int $offset = 0;

        public int $pagesCount = 1;

        public int $pageItemsCount = 0;

        public array $pageItems = [];

        public function __construct(
            public int $page,
            public int $count,
            public int $pageSize = PageData::DEFAULT_PAGE_SIZE
        ) {
            $pagesCount = $pageSize > 0 ? (int)ceil($count / $pageSize) : 1;

            if ($pagesCount < 1) {
                $pagesCount = 1;
            }

            $offset = ($page > 0) && ($count > 0) ? ($page - 1) * $pageSize : 0;
            $count = $count >= 1 ? $count : 0;

            $this->count = $count;
            $this->offset = $offset;
            $this->pagesCount = $pagesCount;
        }
    }
}
