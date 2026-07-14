<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cookies {
    use DateTime;
    use DateTimeInterface;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie;

    class Cookie implements ICookie {
        use StringUtilTrait;

        protected bool $isChanged = true;

        protected ?string $name;

        protected ?string $value;

        protected string $sameSite = 'Strict';

        protected ?int $expires = 0;

        protected ?int $maxAge = 0;

        protected ?string $path = null;

        protected ?string $domain = null;

        protected ?bool $secure = false;

        protected ?bool $httpOnly = false;

        public function __construct(?string $name = null, ?string $value = null) {
            $this->name = $name;
            $this->value = $value;
        }

        protected bool $isNew = true;

        public function setOld(): ICookie {
            $this->isChanged = false;
            $this->isNew = false;

            return $this;
        }

        public function setItNew(): ICookie {
            $this->isChanged = true;
            $this->isNew = true;

            return $this;
        }

        public function isNew(): bool {
            return $this->isNew;
        }

        protected bool $observeStarted = false;

        public function startObserveChanges(): ICookie {
            $this->isChanged = false;
            $this->observeStarted = true;

            return $this;
        }

        public function isChanged(): bool {
            return ($this->observeStarted && $this->isChanged);
        }

        public function resetChanged(): bool {
            return $this->isChanged = false;
        }

        public function getName(): ?string {
            return $this->name;
        }

        public function getValue(): ?string {
            return $this->value;
        }

        public function getExpires(): ?int {
            return $this->expires;
        }

        public function getMaxAge(): ?int {
            return $this->maxAge;
        }

        public function getPath(): ?string {
            return $this->path;
        }

        public function getDomain(): ?string {
            return $this->domain;
        }

        public function getSecure(): bool {
            return !!$this->secure;
        }

        public function getHttpOnly(): bool {
            return !!$this->httpOnly;
        }

        public function setName(?string $name = null): ICookie {
            $this->isChanged = true;
            $this->name = $name;

            return $this;
        }

        public function setValue(?string $value = null): ICookie {
            $this->isChanged = true;
            $this->value = $value;

            return $this;
        }

        /**
         * @param DateTimeInterface|int|string|null $expires
         * @return null|int|false
         */
        protected function resolveExpires(null|DateTimeInterface|int|string $expires = null): null|int|false {
            if (null === $expires) {
                return null;
            }

            if ($expires instanceof DateTimeInterface) {
                return $expires->getTimestamp();
            }

            if (is_numeric($expires)) {
                return intval($expires);
            }

            return strtotime($expires);
        }

        public function setExpires(null|DateTimeInterface|int|string $expires = null): ICookie {
            $this->isChanged = true;
            $expires = $this->resolveExpires($expires);

            $this->expires = is_int($expires) ? $expires : 0;

            return $this;
        }

        public function rememberForever(): ICookie {
            return $this->setExpires(new DateTime('+5 years'));
        }

        public function expire(): ICookie {
            return $this->setExpires(new DateTime('-5 years'));
        }

        public function setMaxAge(?int $maxAge = null): ICookie {
            $this->isChanged = true;
            $this->maxAge = $maxAge;

            return $this;
        }

        public function setPath(?string $path = null): ICookie {
            $this->isChanged = true;
            $this->path = $path;

            return $this;
        }

        public function setDomain(?string $domain = null): ICookie {
            $this->isChanged = true;
            $this->domain = $domain;

            return $this;
        }

        public function setSecure(?bool $secure = null): ICookie {
            $this->isChanged = true;
            $this->secure = !!$secure;

            return $this;
        }

        public function setHttpOnly(?bool $httpOnly = null): ICookie {
            $this->isChanged = true;
            $this->httpOnly = !!$httpOnly;

            return $this;
        }

        public function __toString(): string {
            if (empty($this->name) || empty($this->value)) {
                return '';
            }

            if ($this->isNew() || $this->isChanged()) {
                $cookieStringParts = [
                    urlencode($this->name) . '=' . urlencode($this->value),
                ];

                $cookieStringParts = $this->appendFormattedDomainPartIfSet($cookieStringParts);
                $cookieStringParts = $this->appendFormattedPathPartIfSet($cookieStringParts);
                $cookieStringParts = $this->appendFormattedExpiresPartIfSet($cookieStringParts);
                $cookieStringParts = $this->appendFormattedMaxAgePartIfSet($cookieStringParts);
                $cookieStringParts = $this->appendFormattedSecurePartIfSet($cookieStringParts);
                $cookieStringParts = $this->appendFormattedHttpOnlyPartIfSet($cookieStringParts);
                $cookieStringParts = $this->appendFormattedSameSite($cookieStringParts);

                return implode('; ', $cookieStringParts);
            }

            return '';
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedDomainPartIfSet(array $cookieStringParts): array {
            if ($this->domain) {
                $cookieStringParts[] = sprintf('Domain=%s', $this->domain);
            }

            return $cookieStringParts;
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedPathPartIfSet(array $cookieStringParts): array {
            if ($this->path) {
                $cookieStringParts[] = sprintf('Path=%s', $this->path);
            }

            return $cookieStringParts;
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedExpiresPartIfSet(array $cookieStringParts): array {
            if ($this->expires) {
                $cookieStringParts[] = sprintf('Expires=%s', gmdate('D, d M Y H:i:s T', $this->expires));
            }

            return $cookieStringParts;
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedMaxAgePartIfSet(array $cookieStringParts): array {
            if ($this->maxAge) {
                $cookieStringParts[] = sprintf('Max-Age=%s', $this->maxAge);
            }

            return $cookieStringParts;
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedSecurePartIfSet(array $cookieStringParts): array {
            if ($this->secure) {
                $cookieStringParts[] = 'Secure';
            }

            return $cookieStringParts;
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedHttpOnlyPartIfSet(array $cookieStringParts): array {
            if ($this->httpOnly) {
                $cookieStringParts[] = 'HttpOnly';
            }

            return $cookieStringParts;
        }

        /**
         * @param array<array-key, string> $cookieStringParts
         * @return array<array-key, string>
         */
        protected function appendFormattedSameSite(array $cookieStringParts): array {
            if (!empty($this->sameSite)) {
                $cookieStringParts[] = sprintf('SameSite=%s', $this->sameSite);
            }

            return $cookieStringParts;
        }

        public function parse(string $string): ICookie {
            $rawAttributes = $this->splitOnAttributeDelimiter($string);
            [$cookieName, $cookieValue] = $this->splitCookiePair(array_shift($rawAttributes) ?? '');

            $this->setName($cookieName);
            $this->setValue($cookieValue);

            while ($rawAttribute = array_shift($rawAttributes)) {
                $rawAttributePair = explode('=', $rawAttribute, 2);

                $attributeKey = mb_strtolower($rawAttributePair[0]);
                $attributeValue = count($rawAttributePair) > 1 ? $rawAttributePair[1] : null;

                $attributeKey = strtolower($attributeKey);

                switch ($attributeKey) {
                    case 'expires':
                        $this->setExpires($attributeValue);

                        break;

                    case 'max-age':
                        $this->setMaxAge(intval($attributeValue));

                        break;

                    case 'domain':
                        $this->setDomain($attributeValue);

                        break;

                    case 'path':
                        $this->setPath($attributeValue);

                        break;

                    case 'secure':
                        $this->setSecure(true);

                        break;

                    case 'httponly':
                        $this->setHttpOnly(true);

                        break;

                    case 'samesite':
                        if (!empty($attributeValue)) {
                            $this->sameSite = $attributeValue;
                        }

                        break;
                }
            }

            return $this;
        }

        public function getSameSite(): string {
            return $this->sameSite;
        }

        public function setSameSiteStrict(): ICookie {
            $this->sameSite = 'Strict';

            return $this;
        }

        public function setSameSiteLax(): ICookie {
            $this->sameSite = 'Lax';

            return $this;
        }

        public function setSameSiteNone(): ICookie {
            $this->sameSite = 'None';

            return $this;
        }
    }
}
