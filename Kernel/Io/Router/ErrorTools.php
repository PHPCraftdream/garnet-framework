<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

    class ErrorTools {
        /**
         * @param bool $isLocal
         * @param string $error
         * @param int $code
         * @return array
         */
        public static function makeErrorJsonArr(bool $isLocal, string $error, int $code = 500): array {
            $result = ['code' => $code, 'message' => CodesTool::getReasonByCode($code)];

            if ($isLocal) {
                $errorLines = explode("\n", $error);
                $errorLines = array_map('trim', $errorLines);
                /* @phpstan-ignore-next-line */
                $errorLines = array_filter($errorLines, 'strlen');

                $result['details'] = $errorLines;
            }

            return $result;
        }

        /**
         * @param int $code
         * @return array
         */
        public static function makeErrorJson(int $code = 500): array {
            return ['code' => $code, 'message' => CodesTool::getReasonByCode($code)];
        }

        /**
         * Wrap an arbitrary string into a single `.line` div — used when we
         * need the error page styling but the input isn't a multi-line stack
         * trace (e.g. fallback "Internal server error").
         */
        public static function wrapAsLine(string $text): string {
            return "<div class='line'>{$text}</div>";
        }

        /**
         * Splits a stack-trace-style error string into `.line` blocks and
         * bolds the prefix before the first `:`. Returns pre-rendered HTML
         * because callers splice it into the ErrorPage Twig template via
         * `| raw`. This is a transformation helper, not page-layout markup —
         * the surrounding page is rendered by Twig.
         */
        public static function formatErrorStrToHtml(string $error): string {
            $error = str_replace('  ', '&nbsp;&nbsp;&nbsp;&nbsp;', $error);
            $errorLines = explode("\n", $error);
            $errorLines = array_map('trim', $errorLines);
            /* @phpstan-ignore-next-line */
            $errorLines = array_filter($errorLines, 'strlen');

            $errorLines = array_map(static function ($err) {
                $err = preg_replace('#(^([^:]+):)#si', '<b>$2</b>:', $err);

                return "<div class='line'>{$err}</div>";
            }, $errorLines);

            return join("\n", $errorLines);
        }

        /**
         * Renders the standalone error page through the ErrorPage.twig
         * template. Page chrome (DOCTYPE, head, body, css) lives in Twig;
         * this method just prepares the inner HTML fragment.
         */
        public static function makeErrorPageHtml(string $title, string $error, bool $isLocal): string {
            $errorHtml = $isLocal
                ? static::formatErrorStrToHtml($error)
                : static::wrapAsLine('Internal server error.');

            return Twig::get()->render('Layout/ErrorPage.twig', [
                'title' => $title,
                'description' => '',
                'error_html' => $errorHtml,
            ]);
        }
    }
}
