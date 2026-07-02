<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router\Spec\Tools;

use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars4Tests;
use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
use PHPCraftdream\Garnet\Kernel\Io\Router\BaseController;
use Psr\Http\Message\ResponseInterface;

class MicroController extends BaseController {
    public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
        GlobalVars4Tests::set('get__main', true);
        GlobalVars4Tests::setNotNull('lang_get__main', $params->getUriParam('lang'));

        return 'Hello Main World!';
    }

    public static function get__hello(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
        GlobalVars4Tests::set('get__hello', true);
        GlobalVars4Tests::setNotNull('lang_get__hello', $params->getUriParam('lang'));

        return 'Hello Hello World!';
    }

    public static function post__ok(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
        GlobalVars4Tests::set('post__ok', true);
        GlobalVars4Tests::setNotNull('lang_post__ok', $params->getUriParam('lang'));

        return 'Hello World Post!';
    }

    // ------------------------------------------------------------------------------------------------------------------

    public static function touchBeforeClass(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
        GlobalVars4Tests::set('touchBeforeClass', true);
        GlobalVars4Tests::setNotNull('lang_touchBeforeClass', $params->getUriParam('lang'));

        if ($params->getMethodParam(0) === 'print_before_class') {
            return 'print_before_class';
        }

        return null;
    }

    // ------------------------------------------------------------------------------------------------------------------

    public static function touchAfterClass(IGlobalReqParams $globals, IRouterUriParams $params, ResponseInterface|string|null $apiResult): mixed {
        GlobalVars4Tests::set('touchAfterClass', true);
        GlobalVars4Tests::setNotNull('lang_touchAfterClass', $params->getUriParam('lang'));

        if ($apiResult === null) {
            $apiResult = 'null';
        }

        if ($apiResult instanceof ResponseInterface) {
            $apiResult = $apiResult->getBody()->getContents();
        }

        return "{{$apiResult}}";
    }

    // ------------------------------------------------------------------------------------------------------------------

    /**
     * @param IGlobalReqParams $globals
     * @param IRouterUriParams $uriParams
     * @return ResponseInterface|string
     */
    public static function handlerNotFound(
        IGlobalReqParams $globals,
        IRouterUriParams $uriParams
    ): ResponseInterface|string {
        GlobalVars4Tests::set('handlerNotFound', true);

        return 'handlerNotFoundRes';
    }

    // ------------------------------------------------------------------------------------------------------------------
}
