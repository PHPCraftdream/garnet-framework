<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils\Spec {
    use Mockery;
    use PHPCraftdream\Garnet\Bundle\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\PageData;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;

    describe('PaginationHelper', function (): void {
        afterEach(function (): void {
            Mockery::close();
        });

        describe('readPageParams()', function (): void {
            it('returns defaults when no params are provided', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(0);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(0);
                $globals->allows('readGetValue')->with('page', 1)->andReturn(1);
                $globals->allows('readGetValue')->with('perPage', PaginationHelper::DEFAULT_PER_PAGE)
                    ->andReturn(PaginationHelper::DEFAULT_PER_PAGE);

                $result = PaginationHelper::readPageParams($globals);
                expect($result['page'])->toBe(1);
                expect($result['perPage'])->toBe(PaginationHelper::DEFAULT_PER_PAGE);
            });

            it('reads page and perPage from POST', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(3);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(25);
                $globals->allows('readGetValue')->andReturn(1);

                $result = PaginationHelper::readPageParams($globals);
                expect($result['page'])->toBe(3);
                expect($result['perPage'])->toBe(25);
            });

            it('falls back to GET when POST returns 0', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(0);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(0);
                $globals->allows('readGetValue')->with('page', 1)->andReturn(5);
                $globals->allows('readGetValue')->with('perPage', PaginationHelper::DEFAULT_PER_PAGE)->andReturn(50);

                $result = PaginationHelper::readPageParams($globals);
                expect($result['page'])->toBe(5);
                expect($result['perPage'])->toBe(50);
            });

            it('clamps page to 1 when negative', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(-5);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(10);
                $globals->allows('readGetValue')->andReturn(1);

                $result = PaginationHelper::readPageParams($globals);
                expect($result['page'])->toBe(1);
            });

            it('clamps perPage to default when below 1', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(1);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(-10);
                $globals->allows('readGetValue')->andReturn(1);

                $result = PaginationHelper::readPageParams($globals);
                expect($result['perPage'])->toBe(PaginationHelper::DEFAULT_PER_PAGE);
            });

            it('caps perPage at MAX_PER_PAGE', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(1);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(999);
                $globals->allows('readGetValue')->andReturn(1);

                $result = PaginationHelper::readPageParams($globals);
                expect($result['perPage'])->toBe(PaginationHelper::MAX_PER_PAGE);
            });

            it('uses custom defaultPerPage', function (): void {
                $globals = Mockery::mock(IGlobalReqParams::class);
                $globals->allows('readPostValue')->with('page', 0)->andReturn(0);
                $globals->allows('readPostValue')->with('perPage', 0)->andReturn(0);
                $globals->allows('readGetValue')->with('page', 1)->andReturn(1);
                $globals->allows('readGetValue')->with('perPage', 50)->andReturn(50);

                $result = PaginationHelper::readPageParams($globals, 50);
                expect($result['perPage'])->toBe(50);
            });
        });

        describe('toPageResponse()', function (): void {
            it('converts PageData to a response array', function (): void {
                $pd = new PageData(2, 45, 10);
                $pd->pageItems = [['id' => 1], ['id' => 2]];

                $result = PaginationHelper::toPageResponse($pd);
                expect($result['items'])->toBe([['id' => 1], ['id' => 2]]);
                expect($result['page'])->toBe(2);
                expect($result['perPage'])->toBe(10);
                expect($result['total'])->toBe(45);
                expect($result['totalPages'])->toBe(5);
            });

            it('merges extra keys into response', function (): void {
                $pd = new PageData(1, 10, 10);
                $pd->pageItems = [];

                $result = PaginationHelper::toPageResponse($pd, ['filter' => 'active']);
                expect($result['filter'])->toBe('active');
                expect(array_key_exists('items', $result))->toBe(true);
            });
        });

        describe('constants', function (): void {
            it('has sensible constant values', function (): void {
                expect(PaginationHelper::DEFAULT_PER_PAGE)->toBeA('integer');
                expect(PaginationHelper::MAX_PER_PAGE)->toBeGreaterThan(PaginationHelper::DEFAULT_PER_PAGE);
                expect(PaginationHelper::MIN_PER_PAGE <= PaginationHelper::DEFAULT_PER_PAGE)->toBe(true);
                expect(PaginationHelper::MAX_PER_PAGE_LARGE)->toBeGreaterThan(PaginationHelper::MAX_PER_PAGE);
            });
        });
    });
}
