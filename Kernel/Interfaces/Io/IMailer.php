<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Io {
    interface IMailer {
        public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void;
    }
}
