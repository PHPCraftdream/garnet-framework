<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterDevFile;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use Psr\Http\Message\ResponseInterface;

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    class RouterDevFile implements IRouterDevFile {
        protected array $filesDirs = [];

        /**
         * @param string $name
         * @param string $dir
         * @return void
         * @throws RouterException
         */
        public function addFilesDir(string $name, string $dir): void {
            $dir = str_replace(['\\', '/'], DS, $dir);
            $name = trim($name, '\\/');

            if (!preg_match('/^[a-zA-Z\d\-_\/]*$/', $name)) {
                throw new RouterException('Wrong directory name pattern');
            }

            if (!empty($this->filesDirs[$name])) {
                throw new RouterException('Files dir already exists');
            }

            if (!is_dir($dir)) {
                throw new RouterException('Wrong directory');
            }

            $this->filesDirs[$name] = $dir;
        }

        /**
         * @param IGlobalReqParams $globals
         * @return ResponseInterface|null
         * @throws RouterException
         */
        public function dispatch(IGlobalReqParams $globals): ResponseInterface|null {
            if (!RouterTools::checkUriPathFile($globals->getUri())) {
                return null;
            }

            $uriParams = RouterUriParams::fromGlobals($globals);
            $result = $this->tryFile($uriParams);

            if ($result === null) {
                return null;
            }

            [$filePath, $fileName] = $result;

            return ControllerTools::okFilePath($filePath, $fileName);
        }

        /**
         * @param string $dir
         * @param string $fileName
         * @return array{string, string}|null
         */
        protected function tryFileByDir(string $dir, string $fileName): array|null {
            if (empty($this->filesDirs[$dir])) {
                return null;
            }

            $fileDir = $this->filesDirs[$dir];
            $fileName = empty($fileName) ? 'index.html' : $fileName;

            if (strtolower(substr($fileName, -4)) === '.php') {
                return null;
            }

            $filePath = RouterTools::makeFilePath([$fileDir, $fileName]);

            if (!is_file($filePath)) {
                return null;
            }

            $fileName = substr($filePath, strrpos($filePath, DS) + 1);

            return [$filePath, $fileName];
        }

        /**
         * @param string $routeVal
         * @return array{string, string}
         */
        protected static function getRouteDirAndFile(string $routeVal): array {
            $routeVal = str_replace(['\\', '/'], DS, $routeVal);
            $routeVal = ltrim($routeVal, DS);

            $routeArr = explode(DS, $routeVal);
            $emptyArr1 = !array_key_exists(1, $routeArr);
            $routeDir = $emptyArr1 ? '' : $routeArr[0];

            $routeArrTail = $emptyArr1 ? $routeArr[0] : join(DS, array_slice($routeArr, 1));
            $file = empty($routeArrTail) ? 'index.html' : $routeArrTail;

            return [$routeDir, $file];
        }

        /**
         * @param IRouterUriParams $params
         * @return array{string, string}|null
         */
        protected function tryFile(IRouterUriParams $params): array|null {
            $routeVal = $params->getRouteVal();
            $routeVal = str_replace(['\\', '/'], DS, $routeVal);
            $routeVal = trim($routeVal, DS);

            $result = $this->tryFileByDir('', $routeVal);

            if ($result) {
                return $result;
            }

            [$routeDir, $file] = static::getRouteDirAndFile($routeVal);

            return $this->tryFileByDir($routeDir, $file);
        }
    }
}
