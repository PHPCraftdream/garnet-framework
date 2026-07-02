<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\Spec {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminView;

    use function strlen;

    describe('AdminView', function (): void {
        describe('::loginPage', function (): void {
            it('renders an HTML page with the login title and Twig output', function (): void {
                $html = AdminView::loginPage();
                expect($html)->toBeA('string');
                expect(strlen($html))->toBeGreaterThan(0);
                expect($html)->toContain('Garnet Admin - Login');
            });

            it('produces something resembling an HTML document', function (): void {
                $html = AdminView::loginPage();
                // login.twig extends layout.twig, so we should see html/body markup.
                expect($html)->toMatch('/<html/i');
                expect($html)->toMatch('/<\/html>/i');
            });
        });

        describe('::deniedPage', function (): void {
            it('renders an HTML page with the denied title', function (): void {
                $html = AdminView::deniedPage();
                expect($html)->toContain('Garnet Admin - Denied');
                expect($html)->toMatch('/<html/i');
            });
        });

        describe('::dashboardPage', function (): void {
            it('embeds the currentApp + apps list as JSON props', function (): void {
                $html = AdminView::dashboardPage('Blog', ['Blog', 'Shop', 'Docs']);

                expect($html)->toContain('Garnet Admin');
                // props_json is the JSON_HEX_QUOT-encoded payload — single quotes around
                // it stay as ascii, but double quotes inside become ".
                expect($html)->toContain('currentApp');
                expect($html)->toContain('Blog');
                expect($html)->toContain('Shop');
            });

            it('survives an empty apps list', function (): void {
                $html = AdminView::dashboardPage('', []);
                expect($html)->toContain('Garnet Admin');
                expect($html)->toMatch('/<html/i');
            });

            it('escapes quotes in the JSON props (no XSS via app name)', function (): void {
                $html = AdminView::dashboardPage('Mal"icious', ['x']);
                // JSON_HEX_QUOT replaces " with ", so the raw " never reaches
                // the HTML attribute value boundary.
                // The literal `Mal"icious` should NOT appear in the rendered page;
                // either the encoded form or the HTML-escaped form should appear.
                expect($html)->not->toContain('Mal"icious');
            });
        });

        describe('Twig environment caching', function (): void {
            it('returns identical output for repeated calls (cache stable)', function (): void {
                $a = AdminView::loginPage();
                $b = AdminView::loginPage();
                expect($a)->toBe($b);
            });
        });
    });
}
