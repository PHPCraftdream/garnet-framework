<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Twig\Spec {
    use PHPCraftdream\Garnet\Kernel\Io\Twig\LocaleResolvingLoader;
    use stdClass;

    use function time;

    use Twig\Loader\ArrayLoader;

    describe('LocaleResolvingLoader', function (): void {
        beforeEach(function (): void {
            // Locale lives on a shared container so the closure passed to
            // LocaleResolvingLoader observes mutations made later in the
            // spec body. Storing it directly on `$this->locale` doesn't
            // work — kahlan's spec-scope object isn't always the same one
            // the arrow closure captured at construction.
            $this->localeRef = new stdClass();
            $this->localeRef->value = 'en';
            $localeRef = $this->localeRef;

            $this->inner = new ArrayLoader([
                'Foo/Bar.en.twig' => 'EN content for {{ x }}',
                'Foo/Bar.ru.twig' => 'RU контент для {{ x }}',
                'Foo/Bar.de.twig' => 'DE Inhalt für {{ x }}',

                'NoLocale/Plain.en.twig' => 'plain EN body',

                // Explicit-locale callers stay byte-identical:
                'Explicit/X.ru.twig' => 'explicit RU',

                // Non-twig assets pass through untouched.
                'README.md' => '# inert',
            ]);

            $this->loader = new LocaleResolvingLoader(
                $this->inner,
                static fn (): string => $localeRef->value,
            );
        });

        describe('rewrite() — implicit suffix', function (): void {
            it('appends `.{locale}.twig` when callers ask for the canonical bare name', function (): void {
                expect($this->loader->exists('Foo/Bar.twig'))->toBe(true);
                expect($this->loader->getSourceContext('Foo/Bar.twig')->getCode())
                    ->toBe('EN content for {{ x }}');
            });

            it('follows the live locale on every call (lazy)', function (): void {
                $this->localeRef->value = 'ru';
                expect($this->loader->getSourceContext('Foo/Bar.twig')->getCode())
                    ->toBe('RU контент для {{ x }}');

                $this->localeRef->value = 'de';
                expect($this->loader->getSourceContext('Foo/Bar.twig')->getCode())
                    ->toBe('DE Inhalt für {{ x }}');
            });

            it('normalises locale (case + surrounding whitespace)', function (): void {
                $this->localeRef->value = '  RU  ';
                expect($this->loader->getSourceContext('Foo/Bar.twig')->getCode())
                    ->toBe('RU контент для {{ x }}');
            });

            it("falls back to 'en' when the provider returns an empty string", function (): void {
                $this->localeRef->value = '';
                expect($this->loader->getSourceContext('Foo/Bar.twig')->getCode())
                    ->toBe('EN content for {{ x }}');
            });

            it('returns the suffixed name as the Source name (keeps reentry-safe)', function (): void {
                $src = $this->loader->getSourceContext('Foo/Bar.twig');
                // The decorator restamps the Source name to the un-suffixed
                // form so `{% include 'Foo/Bar.twig' %}` inside this
                // template still routes back through the locale loader.
                expect($src->getName())->toBe('Foo/Bar.twig');
            });
        });

        describe('rewrite() — explicit suffix', function (): void {
            it('treats names already ending in `.{locale}.twig` as canonical', function (): void {
                // Callers may name a locale explicitly; the decorator
                // must not double-stamp it.
                expect($this->loader->getSourceContext('Explicit/X.ru.twig')->getCode())
                    ->toBe('explicit RU');
            });

            it('treats *any* trailing two-letter language stamp as canonical (no double-stamp)', function (): void {
                // Even when current locale='en' and the name carries '.de',
                // we must NOT rewrite to '.de.en.twig'.
                expect($this->loader->getSourceContext('Foo/Bar.de.twig')->getCode())
                    ->toBe('DE Inhalt für {{ x }}');
            });
        });

        describe('rewrite() — non-twig pass-through', function (): void {
            it('returns non-twig names verbatim', function (): void {
                expect($this->loader->exists('README.md'))->toBe(true);
                expect($this->loader->getSourceContext('README.md')->getCode())->toBe('# inert');
            });
        });

        describe('cache keys', function (): void {
            it('encodes the resolved locale so EN/RU outputs cache separately', function (): void {
                $this->localeRef->value = 'en';
                $keyEn = $this->loader->getCacheKey('Foo/Bar.twig');

                $this->localeRef->value = 'ru';
                $keyRu = $this->loader->getCacheKey('Foo/Bar.twig');

                expect($keyEn)->not->toBe($keyRu);
            });
        });

        describe('exists() / isFresh()', function (): void {
            it('routes exists() through the resolved name', function (): void {
                $this->localeRef->value = 'ru';
                expect($this->loader->exists('Foo/Bar.twig'))->toBe(true);

                $this->localeRef->value = 'xx';  // No Foo/Bar.xx.twig in fixture
                expect($this->loader->exists('Foo/Bar.twig'))->toBe(false);
            });

            it('routes isFresh() through the resolved name', function (): void {
                // ArrayLoader templates are always fresh — we just want the
                // call to not blow up after rewrite.
                expect($this->loader->isFresh('Foo/Bar.twig', time() - 10))->toBe(true);
            });
        });
    });
}
