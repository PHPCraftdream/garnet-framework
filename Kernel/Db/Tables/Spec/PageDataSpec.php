<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Tables\PageData;

describe('PageData', function (): void {
    describe('constructor', function (): void {
        it('calculates offset for different pages', function (): void {
            $page1 = new PageData(1, 100, 10);
            expect($page1->offset)->toBe(0);

            $page2 = new PageData(2, 100, 10);
            expect($page2->offset)->toBe(10);

            $page5 = new PageData(5, 100, 10);
            expect($page5->offset)->toBe(40);
        });

        it('calculates pages count with rounding up', function (): void {
            $pageData = new PageData(1, 100, 10);
            expect($pageData->pagesCount)->toBe(10);

            $pageData95 = new PageData(1, 95, 10);
            expect($pageData95->pagesCount)->toBe(10);
        });

        it('stores all parameters', function (): void {
            $pageData = new PageData(5, 250, 25);
            expect($pageData->page)->toBe(5);
            expect($pageData->count)->toBe(250);
            expect($pageData->pageSize)->toBe(25);
        });

        it('defaults to DEFAULT_PAGE_SIZE', function (): void {
            $pageData = new PageData(1, 100);
            expect($pageData->pageSize)->toBe(PageData::DEFAULT_PAGE_SIZE);
        });

        it('handles edge cases: empty, zero page, negative count', function (): void {
            $pageData = new PageData(0, 100, 10);
            expect($pageData->offset)->toBe(0);

            $pageData2 = new PageData(1, -1, 10);
            expect($pageData2->count)->toBe(0);
        });

        it('minimum pages count is 1 even with no items', function (): void {
            $pageData = new PageData(1, 0, 10);
            expect($pageData->pagesCount)->toBe(1);
            expect($pageData->offset)->toBe(0);
        });

        it('handles zero page size', function (): void {
            $pageData = new PageData(1, 100, 0);
            expect($pageData->pagesCount)->toBe(1);
        });
    });

    describe('DEFAULT_PAGE_SIZE', function (): void {
        it('is defined as 20', function (): void {
            expect(PageData::DEFAULT_PAGE_SIZE)->toBe(20);
        });
    });

    describe('pageItems', function (): void {
        it('can store and retrieve items', function (): void {
            $pageData = new PageData(1, 100, 10);
            expect($pageData->pageItems)->toBe([]);

            $pageData->pageItems = ['item1', 'item2'];
            $pageData->pageItemsCount = 2; // Must be set explicitly
            expect($pageData->pageItems)->toBe(['item1', 'item2']);
            expect($pageData->pageItemsCount)->toBe(2);
        });
    });
});
