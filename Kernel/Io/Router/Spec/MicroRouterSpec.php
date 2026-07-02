<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\BaseTest\BaseTest;
use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars4Tests;
use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
use PHPCraftdream\Garnet\Kernel\Io\Router\Router;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
use PHPCraftdream\Garnet\Kernel\Io\Router\Spec\Tools\MicroController;

function checkCalls(): void {
    expect(GlobalVars4Tests::get('lang_touchBeforeClass'))->toBe('ru');
    expect(GlobalVars4Tests::get('lang_touchAfterClass'))->toBe('ru');
    expect(GlobalVars4Tests::get('touchBeforeClass'))->toBe(true);
    expect(GlobalVars4Tests::get('touchAfterClass'))->toBe(true);
}

describe('MicroRouter', function (): void {
    it('adds a route successfully', function (): void {
        $router = new Router(
            MicroController::handlerNotFound(...)
        );
        $router->add('/hello', MicroController::class);

        /**
         * @var array<string, string> $classNames
         */
        $classNames = BaseTest::getPropertyValue($router, 'routes');

        expect(array_key_exists('/hello', $classNames))->toBe(true);
        expect($classNames['/hello'] ?? null)->toBe([MicroController::class, [], []]);
    });

    it('throws an exception when adding an existing route', function (): void {
        $router = new Router(
            MicroController::handlerNotFound(...)
        );
        $router->add('/hello', MicroController::class);

        $closure = function () use ($router): void {
            $router->add('/hello', MicroController::class);
        };
        expect($closure)->toThrow(new RouterException('Route already exists #A'));
    });

    it('dispatches a route successfully', function (): void {
        $router = new Router(
            MicroController::handlerNotFound(...)
        );

        $router->add(
            uri: '/hello/{lang}',
            className: MicroController::class,
            callBefore: [MicroController::class . '::touchBeforeClass'],
            callAfter: [MicroController::class . '::touchAfterClass']
        );

        $request = GlobalReqParams::makeGet4Tests('/hello/lang~ru');
        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('{Hello Main World!}');
        expect(GlobalVars4Tests::get('get__main'))->toBeTruthy();
        expect(GlobalVars4Tests::get('lang_get__main'))->toBe('ru');
        checkCalls();

        $request = GlobalReqParams::makeGet4Tests('/hello/lang~ru/~hello/');
        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('{Hello Hello World!}');
        expect(GlobalVars4Tests::get('get__hello'))->toBeTruthy();
        expect(GlobalVars4Tests::get('lang_get__hello'))->toBe('ru');
        checkCalls();

        $request = GlobalReqParams::makePost4Tests('/hello/lang~ru/~ok/');
        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('{Hello World Post!}');
        expect(GlobalVars4Tests::get('post__ok'))->toBeTruthy();
        expect(GlobalVars4Tests::get('lang_post__ok'))->toBe('ru');
        checkCalls();

        $request = GlobalReqParams::makePost4Tests('/hello/lang~ru/~ok/print_before_class');
        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('print_before_class');
        expect(GlobalVars4Tests::get('lang_touchBeforeClass'))->toBe('ru');
        expect(GlobalVars4Tests::get('touchBeforeClass'))->toBeTruthy();

        $request = GlobalReqParams::makePost4Tests('/hello/lang~ru/~ok');
        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('{Hello World Post!}');
        expect(GlobalVars4Tests::get('lang_touchBeforeClass'))->toBe('ru');
        expect(GlobalVars4Tests::get('touchBeforeClass'))->toBeTruthy();
    });

    it('should dispatch route not found', function (): void {
        $router = new Router(
            MicroController::handlerNotFound(...)
        );

        $request = GlobalReqParams::makeGet4Tests('/not_found');

        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('handlerNotFoundRes');
        expect(GlobalVars4Tests::get('handlerNotFound'))->toBe(true);
    });

    it('should dispatch method not found', function (): void {
        $router = new Router(
            MicroController::handlerNotFound(...)
        );

        $router->add(
            uri: '/hello/{lang}',
            className: MicroController::class,
        );

        $request = GlobalReqParams::makeGet4Tests('/hello/lang~ru/~not_found_method');

        GlobalVars4Tests::reset();
        $response = $router->dispatch($request, RouterUriParams::fromGlobals($request));
        expect($response)->toBe('handlerNotFoundRes');
        expect(GlobalVars4Tests::get('handlerNotFound'))->toBe(true);
    });
});
