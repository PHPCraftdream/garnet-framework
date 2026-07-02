<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Twig {
    use Closure;

    use function preg_match;
    use function str_ends_with;
    use function strlen;
    use function strtolower;
    use function substr;
    use function trim;

    use Twig\Loader\LoaderInterface;
    use Twig\Source;

    /**
     * Decorates an underlying Twig loader so every `Foo/Bar.twig` lookup
     * resolves to a locale-specific file `Foo/Bar.{locale}.twig` first.
     *
     * The framework's bundled templates ship as paired files (e.g.
     * `Layout/Maintenance.en.twig` + `Layout/Maintenance.ru.twig`). Call
     * sites and inter-template `{% include %}` / `{% extends %}` /
     * `{% import %}` directives keep using the canonical bare name — the
     * loader rewrites them on the fly.
     *
     * Cache keys carry the suffix too, so EN-rendered and RU-rendered
     * outputs are cached independently in the same Environment.
     *
     * Locale source is supplied as a callable so it can be evaluated
     * lazily on every load (tests flip locale at runtime via
     * IniConfig::app()->setRuntimeOverride).
     */
    class LocaleResolvingLoader implements LoaderInterface {
        /**
         * @param LoaderInterface $inner
         * @param Closure        $localeProvider returns lowercase locale code, e.g. 'en' / 'ru'
         */
        public function __construct(
            private LoaderInterface $inner,
            private Closure $localeProvider,
        ) {
        }

        public function getSourceContext(string $name): Source {
            $resolved = $this->rewrite($name);
            $src = $this->inner->getSourceContext($resolved);

            // Re-stamp the Source with the original (un-suffixed) name so
            // `{% include 'Email/Row.twig' %}` inside this template still
            // routes through this loader on the next load.
            return new Source($src->getCode(), $name, $src->getPath());
        }

        public function getCacheKey(string $name): string {
            return $this->inner->getCacheKey($this->rewrite($name));
        }

        public function isFresh(string $name, int $time): bool {
            return $this->inner->isFresh($this->rewrite($name), $time);
        }

        public function exists(string $name): bool {
            return $this->inner->exists($this->rewrite($name));
        }

        /**
         * Rewrites `Foo/Bar.twig` → `Foo/Bar.{locale}.twig`. If `$name`
         * already carries a `.{locale}.twig` suffix it is returned
         * unchanged (covers explicit callers and prevents double-stamping
         * via re-entry).
         */
        private function rewrite(string $name): string {
            if (!str_ends_with($name, '.twig')) {
                return $name;
            }

            $locale = $this->locale();

            // Already locale-stamped: bail out so we don't produce
            // `Foo/Bar.en.en.twig`.
            $stem = substr($name, 0, -strlen('.twig'));

            if (str_ends_with($stem, '.' . $locale)) {
                return $name;
            }

            // Reject any other 2-letter trailing language stamp too — this
            // is how spec helpers like `Twig::get()->render('X.ru.twig')`
            // stay byte-identical to the inner loader's input.
            if (preg_match('/\.[a-z]{2}$/', $stem) === 1) {
                return $name;
            }

            return $stem . '.' . $locale . '.twig';
        }

        private function locale(): string {
            $raw = (string)($this->localeProvider)();
            $raw = strtolower(trim($raw));

            return $raw === '' ? 'en' : $raw;
        }
    }
}
