<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Exceptions {
    use Throwable;

    class ValidationException extends CommonException {
        protected string $name;

        protected array $params;

        protected function __construct(string $message = '', int $code = 0, ?Throwable $previous = null) {
            parent::__construct($message, $code, $previous);
        }

        /**
         * @param string $name
         * @param array $params
         * @return void
         * @throws ValidationException
         */
        public static function fire(string $name, array $params): void {
            $result = new ValidationException($name);
            $result->name = $name;
            $result->params = $params;

            throw $result;
        }

        /**
         * @return string
         */
        public function getName(): string {
            return $this->name;
        }

        /**
         * @return array
         */
        public function getParams(): array {
            return $this->params;
        }
    }
}
