<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils;

use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

class RenderIsland {
    /**
     * Render an island container element.
     *
     * @param string $className  Island class name (without -init suffix)
     * @param array  $props      Props array (auto-encoded to JSON)
     */
    public static function render(string $className, array $props = []): string {
        $propsJson = json_encode($props, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return Twig::get()->render('Layout/Island.twig', [
            'class_name' => $className,
            'props_json' => $propsJson,
        ]);
    }
}
