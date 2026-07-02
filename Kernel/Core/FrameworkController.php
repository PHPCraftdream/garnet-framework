<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core {
    use Exception;
    use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\AppConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
    use PHPCraftdream\Garnet\Kernel\Io\Router\BaseController;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ErrorTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use Throwable;

    abstract class FrameworkController extends BaseController {
        public const URL = '/';

        protected static string $appIniNamespace = IniConfig::ENV_APP;

        protected static function makeErrorPage(string $title, string $error, bool $isLocal): string {
            $title = $title . '. ';
            $description = $title . '. ';
            $result = 'Internal server error.';

            try {
                $result = $isLocal ? ErrorTools::formatErrorStrToHtml($error) : 'Internal server error.';
            } catch (Exception $e) {
            }

            try {
                $ini = AppConfig::get(static::$appIniNamespace);
                $title .= $ini->paramString('title', '');
                $description .= $ini->paramString('description', '');
            } catch (Throwable $e) {
                $exceptionStr = ErrorTools::formatErrorStrToHtml(ErrorCatcher::getExceptionStrResult($e));

                if ($isLocal) {
                    $result = $result . $exceptionStr;
                } else {
                    $result = ErrorTools::formatErrorStrToHtml('EnvIni load error') . $result;
                }

                try {
                    Logger::get(Logger::ERROR_LOGGER)->write('load_env_ini', $exceptionStr);
                } catch (Throwable $e) {
                }
            }

            return Twig::get()->render('Layout/ErrorPage.twig', [
                'title' => $title,
                'description' => $description,
                'error_html' => ErrorTools::wrapAsLine($result),
            ]);
        }
    }
}
