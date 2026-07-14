<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Cookies {
    use DateTimeInterface;

    interface ICookie {
        public function setOld(): ICookie;

        public function setItNew(): ICookie;

        public function isNew(): bool;

        public function startObserveChanges(): ICookie;

        public function isChanged(): bool;

        public function resetChanged(): bool;

        public function getName(): ?string;

        public function getValue(): ?string;

        public function getExpires(): ?int;

        public function getMaxAge(): ?int;

        public function getPath(): ?string;

        public function getDomain(): ?string;

        public function getSecure(): bool;

        public function getHttpOnly(): bool;

        public function getSameSite(): string;

        public function setName(?string $name = null): ICookie;

        public function setValue(?string $value = null): ICookie;

        public function setSameSiteStrict(): ICookie;

        public function setSameSiteLax(): ICookie;

        public function setSameSiteNone(): ICookie;

        public function setExpires(null|DateTimeInterface|int|string $expires = null): ICookie;

        public function rememberForever(): ICookie;

        public function expire(): ICookie;

        public function setMaxAge(?int $maxAge = null): ICookie;

        public function setPath(?string $path = null): ICookie;

        public function setDomain(?string $domain = null): ICookie;

        public function setSecure(?bool $secure = null): ICookie;

        public function setHttpOnly(?bool $httpOnly = null): ICookie;

        public function __toString(): string;

        public function parse(string $string): ICookie;
    }
}
