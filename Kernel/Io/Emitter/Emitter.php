<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Emitter {
    use Psr\Http\Message\ResponseInterface;

    class Emitter {
        protected static function header(string $header, bool $replace = true, int $response_code = 0): void {
            header($header, $replace, $response_code);
        }

        public static function emit(ResponseInterface $response): bool {
            $statusLine = sprintf(
                'HTTP/%s %s %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
            );

            $body = $response->getBody();
            $size = $body->getSize();

            static::header($statusLine);

            foreach ($response->getHeaders() as $name => $values) {
                // First value replaces any prior header of the same name,
                // subsequent values are appended. Without `$replace=false`
                // on the inner iteration, every value of a repeated header
                // (most commonly Set-Cookie) would clobber the previous one
                // and only the LAST cookie would actually be sent.
                $first = true;

                foreach ($values as $value) {
                    static::header("{$name}: {$value}", $first);
                    $first = false;
                }
            }

            static::header('X-Powered-By: Application');
            static::header("Content-Length: {$size}");
            static::header('Content-Encoding: none');

            echo $body->__toString();

            return true;
        }
    }
}
