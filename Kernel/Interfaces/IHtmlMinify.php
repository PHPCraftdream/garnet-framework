<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IHtmlMinify {
        /**
         * @param string $html
         * @return string
         */
        public function minify(string $html): string;
    }
}
