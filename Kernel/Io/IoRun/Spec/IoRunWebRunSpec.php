<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun\Spec;

use Closure;
use Mockery as M;
use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars4Tests;
use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Interfaces\ISession;
use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;
use PHPCraftdream\Garnet\Kernel\Io\IoRun\IoRunWeb;
use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

if (!class_exists('IoRunWebRunSpec', false)) {
    class IoRunWebRunSpec extends IoRunWeb {
        protected static ?ISession $testSessionMock = null;

        public static function resetTestSession(): void {
            self::$testSessionMock = null;
        }

        /**
         * @return ISession
         */
        protected static function getSession(): ISession {
            if (self::$testSessionMock !== null) {
                return self::$testSessionMock;
            }

            // Create a mock for ISession
            $sessionMock = M::mock(ISession::class);

            // Teach the mock to respond to method calls
            $sessionMock->shouldReceive('readFromServer')
                ->zeroOrMoreTimes()
                ->andReturnUsing(function ($server): void {
                    GlobalVars4Tests::set('readFromServer', $server);
                });

            $sessionMock->shouldReceive('patchResponse')
                ->zeroOrMoreTimes()
                ->andReturnUsing(function (ResponseInterface $response) {
                    $response = $response->withAddedHeader('header_value', 'ok');
                    GlobalVars4Tests::set('patchResponse', true);

                    return $response;
                });

            $sessionMock->shouldReceive('isReadCookies')
                ->zeroOrMoreTimes()
                ->andReturn(false);

            // The code also calls readDataAsync()
            $sessionMock->shouldReceive('readDataAsync')
                ->zeroOrMoreTimes()
                ->andReturnNull();

            // IoRunWeb::run hydrates the session via readDataAsyncPollFinishAll()
            // before delegating into $init — see IoRunWeb::run().
            $sessionMock->shouldReceive('readDataAsyncPollFinishAll')
                ->zeroOrMoreTimes()
                ->andReturnNull();

            self::$testSessionMock = $sessionMock;

            return $sessionMock;
        }

        /**
         * @param IGlobalReqParams $globals
         * @param Closure(IGlobalReqParams $globals, IRouterUriParams $uriParams):(ResponseInterface|string|null) $init
         * @param Closure(IGlobalReqParams $globals, IRouterUriParams $params, string $error): ResponseInterface $errorCallBack
         * @return ResponseInterface
         * @throws RouterException
         */
        protected static function getResponse(
            IGlobalReqParams $globals,
            Closure $init,
            Closure $errorCallBack,
        ): ResponseInterface {
            GlobalVars4Tests::set('getResponse', true);

            $uriParams = RouterUriParams::fromGlobals($globals);

            try {
                return $init($globals, $uriParams);
            } catch (Throwable $e) {
                $message = ErrorCatcher::getExceptionStrResult($e);

                return $errorCallBack($globals, $uriParams, $message);
            }
        }

        /**
         * @return void
         */
        protected static function flushAppData(): void {
            GlobalVars4Tests::set('flushAppData', true);
        }

        /**
         * @param ResponseInterface $response
         * @return void
         */
        public static function closeConnection(ResponseInterface $response): void {
            GlobalVars4Tests::set('IoRunWebTestResponse', $response);
        }
    }
}

describe('IoRunWeb', function (): void {
    beforeEach(function (): void {
        M::close();
        IoRunWebRunSpec::resetTestSession();
    });

    afterEach(function (): void {
        M::close();
    });

    $init = static function (IGlobalReqParams $globals, IRouterUriParams $uriParams): ResponseInterface|string|null {
        GlobalVars4Tests::set('$init', true);

        if ($globals->readGetValue('error')) {
            throw new RuntimeException('RuntimeException');
        }

        return ControllerTools::ok('getResponse');
    };

    $errorCallBack = static function (IGlobalReqParams $globals, IRouterUriParams $uriParams): ResponseInterface|string|null {
        GlobalVars4Tests::set('$errorCallBack', true);

        return ControllerTools::ok('errorCallBack');
    };

    it('IoRunWeb::run - handles success and error cases', function () use ($init, $errorCallBack): void {
        $_server = [];

        // Success case
        GlobalVars4Tests::reset();
        IoRunWebRunSpec::run(
            GlobalReqParams::from($_server, ['error' => false], [], [], []),
            $init,
            $errorCallBack
        );

        expect(GlobalVars4Tests::get('$errorCallBack'))->toBe(null);
        expect(GlobalVars4Tests::get('getResponse'))->toBe(true);
        expect(GlobalVars4Tests::get('$init'))->toBe(true);
        expect(GlobalVars4Tests::get('patchResponse'))->toBe(true);
        expect(GlobalVars4Tests::get('flushAppData'))->toBe(true);
        expect(GlobalVars4Tests::get('readFromServer'))->toBe($_server);

        $response = GlobalVars4Tests::get('IoRunWebTestResponse');
        expect($response->getBody()->__toString())->toBe('getResponse');
        expect($response->getHeader('header_value'))->toBe(['ok']);

        // Error case
        GlobalVars4Tests::reset();
        IoRunWebRunSpec::run(
            GlobalReqParams::from($_server, ['error' => true], [], [], []),
            $init,
            $errorCallBack
        );

        expect(GlobalVars4Tests::get('$errorCallBack'))->toBe(true);
        expect(GlobalVars4Tests::get('getResponse'))->toBe(true);
        expect(GlobalVars4Tests::get('$init'))->toBe(true);
        expect(GlobalVars4Tests::get('patchResponse'))->toBe(true);
        expect(GlobalVars4Tests::get('flushAppData'))->toBe(true);

        $response = GlobalVars4Tests::get('IoRunWebTestResponse');
        expect($response->getBody()->__toString())->toBe('errorCallBack');
        expect($response->getHeader('header_value'))->toBe(['ok']);
    });

    it('session mock is used correctly in run', function () use ($init, $errorCallBack): void {
        GlobalVars4Tests::reset();
        $_server = [];

        // First run
        IoRunWebRunSpec::run(
            GlobalReqParams::from($_server, ['error' => false], [], [], []),
            $init,
            $errorCallBack
        );

        $response1 = GlobalVars4Tests::get('IoRunWebTestResponse');

        // Reset and run again - should use the same mock
        GlobalVars4Tests::reset();
        IoRunWebRunSpec::run(
            GlobalReqParams::from($_server, ['error' => false], [], [], []),
            $init,
            $errorCallBack
        );

        $response2 = GlobalVars4Tests::get('IoRunWebTestResponse');

        // Both responses should be correct
        expect($response1->getBody()->__toString())->toBe('getResponse');
        expect($response2->getBody()->__toString())->toBe('getResponse');
    });

    it('resets session mock correctly', function () use ($init, $errorCallBack): void {
        GlobalVars4Tests::reset();

        // Reset the session
        IoRunWebRunSpec::resetTestSession();

        $_server = [];
        IoRunWebRunSpec::run(
            GlobalReqParams::from($_server, ['error' => false], [], [], []),
            $init,
            $errorCallBack
        );

        $response = GlobalVars4Tests::get('IoRunWebTestResponse');
        expect($response->getBody()->__toString())->toBe('getResponse');
        expect($response->getHeader('header_value'))->toBe(['ok']);
    });
});
