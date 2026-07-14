<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

describe('TwigParams', function (): void {
    beforeEach(function (): void {
        // Reset singleton instance before each test
        $reflection = new ReflectionClass(TwigParams::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setValue(null, null);
    });

    describe('integration with parent class', function (): void {
        it('works with callable parameters and returns empty array for non-existent keys', function (): void {
            $params = TwigParams::init();
            $callable = fn () => ['result' => 'lazy'];
            $params->set('lazy', $callable);
            expect($params->get('lazy'))->toBe(['result' => 'lazy']);

            expect($params->get('nonexistent'))->toBe([]);
        });
    });
});
