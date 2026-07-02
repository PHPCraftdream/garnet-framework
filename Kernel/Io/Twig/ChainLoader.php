<?php declare(strict_types=1);
/** @noinspection DuplicatedCode */

namespace PHPCraftdream\Garnet\Kernel\Io\Twig {
    use Twig\Error\LoaderError;
    use Twig\Loader\LoaderInterface;
    use Twig\Source;

    /**
     * @override
     */
    class ChainLoader implements LoaderInterface {
        private array $hasSourceCache = [];

        /**
         * @var LoaderInterface
         */
        private array $loaders = [];

        /**
         * @param LoaderInterface[] $loaders
         */
        public function __construct(array $loaders = []) {
            foreach ($loaders as $loader) {
                $this->addLoader($loader);
            }
        }

        /**
         * @param LoaderInterface $loader
         * @return void
         */
        public function addLoader(LoaderInterface $loader): void {
            $this->loaders[] = $loader;
            $this->hasSourceCache = [];
        }

        /**
         * @param LoaderInterface $loader
         * @return void
         */
        public function prependLoader(LoaderInterface $loader): void {
            array_unshift($this->loaders, $loader);
            $this->hasSourceCache = [];
        }

        /**
         * @return LoaderInterface[]
         */
        public function getLoaders(): array {
            return $this->loaders;
        }

        /**
         * @param string $name
         * @return Source
         * @throws LoaderError
         */
        public function getSourceContext(string $name): Source {
            $ex = [];

            foreach ($this->loaders as $loader) {
                if (!$loader->exists($name)) {
                    continue;
                }

                try {
                    return $loader->getSourceContext($name);
                } catch (LoaderError $e) {
                    $ex[] = $e->getMessage();
                }
            }

            $exStr = $ex ? ' (' . implode(', ', $ex) . ')' : '';

            throw new LoaderError(sprintf('Template "%s" is not defined%s.', $name, $exStr));
        }

        /**
         * @param string $name
         * @return bool
         */
        public function exists(string $name): bool {
            if (isset($this->hasSourceCache[$name])) {
                return $this->hasSourceCache[$name];
            }

            foreach ($this->loaders as $loader) {
                if ($loader->exists($name)) {
                    return $this->hasSourceCache[$name] = true;
                }
            }

            return $this->hasSourceCache[$name] = false;
        }

        /**
         * @param string $name
         * @return string
         * @throws LoaderError
         */
        public function getCacheKey(string $name): string {
            $ex = [];

            foreach ($this->loaders as $loader) {
                if (!$loader->exists($name)) {
                    continue;
                }

                try {
                    return $loader->getCacheKey($name);
                } catch (LoaderError $e) {
                    $ex[] = get_class($loader) . ': ' . $e->getMessage();
                }
            }

            $exStr = $ex ? ' (' . implode(', ', $ex) . ')' : '';

            throw new LoaderError(sprintf('Template "%s" is not defined%s.', $name, $exStr));
        }

        /**
         * @param string $name
         * @param int $time
         * @return bool
         * @throws LoaderError
         */
        public function isFresh(string $name, int $time): bool {
            $ex = [];

            foreach ($this->loaders as $loader) {
                if (!$loader->exists($name)) {
                    continue;
                }

                try {
                    return $loader->isFresh($name, $time);
                } catch (LoaderError $e) {
                    $ex[] = get_class($loader) . ': ' . $e->getMessage();
                }
            }

            $exStr = $ex ? ' (' . implode(', ', $ex) . ')' : '';

            throw new LoaderError(sprintf('Template "%s" is not defined%s.', $name, $exStr));
        }
    }
}
