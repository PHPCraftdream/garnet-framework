<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\BaseTest {
    use Mockista\Registry;
    use ReflectionClass;
    use ReflectionException;
    use ReflectionMethod;

    class BaseTest {
        protected static ?Registry $mockista = null;

        /**
         * @param class-string $className
         * @param array<string, callable> $methods
         * @return object
         */
        public static function createMock(string $className, array $methods = []): object {
            if (empty(static::$mockista)) {
                static::$mockista = new Registry();
            }

            return static::$mockista->create($className, $methods);
        }

        /**
         * @param object $object
         * @param string $propertyName
         * @return mixed
         * @throws ReflectionException
         */
        public static function getPropertyValue(object $object, string $propertyName): mixed {
            $reflectionClass = new ReflectionClass($object);
            $property = $reflectionClass->getProperty($propertyName);

            return $property->getValue($object);
        }

        /**
         * @param class-string $className
         * @param string $methodName
         * @return ReflectionMethod
         * @throws ReflectionException
         */
        public static function getMethod(string $className, string $methodName): ReflectionMethod {
            $reflectionClass = new ReflectionClass($className);

            return $reflectionClass->getMethod($methodName);
        }

        /**
         * @param class-string|object $target
         * @param string $methodName
         * @param array<int, mixed> $args
         * @return mixed
         * @throws ReflectionException
         */
        public static function invoke(string|object $target, string $methodName, array $args = []): mixed {
            $class = new ReflectionClass($target);
            $method = $class->getMethod($methodName);

            return $method->invokeArgs(is_object($target) ? $target : null, $args);
        }
    }
}
