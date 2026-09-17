<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Accounts\Auth\AuthStrategy;

use PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Interfaces\Web\Router\IRouterUriParams;
use Psr\Http\Message\ResponseInterface;

interface AuthStrategyInterface {
    /**
     * Process authentication request
     * Returns null on success (allow access) or ResponseInterface for auth page/error
     */
    public static function authOnly(IGlobalReqParams $globals, IRouterUriParams $params): ?ResponseInterface;

    /**
     * Get current auth method name
     */
    public static function getMethodName(): string;
}
