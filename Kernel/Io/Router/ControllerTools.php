<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    use GuzzleHttp\Psr7\Response;
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use Psr\Http\Message\ResponseInterface;

    class ControllerTools {
        /**
         * @param string $text
         * @param ResponseInterface|null $response
         * @return ResponseInterface
         */
        public static function internalError(
            string $text = 'Internal server error',
            ResponseInterface $response = null
        ): ResponseInterface {
            $response ??= new Response();

            $response = $response->withStatus(500, 'Internal server error');
            $response->getBody()->write($text);

            return $response;
        }

        /**
         * @param string $text
         * @param ResponseInterface|null $response
         * @return ResponseInterface
         */
        public static function notFound(
            string $text = 'Not found',
            ResponseInterface $response = null
        ): ResponseInterface {
            $response ??= new Response();

            $response = $response->withStatus(404, 'Not found');
            $response->getBody()->write($text);

            return $response;
        }

        /**
         * @param string $text
         * @param ResponseInterface|null $response
         * @return ResponseInterface
         */
        public static function ok(string $text, ResponseInterface $response = null): ResponseInterface {
            $response ??= new Response();

            $response = $response
                ->withStatus(200, 'OK')
                ->withHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ;
            $response->getBody()->write($text);

            return $response;
        }

        /**
         * @param mixed $item
         * @param ResponseInterface|null $response
         * @param int $status
         * @return ResponseInterface
         */
        public static function JSON(mixed $item = null, ResponseInterface $response = null, int $status = 200): ResponseInterface {
            $response ??= new Response();
            $newResponse = $response
                ->withStatus($status, CodesTool::getReasonByCode($status))
                ->withAddedHeader('Content-type', 'application/json')
            ;

            $result = json_encode($item);

            if (!$result) {
                $result = '{}';
            }

            $newResponse->getBody()->write($result);

            return $newResponse;
        }

        /**
         * @param string $text
         * @param string $fileName
         * @param ResponseInterface|null $response
         * @return ResponseInterface
         */
        public static function okFile(string $text, string $fileName, ?ResponseInterface $response = null): ResponseInterface {
            $response ??= new Response();
            $mime = Mime::getFileMime($fileName);

            $response = $response->withStatus(200, 'OK');

            if ($mime) {
                $response = $response->withAddedHeader('Content-type', $mime);
            }

            $response->getBody()->write($text);

            return $response;
        }

        /**
         * @param string $url
         * @param int $status
         * @param ResponseInterface|null $response
         * @return ResponseInterface
         * @throws RouterException
         */
        public static function redirect(string $url, int $status = 302, ?ResponseInterface $response = null): ResponseInterface {
            $response ??= new Response();

            return $response
                ->withStatus($status)
                ->withHeader('Location', $url);
        }

        public static function okFilePath(string $filePath, string $fileName, ?ResponseInterface $response = null): ResponseInterface {
            $content = file_get_contents($filePath);

            if ($content === false) {
                $error = error_get_last();
                $message = empty($error['message']) ? 'no message' : $error['message'] ;

                throw new RouterException("Error on read file {$fileName}: " . $message);
            }

            return static::okFile($content, $fileName, $response);
        }
    }
}
