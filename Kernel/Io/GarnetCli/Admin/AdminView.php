<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Thin façade that hands the GarnetCli admin views to Twig.
 *
 * The admin app runs its own micro HTTP server (`php garnet admin`) and
 * doesn't share the main framework's Twig environment, so we spin up a
 * local FilesystemLoader against `./templates/`. Markup belongs to Twig
 * files — see AGENTS.md §12 "HTML markup — Twig only".
 */
class AdminView {
    public static function loginPage(): string {
        return self::twig()->render('login.twig', ['title' => 'Garnet Admin - Login']);
    }

    public static function deniedPage(): string {
        return self::twig()->render('denied.twig', ['title' => 'Garnet Admin - Denied']);
    }

    public static function dashboardPage(string $currentApp, array $apps): string {
        $propsJson = json_encode(['currentApp' => $currentApp, 'apps' => $apps], JSON_HEX_APOS | JSON_HEX_QUOT);

        return self::twig()->render('dashboard.twig', [
            'title' => 'Garnet Admin',
            'props_json' => $propsJson,
        ]);
    }

    private static function twig(): Environment {
        static $env = null;

        if ($env === null) {
            $env = new Environment(new FilesystemLoader(__DIR__ . '/templates'), ['cache' => false]);
        }

        return $env;
    }
}
