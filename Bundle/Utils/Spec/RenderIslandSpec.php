<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils\Spec;

use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;

describe('RenderIsland', function (): void {
    describe('render()', function (): void {
        it('returns a div element', function (): void {
            $html = RenderIsland::render('my-widget', []);
            expect($html)->toMatch('/<div /');
            expect($html)->toContain('</div>');
        });

        it('class is formed as {className}-init', function (): void {
            expect(RenderIsland::render('my-widget', []))->toContain('my-widget-init');
            expect(RenderIsland::render('registration-form', []))->toContain('registration-form-init');
            expect(RenderIsland::render('user-profile', []))->toContain('user-profile-init');
        });

        it('props array is embedded into the data-props attribute as JSON', function (): void {
            $props = ['key' => 'value', 'count' => 42];
            $html = RenderIsland::render('component', $props);
            expect($html)->toContain('data-props=');
            expect($html)->toContain('"key"');
            expect($html)->toContain('"value"');
            expect($html)->toContain('42');
        });

        it('an empty array yields [] in data-props (json_encode([])=[])', function (): void {
            $html = RenderIsland::render('component', []);
            expect($html)->toContain("data-props='[]'");
        });

        it('an array with elements is correctly encoded as JSON', function (): void {
            $props = ['name' => 'test', 'items' => [1, 2, 3]];
            $html = RenderIsland::render('widget', $props);
            expect($html)->toContain('"name"');
            expect($html)->toContain('"test"');
            expect($html)->toContain('[1,2,3]');
        });

        it('different className values yield different class attributes', function (): void {
            $html1 = RenderIsland::render('widget-a', []);
            $html2 = RenderIsland::render('widget-b', []);
            expect($html1)->toContain('widget-a-init');
            expect($html2)->toContain('widget-b-init');
            expect($html1)->not->toContain('widget-b-init');
            expect($html2)->not->toContain('widget-a-init');
        });

        it('different props yield different output', function (): void {
            $html1 = RenderIsland::render('component', ['id' => 1]);
            $html2 = RenderIsland::render('component', ['id' => 2]);
            expect($html1)->not->toBe($html2);
        });
    });
});
