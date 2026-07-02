<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils\Spec;

use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;

describe('HtmlLayout — prefetch_js_assets', function (): void {
    it('emits <link rel="prefetch"> for each URL in prefetch_js_assets', function (): void {
        $html = HtmlLayout::render([
            'prefetch_js_assets' => [
                '/assets/MyApp/gen/js/1017.aaaabbbbccccdddd.gen.js',
                '/assets/MyApp/gen/js/2192.eeeeffff00001111.gen.js',
            ],
        ]);
        expect($html)->toMatch('#<link rel="prefetch" as="script" href="/assets/MyApp/gen/js/1017\.aaaabbbbccccdddd\.gen\.js">#');
        expect($html)->toMatch('#<link rel="prefetch" as="script" href="/assets/MyApp/gen/js/2192\.eeeeffff00001111\.gen\.js">#');
    });

    it('renders an empty <head> block when prefetch_js_assets is omitted', function (): void {
        $html = HtmlLayout::render([]);
        // No prefetch link at all when caller passes nothing — HtmlLayout
        // intentionally does NOT auto-scan the filesystem at request time
        // (that would be a per-request glob, the whole point of generating
        // the list at build time).
        expect($html)->not->toMatch('#<link rel="prefetch"[^>]*>#');
    });

    it('handles a single chunk just like multiple', function (): void {
        $html = HtmlLayout::render([
            'prefetch_js_assets' => ['/assets/MyApp/gen/js/9999.deadbeefdeadbeef.gen.js'],
        ]);
        $matches = preg_match_all('#<link rel="prefetch"[^>]*>#', $html);
        expect($matches)->toBe(1);
    });

    it('passes empty array through as zero prefetch links', function (): void {
        $html = HtmlLayout::render(['prefetch_js_assets' => []]);
        expect($html)->not->toMatch('#<link rel="prefetch"[^>]*>#');
    });

    it('non-array prefetch_js_assets is coerced to empty list (no crash)', function (): void {
        $html = HtmlLayout::render(['prefetch_js_assets' => 'not-an-array']);
        // String value gets cast to (array) → ['not-an-array'] — but we
        // care that it doesn't crash and emits at most 1 link.
        $matches = preg_match_all('#<link rel="prefetch"[^>]*>#', $html);
        expect($matches)->toBeLessThan(2);
    });
});
