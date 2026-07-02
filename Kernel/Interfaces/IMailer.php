<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IMailer {
        public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void;
    }
}
