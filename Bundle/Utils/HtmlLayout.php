<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils;

use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

/**
 * Thin façade that prepares parameters for the base page Twig layout.
 *
 * All HTML markup lives in `Layout/HtmlLayout.twig`. This class only
 * normalises inputs, JSON-encodes island props (so the template can splice
 * them straight into `data-props='…'` attributes), and hands the dict to
 * Twig::render(). See AGENTS.md §12 "HTML markup — Twig only".
 */
class HtmlLayout {
    /** Markers that mean the page body already carries the site shell. */
    private const SHELL_MARKERS = ['class="sp-nav', 'class="sp-footer'];

    public static function render(array $params): string {
        $accountId = (int)($params['account_id'] ?? 0);
        $topItems = (array)($params['top_menu_items'] ?? []);
        $sideItems = (array)($params['side_menu_items'] ?? []);
        $utility = $params['utility'] ?? null;

        // Centralised bare-main detect: if the body already brings the public
        // site chrome (sp-nav / sp-footer), the host `<main>` must drop its
        // p-4 lg:p-6 inset or the chrome ends up boxed inside the page.
        $contentRaw = (string)($params['content'] ?? '');
        $bareMain = (bool)($params['bare_main'] ?? false);

        if (!$bareMain) {
            foreach (self::SHELL_MARKERS as $marker) {
                if (str_contains($contentRaw, $marker)) {
                    $bareMain = true;

                    break;
                }
            }
        }

        $topMenuProps = ['menuItems' => $topItems];

        if (is_array($utility) && !empty($utility)) {
            $topMenuProps['utility'] = $utility;
        }

        $sidebarMenuProps = ['menuItems' => $sideItems, 'hasTopMenu' => !empty($topItems)];

        $mobileMenuProps = ['topItems' => $topItems, 'sideItems' => $sideItems];

        if (is_array($utility) && !empty($utility)) {
            $mobileMenuProps['utility'] = $utility;
        }

        $userPayloadJson = '{}';

        if ($accountId > 0) {
            $userPayload = [
                'accountId' => $accountId,
                'name' => (string)($params['user_name'] ?? ''),
                'timezone' => (string)($params['user_timezone'] ?? ''),
            ];
            $encoded = json_encode($userPayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

            if ($encoded !== false) {
                $userPayloadJson = $encoded;
            }
        }

        $noPrefixPaths = RouterUriParams::getNoPrefixPaths();
        $noPrefixPathsJson = '';

        if (!empty($noPrefixPaths)) {
            $encoded = json_encode($noPrefixPaths, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            if ($encoded !== false) {
                $noPrefixPathsJson = $encoded;
            }
        }

        $jsonAttrFlags = JSON_HEX_APOS | JSON_HEX_QUOT;

        // --- SEO / social-sharing meta (cascade: explicit → title/description). ---
        $title = (string)($params['title'] ?? '');
        $description = (string)($params['description'] ?? '');
        $canonical = (string)($params['canonical'] ?? '');
        $ogUrl = (string)($params['og_url'] ?? $canonical);

        // Resolve og:image — absolutise a relative path; when none is set fall
        // back to the site favicon so links always unfurl with an image.
        $baseUrl = rtrim((string)($params['base_url'] ?? ''), '/');
        $ogImage = trim((string)($params['og_image'] ?? ''));
        $hasRealOg = $ogImage !== '';

        if ($hasRealOg && !preg_match('#^https?://#i', $ogImage)) {
            $ogImage = $baseUrl . '/' . ltrim($ogImage, '/');
        }

        if (!$hasRealOg && $baseUrl !== '') {
            $ogImage = $baseUrl . '/favicon.ico';
        }

        $seo = [
            'canonical' => $canonical,
            'robots' => (string)($params['robots'] ?? ''),
            'theme_color' => (string)($params['theme_color'] ?? ''),
            'og_title' => (string)($params['og_title'] ?? $title),
            'og_description' => (string)($params['og_description'] ?? $description),
            'og_image' => $ogImage,
            // Only a configured image is a real 1200×630 card; the favicon
            // fallback is a small square, so it uses the `summary` twitter card.
            'og_image_is_real' => $hasRealOg,
            'og_url' => $ogUrl,
            'og_type' => (string)($params['og_type'] ?? 'website'),
            'og_site_name' => (string)($params['og_site_name'] ?? ''),
            'og_locale' => (string)($params['og_locale'] ?? ''),
            'twitter_card' => (string)($params['twitter_card'] ?? ($hasRealOg ? 'summary_large_image' : 'summary')),
            'twitter_site' => (string)($params['twitter_site'] ?? ''),
        ];

        $vars = [
            'title' => $title,
            'description' => $description,
            'keywords' => (string)($params['keywords'] ?? ''),
            'viewport' => (string)($params['viewport'] ?? 'width=device-width, initial-scale=1, shrink-to-fit=no'),
            'meta_referrer' => (string)($params['meta_referrer'] ?? ''),
            'content_type' => (string)($params['content_type'] ?? ''),
            'lang' => (string)($params['lang'] ?? ''),
            'csrf' => (string)($params['csrf'] ?? ''),
            'base_url' => (string)($params['base_url'] ?? ''),
            // Surfaces the runtime env flag to the layout so frontend
            // bundles can gate dev-only code (e.g. the quick-login panel
            // in Framework.ts → AuthDev). Read straight from app.ini's
            // `env` — production app.ini has `env = "prod"`, so the
            // twig block never fires and the dev panel never ships.
            'is_dev' => IniConfig::app()->paramString('env', 'prod') === 'dev',
            'upload_dir' => (string)($params['upload_dir'] ?? ''),
            'route_prefix' => (string)($params['route_prefix'] ?? ''),
            // Endpoint the client polls (~20s) for live nav-badge / widget
            // counters. Optional — apps that don't provide it just skip polling.
            'counts_url' => (string)($params['counts_url'] ?? ''),
            // Support/contact email shown in the page footer when configured.
            'support_email' => (string)($params['support_email'] ?? ''),
            'support_contact_label' => (string)($params['support_contact_label'] ?? ''),
            'account_id' => $accountId,
            // Frontend build id — emitted as a meta tag + window global so the
            // SPA navigator can detect a stale bundle and hard-reload.
            'build_id' => (string)($params['build_id'] ?? ''),
            'user_payload_json' => $userPayloadJson,
            'no_prefix_paths_json' => $noPrefixPathsJson,
            'styles_assets' => (array)($params['styles_assets'] ?? []),
            'vendor_js_assets' => (array)($params['vendor_js_assets'] ?? []),
            'js_assets' => (array)($params['js_assets'] ?? []),
            // Common async chunks emitted by rspack splitChunks. The
            // template emits `<link rel="prefetch">` for each so the browser
            // warms its cache during idle time after the main page paints.
            // Caller passes the array (typically `<App>JsGen::commonChunks()`,
            // generated at build time by PhpClassGeneratorPlugin); pass an
            // empty array to opt out for landing pages where prefetch noise
            // is not worth it.
            'prefetch_js_assets' => array_values((array)($params['prefetch_js_assets'] ?? [])),
            'top_items' => $topItems,
            'side_items' => $sideItems,
            'has_menus' => !empty($topItems) || !empty($sideItems),
            'top_menu_props_json' => json_encode($topMenuProps, $jsonAttrFlags),
            'sidebar_menu_props_json' => json_encode($sidebarMenuProps, $jsonAttrFlags),
            'mobile_menu_props_json' => json_encode($mobileMenuProps, $jsonAttrFlags),
            'content' => $contentRaw,
            // When the page body brings its OWN chrome (static pages render a
            // full-bleed header/footer inside `content`), drop the main wrapper
            // padding so that chrome reaches the viewport edges like the admin
            // top bar does — otherwise the sticky bar is inset by p-4/lg:p-6.
            //
            // Belt-and-suspenders: if the caller forgot to pass `bare_main`
            // but the body already carries the site shell (any `sp-nav` /
            // `sp-footer` marker), force it. Centralising the detect here
            // means new callers can't silently re-introduce the inset bug.
            'bare_main' => $bareMain,
            'support_widget' => (string)($params['support_widget'] ?? ''),
            'im_widget' => (string)($params['im_widget'] ?? ''),
            // Whether to mount the timezone-mismatch warn banner under the
            // top header. Defaults to true; callers (e.g. static page
            // controllers) can opt out by passing `tz_banner => false`.
            'tz_banner' => (bool)($params['tz_banner'] ?? true),
        ];

        $vars += $seo;

        return Twig::get()->render('Layout/HtmlLayout.twig', $vars);
    }
}
