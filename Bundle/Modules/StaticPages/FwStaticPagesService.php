<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\StaticPages {
    use Aura\SqlQuery\Common\SelectInterface;
    use League\CommonMark\Environment\Environment;
    use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
    use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
    use League\CommonMark\Extension\Table\TableExtension;
    use League\CommonMark\MarkdownConverter;
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Tables\FwStaticPageBlocks;
    use PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Tables\FwStaticPages;
    use PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Tables\FwStaticSnippets;
    use PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\FwAppSettings;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\AppConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

    abstract class FwStaticPagesService {
        abstract protected static function pagesTable(): FwStaticPages;

        abstract protected static function blocksTable(): FwStaticPageBlocks;

        abstract protected static function snippetsTable(): FwStaticSnippets;

        public static function getPageBySlug(string $slug): ?array {
            $page = static::pagesTable()->selectOneByField('slug', $slug);

            if (!$page) {
                return null;
            }
            $page['blocks'] = static::getBlocksForPage((int)$page['id'], false);

            return $page;
        }

        public static function getPublishedPageBySlug(string $slug): ?array {
            $page = static::pagesTable()->selectAll(function (SelectInterface $q) use ($slug): void {
                $q->where('slug = ?', [$slug]);
                $q->where('is_published = 1');
                $q->limit(1);
            });

            if (empty($page)) {
                return null;
            }
            $page = $page[0];
            $page['blocks'] = static::getBlocksForPage((int)$page['id'], true);

            return $page;
        }

        public static function getBlocksForPage(int $pageId, bool $visibleOnly = false): array {
            return static::blocksTable()->selectAll(function (SelectInterface $q) use ($pageId, $visibleOnly): void {
                $q->where('page_id = ?', [$pageId]);

                if ($visibleOnly) {
                    $q->where('is_hidden = 0');
                }
                $q->orderBy(['sort_order ASC', 'id ASC']);
            });
        }

        public static function listPages(): array {
            return static::pagesTable()->selectAll(function (SelectInterface $q): void {
                $q->orderBy(['sort_order ASC', 'id DESC']);
            });
        }

        public static function listPublishedPages(): array {
            return static::pagesTable()->selectAll(function (SelectInterface $q): void {
                $q->where('is_published = 1');
                $q->orderBy(['sort_order ASC', 'id ASC']);
            });
        }

        /**
         * List published pages filtered by visibility for the current user.
         * @param bool $isLoggedIn
         * @param bool $isModerator
         */
        public static function listPublishedPagesForUser(bool $isLoggedIn, bool $isModerator): array {
            $all = static::listPublishedPages();

            return array_values(array_filter($all, function (array $page) use ($isLoggedIn, $isModerator): bool {
                $vis = (string)($page['visibility'] ?? 'all');

                if ($vis === 'all') {
                    return true;
                }

                if ($vis === 'auth') {
                    return $isLoggedIn;
                }

                if ($vis === 'moderator') {
                    return $isModerator;
                }

                return true;
            }));
        }

        public static function createPage(string $slug, string $title, int $createdBy): array {
            $now = time();
            $data = [
                'slug' => $slug,
                'title' => $title,
                'is_published' => 0,
                'meta_description' => '',
                'sort_order' => 0,
                'updated_at' => $now,
                'updated_by' => $createdBy,
                'created_at' => $now,
            ];
            $id = static::pagesTable()->insert($data);
            $data['id'] = $id;

            return $data;
        }

        public static function updatePage(int $pageId, array $fields, int $updatedBy): void {
            $fields['updated_at'] = time();
            $fields['updated_by'] = $updatedBy;
            static::pagesTable()->updateById($fields, $pageId);
        }

        public static function deletePage(int $pageId): void {
            // Delete all blocks first
            $blocks = static::getBlocksForPage($pageId);

            foreach ($blocks as $block) {
                static::blocksTable()->deleteByField('id', (int)$block['id']);
            }
            static::pagesTable()->deleteByField('id', $pageId);
        }

        public static function addBlock(int $pageId, string $blockType, string $content, int $sortOrder): array {
            // Shift existing blocks at or after this position
            $existing = static::getBlocksForPage($pageId);

            foreach ($existing as $block) {
                if ((int)$block['sort_order'] >= $sortOrder) {
                    static::blocksTable()->updateById(
                        ['sort_order' => (int)$block['sort_order'] + 1],
                        (int)$block['id']
                    );
                }
            }

            $data = [
                'page_id' => $pageId,
                'block_type' => $blockType,
                'content' => $content,
                'sort_order' => $sortOrder,
                'is_hidden' => 0,
                'created_at' => time(),
            ];
            $id = static::blocksTable()->insert($data);
            $data['id'] = $id;

            // Touch page updated_at
            static::pagesTable()->updateById(['updated_at' => time()], $pageId);

            return $data;
        }

        public static function updateBlock(int $blockId, array $fields): void {
            static::blocksTable()->updateById($fields, $blockId);
        }

        public static function deleteBlock(int $blockId): void {
            $block = static::blocksTable()->selectOneByField('id', $blockId);
            static::blocksTable()->deleteByField('id', $blockId);

            if ($block) {
                static::pagesTable()->updateById(['updated_at' => time()], (int)$block['page_id']);
            }
        }

        public static function reorderBlocks(int $pageId, array $blockIds): void {
            $order = 0;

            foreach ($blockIds as $blockId) {
                static::blocksTable()->updateById(['sort_order' => $order], (int)$blockId);
                $order++;
            }
            static::pagesTable()->updateById(['updated_at' => time()], $pageId);
        }

        public static function saveAllBlocks(int $pageId, array $blocks): void {
            // Get existing block IDs
            $existing = static::getBlocksForPage($pageId);
            $existingIds = array_map(fn ($b) => (int)$b['id'], $existing);

            // Process incoming blocks
            $incomingIds = [];
            $order = 0;

            foreach ($blocks as $block) {
                $blockId = (int)($block['id'] ?? 0);
                $blockType = (string)($block['block_type'] ?? 'text');
                $content = (string)($block['content'] ?? '');
                $isHidden = (int)($block['is_hidden'] ?? 0);
                $blockVisibility = (string)($block['visibility'] ?? 'all');

                if ($blockId > 0 && in_array($blockId, $existingIds, true)) {
                    // Update existing block
                    static::blocksTable()->updateById([
                        'content' => $content,
                        'block_type' => $blockType,
                        'sort_order' => $order,
                        'is_hidden' => $isHidden,
                        'visibility' => $blockVisibility,
                    ], $blockId);
                    $incomingIds[] = $blockId;
                } else {
                    // New block (id=0 or negative temp IDs)
                    static::blocksTable()->insert([
                        'page_id' => $pageId,
                        'block_type' => $blockType,
                        'content' => $content,
                        'sort_order' => $order,
                        'is_hidden' => $isHidden,
                        'visibility' => $blockVisibility,
                        'created_at' => time(),
                    ]);
                }
                $order++;
            }

            // Delete blocks that were removed
            foreach ($existingIds as $existingId) {
                if (!in_array($existingId, $incomingIds, true)) {
                    static::blocksTable()->deleteByField('id', $existingId);
                }
            }

            static::pagesTable()->updateById(['updated_at' => time()], $pageId);
        }

        // ── Snippet CRUD ──

        public static function listSnippets(): array {
            return static::snippetsTable()->selectAll(function (SelectInterface $q): void {
                $q->orderBy(['sort_order ASC', 'id DESC']);
            });
        }

        public static function listActiveSnippets(): array {
            return static::snippetsTable()->selectAll(function (SelectInterface $q): void {
                $q->where('is_active = 1');
                $q->orderBy(['sort_order ASC', 'id ASC']);
            });
        }

        public static function getSnippetBySlug(string $slug): ?array {
            return static::snippetsTable()->selectOneByField('slug', $slug);
        }

        public static function createSnippet(string $slug, string $name, string $type, int $sortOrder = 0): array {
            $now = time();
            $data = [
                'slug' => $slug,
                'name' => $name,
                'snippet_type' => $type,
                'content' => '',
                'is_active' => 1,
                'sort_order' => $sortOrder,
                'updated_at' => $now,
                'created_at' => $now,
            ];
            $id = static::snippetsTable()->insert($data);
            $data['id'] = $id;

            return $data;
        }

        public static function updateSnippet(int $id, array $fields): void {
            $fields['updated_at'] = time();
            static::snippetsTable()->updateById($fields, $id);
        }

        public static function deleteSnippet(int $id): void {
            static::snippetsTable()->deleteByField('id', $id);
        }

        public static function listSnippetsByType(string $type): array {
            return static::snippetsTable()->selectAll(function (SelectInterface $q) use ($type): void {
                $q->where('snippet_type = ?', [$type]);
                $q->where('is_active = 1');
                $q->orderBy(['sort_order ASC', 'name ASC']);
            });
        }

        public static function getSnippetById(int $id): ?array {
            return static::snippetsTable()->selectOneByField('id', $id);
        }

        /**
         * Render a stored snippet row (active check enforced) to HTML.
         *
         * Centralises the type-dispatch (header / footer / generic markdown)
         * so any caller — public page controller, auth screen, future guest
         * surfaces — produces identical output for a given snippet. Returns
         * '' when the row is missing or inactive.
         */
        public static function renderSnippetRowHtml(?array $snippet): string {
            if (!$snippet || (int)($snippet['is_active'] ?? 0) !== 1) {
                return '';
            }
            $type = (string)($snippet['snippet_type'] ?? '');
            $content = (string)($snippet['content'] ?? '');
            $jsonData = json_decode($content, true);

            if (is_array($jsonData)) {
                if ($type === 'header' && isset($jsonData['items'])) {
                    return static::renderHeaderHtml($jsonData);
                }

                if ($type === 'footer' && isset($jsonData['columns'])) {
                    return static::renderFooterHtml($jsonData);
                }
            }
            $rendered = static::renderVariables($content);

            return Twig::get()->render('StaticPages/RawSnippet.twig', [
                'inner_html' => static::markdownToHtml($rendered),
                'kind' => $type === 'footer' ? 'footer' : 'header',
            ]);
        }

        public static function renderSnippetHtmlBySlug(string $slug): string {
            if ($slug === '') {
                return '';
            }

            return static::renderSnippetRowHtml(static::getSnippetBySlug($slug));
        }

        public static function renderSnippetHtmlById(int $id): string {
            if ($id <= 0) {
                return '';
            }

            return static::renderSnippetRowHtml(static::getSnippetById($id));
        }

        /**
         * Render the inner body (title heading + blocks) for a CMS page row.
         * Used inside renderPageShell (which adds the max-width wrapper).
         * Resolves `{title}`-style variables in the page title before
         * passing to the template (Twig auto-escapes the output).
         */
        public static function renderPageBody(array $page, string $blocksHtml): string {
            return Twig::get()->render('StaticPages/Body.twig', [
                'title' => static::renderVariables((string)($page['title'] ?? '')),
                'blocks_html' => $blocksHtml,
            ]);
        }

        /**
         * Compose the site-wide chrome (main-nav + body + main-footer) around an
         * arbitrary body fragment. Same Shell.twig used by `renderPageShell`,
         * but driven by the global `main-nav` / `main-footer` snippet slugs
         * instead of a per-page header/footer id — so every public surface
         * (auth page, registration profile form, 404 fallback, landing) goes
         * through one entry point and stays visually identical.
         */
        public static function renderSiteShell(string $bodyHtml, string $maxWidth = 'full'): string {
            $widthMap = [
                'xl' => '576px', '2xl' => '672px', '3xl' => '768px', '4xl' => '896px',
                '5xl' => '1024px', '6xl' => '1152px', '7xl' => '1280px', 'full' => '100%',
            ];
            $maxWidthPx = $widthMap[$maxWidth] ?? $widthMap['full'];

            return Twig::get()->render('StaticPages/Shell.twig', [
                'header_html' => static::renderSnippetHtmlBySlug('main-nav'),
                'footer_html' => static::renderSnippetHtmlBySlug('main-footer'),
                'body_html' => $bodyHtml,
                'max_width_px' => $maxWidthPx,
            ]);
        }

        /**
         * Compose the public-page shell: header snippet + per-page max-width
         * body wrapper + footer snippet. Visual layout lives in
         * StaticPages/Shell.twig.
         */
        public static function renderPageShell(array $page, string $innerBodyHtml): string {
            $widthMap = [
                'xl' => '576px', '2xl' => '672px', '3xl' => '768px', '4xl' => '896px',
                '5xl' => '1024px', '6xl' => '1152px', '7xl' => '1280px', 'full' => '100%',
            ];
            $maxW = (string)($page['max_width'] ?? '3xl');
            $maxWidthPx = $widthMap[$maxW] ?? $widthMap['3xl'];

            return Twig::get()->render('StaticPages/Shell.twig', [
                'header_html' => static::renderSnippetHtmlById((int)($page['header_snippet_id'] ?? 0)),
                'footer_html' => static::renderSnippetHtmlById((int)($page['footer_snippet_id'] ?? 0)),
                'body_html' => $innerBodyHtml,
                'max_width_px' => $maxWidthPx,
            ]);
        }

        /**
         * Default 404 body (heading + message + home link), rendered inside
         * the public-page shell so a missing page still shows the site
         * header and footer. Override the strings in an app-level service.
         */
        public static function renderNotFoundBody(): string {
            return Twig::get()->render('StaticPages/NotFound.twig', [
                'title' => FwI18n::t('StaticPages_NotFound_Title'),
                'text' => FwI18n::t('StaticPages_NotFound_Text'),
                'home' => FwI18n::t('StaticPages_NotFound_Home'),
            ]);
        }

        /**
         * Replace template variables in content.
         * Override in app-level service to add custom variables.
         */
        public static function renderVariables(string $content): string {
            $appConf = AppConfig::get(IniConfig::ENV_APP);
            $contacts = FwAppSettings::supportContacts();

            $vars = [
                '{title}' => $appConf->paramString('title'),
                '{base-url}' => $appConf->baseUrl(),
                '{support-email}' => $contacts['email'],
                '{support-phone}' => $contacts['phone'],
                '{support-telegram}' => $contacts['telegram'],
                '{year}' => date('Y'),
                '{date}' => date('d.m.Y'),
            ];

            $content = str_replace(array_keys($vars), array_values($vars), $content);

            // Resolve snippet references: {include:slug}, {var:slug}, and {link:slug}
            $snippets = null; // lazy load
            $content = preg_replace_callback('/\{(include|var|link):([a-z0-9_-]+)\}/i', function ($m) use (&$snippets) {
                $type = strtolower($m[1]);
                $slug = $m[2];

                if ($type === 'link') {
                    // Resolve to a link to a static page
                    $page = static::pagesTable()->selectOneByField('slug', $slug);

                    if ($page && (int)($page['is_published'] ?? 0) === 1) {
                        return Twig::get()->render('StaticPages/Link.twig', [
                            'slug' => $slug,
                            'title' => $page['title'] ?: $slug,
                        ]);
                    }

                    return $m[0]; // keep as-is if page not found
                }

                if ($snippets === null) {
                    $all = static::listActiveSnippets();
                    $snippets = [];

                    foreach ($all as $s) {
                        $snippets[$s['slug']] = $s;
                    }
                }
                $snippet = $snippets[$slug] ?? null;

                if (!$snippet) {
                    return $m[0];
                } // keep as-is if not found

                if ($type === 'var') {
                    return $snippet['content'] ?? '';
                }

                // include
                return static::markdownToHtml($snippet['content'] ?? '');
            }, $content);

            return $content;
        }

        /**
         * Render blocks to HTML with variable substitution.
         */
        /**
         * @param bool|null $isLoggedIn null = don't filter by visibility
         * @param bool $isModerator
         */
        public static function renderBlocksToHtml(array $blocks, ?bool $isLoggedIn = null, bool $isModerator = false): string {
            $normalized = [];

            foreach ($blocks as $block) {
                if ((int)($block['is_hidden'] ?? 0) === 1) {
                    continue;
                }

                // Block-level visibility filtering
                if ($isLoggedIn !== null) {
                    $vis = (string)($block['visibility'] ?? 'all');

                    if ($vis === 'guest' && $isLoggedIn) {
                        continue;
                    }

                    if ($vis === 'auth' && !$isLoggedIn) {
                        continue;
                    }

                    if ($vis === 'moderator' && !$isModerator) {
                        continue;
                    }
                }
                $content = static::renderVariables((string)($block['content'] ?? ''));
                $type = (string)($block['block_type'] ?? 'text');

                if ($type === 'heading') {
                    $normalized[] = ['kind' => 'heading', 'text' => $content];

                    continue;
                }

                if ($type === 'image') {
                    $data = json_decode($content, true);

                    if (is_array($data) && !empty($data['url'])) {
                        $normalized[] = [
                            'kind' => 'image',
                            'url' => (string)$data['url'],
                            'alt' => (string)($data['alt'] ?? ''),
                            'lightbox' => !isset($data['lightbox']) || !empty($data['lightbox']),
                        ];
                    }

                    continue;
                }

                if ($type === 'gallery') {
                    $data = json_decode($content, true);

                    if (is_array($data) && !empty($data['images'])) {
                        $images = $data['images'];
                        $lightbox = !isset($data['lightbox']) || !empty($data['lightbox']);
                        $rows = max(1, (int)($data['rows'] ?? 2));
                        $total = count($images);
                        $rowSizes = static::distributeToRows($total, $rows);

                        $galleryRows = [];
                        $imgIndex = 0;

                        foreach ($rowSizes as $rowSize) {
                            $rowImages = [];

                            for ($i = 0; $i < $rowSize && $imgIndex < $total; $i++, $imgIndex++) {
                                $img = $images[$imgIndex];
                                $rowImages[] = [
                                    'url' => (string)($img['url'] ?? ''),
                                    'alt' => (string)($img['alt'] ?? ''),
                                ];
                            }
                            $galleryRows[] = ['size' => $rowSize, 'images' => $rowImages];
                        }
                        $normalized[] = [
                            'kind' => 'gallery',
                            'lightbox' => $lightbox,
                            'rows' => $galleryRows,
                        ];
                    }

                    continue;
                }

                // text block
                $normalized[] = ['kind' => 'text', 'html' => static::markdownToHtml($content)];
            }

            return Twig::get()->render('StaticPages/Blocks.twig', ['blocks' => $normalized]);
        }

        /**
         * Distribute N items into R rows as evenly as possible.
         * First rows get the remainder (largest rows first).
         * @return int[] Array of row sizes
         */
        protected static function distributeToRows(int $total, int $rows): array {
            if ($total <= 0) {
                return [];
            }
            $rows = min($rows, $total);
            $base = intdiv($total, $rows);
            $remainder = $total % $rows;
            $result = [];

            for ($i = 0; $i < $rows; $i++) {
                $result[] = $base + ($i < $remainder ? 1 : 0);
            }

            return $result;
        }

        /**
         * Render a structured header snippet to HTML.
         *
         * PHP-side responsibility: data normalization (resolve `page` items
         * to URLs, drop unsafe/empty links, substitute `{support-*}`-style
         * placeholders). Visual layout lives in
         * StaticPages/Nav.twig.
         */
        /**
         * Per-page SEO overrides for the layout params (cascade over the global
         * defaults). Returns only the keys the page actually sets, so the global
         * site description / OG image back-fill the rest.
         *
         * @param array<string, mixed> $page
         * @return array<string, string>
         */
        public static function seoLayoutParams(array $page): array {
            $out = [];

            // Resolve `{title}`/`{base-url}`-style variables so SEO tags never
            // leak a raw placeholder (same substitution as the page body).
            $title = trim((string)($page['seo_title'] ?? '')) ?: trim((string)($page['title'] ?? ''));

            if ($title !== '') {
                $out['title'] = static::renderVariables($title);
            }

            $desc = trim((string)($page['meta_description'] ?? ''));

            if ($desc !== '') {
                $out['description'] = static::renderVariables($desc);
            }

            $ogImage = trim((string)($page['og_image'] ?? ''));

            if ($ogImage !== '') {
                $out['og_image'] = static::renderVariables($ogImage);
            }

            return $out;
        }

        public static function renderHeaderHtml(array $data): string {
            $logo = $data['logo'] ?? null;
            $items = $data['items'] ?? [];
            $layout = (string)($data['layout'] ?? 'left');
            $sticky = !empty($data['sticky']);

            $menuItems = [];

            foreach ($items as $item) {
                $type = $item['type'] ?? 'link';

                if ($type === 'divider') {
                    $menuItems[] = ['is_divider' => true];

                    continue;
                }
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? '/';
                $external = !empty($item['external']);

                if ($type === 'page') {
                    $slug = $item['slug'] ?? '';
                    $page = $slug !== '' ? static::pagesTable()->selectOneByField('slug', $slug) : null;

                    if ($page && (int)($page['is_published'] ?? 0) === 1) {
                        if ($label === '') {
                            $label = $page['title'] ?? $slug;
                        }
                        $url = '/page/view~' . $slug;
                    } else {
                        continue;
                    }
                } else {
                    // Substitute {support-email}, {year}, etc. for plain links
                    $label = static::renderVariables((string)$label);
                    $url = static::renderVariables((string)$url);

                    if (trim((string)$label) === '' || !static::isSafeUrl($url) || $url === 'mailto:' || preg_match('#^https?://(t\.me|wa\.me)/?$#i', (string)$url)) {
                        continue;
                    }
                }
                $menuItems[] = [
                    'is_divider' => false,
                    'url' => $url,
                    'label' => $label,
                    'target_attr' => $external ? ' target="_blank" rel="noopener noreferrer"' : '',
                ];
            }

            if ($logo && !empty($logo['url'])) {
                $logoView = [
                    'url' => (string)$logo['url'],
                    'alt' => (string)($logo['alt'] ?? ''),
                    'link' => (string)($logo['link'] ?? '/'),
                    'height' => (int)($logo['height'] ?? 40),
                ];
            } else {
                // No logo configured — fall back to the site favicon, linking
                // home, so every public page shows the site icon left of the menu.
                $logoView = [
                    'url' => '/favicon.ico',
                    'alt' => FwAppSettings::brandName(),
                    'link' => '/',
                    'height' => 32,
                ];
            }

            return Twig::get()->render('StaticPages/Nav.twig', [
                'logo' => $logoView,
                'menu_items' => $menuItems,
                'layout' => $layout,
                'sticky' => $sticky,
                'nav_id' => 'sp-nav-toggle-' . bin2hex(random_bytes(4)),
                'menu_label' => FwI18n::t('StaticPages_Nav_MenuToggle'),
                'theme_toggle' => static::renderThemeToggleHtml(),
            ]);
        }

        /**
         * Render the light/dark theme toggle button + its bootstrapper.
         * The widget itself lives in StaticPages/ThemeToggle.twig.
         */
        protected static function renderThemeToggleHtml(): string {
            return Twig::get()->render('StaticPages/ThemeToggle.twig', [
                'label' => FwI18n::t('StaticPages_ThemeToggle'),
            ]);
        }

        /**
         * Render a structured footer snippet to HTML.
         */
        public static function renderFooterHtml(array $data): string {
            $columns = $data['columns'] ?? [];
            $copyright = $data['copyright'] ?? '';

            // PHP-side responsibility: filter out columns where every
            // link dropped (unsafe URL, empty label after substitution,
            // unresolved page slug). The Twig template (Layout/
            // StaticPagesFooter.twig) owns the layout switch — exactly
            // 1 column → inline-row meta-footer; 2+ → column grid.
            $renderedCols = [];

            foreach ($columns as $col) {
                $title = (string)($col['title'] ?? '');
                $items = $col['items'] ?? [];
                $renderedItems = [];

                foreach ($items as $item) {
                    $type = $item['type'] ?? 'link';

                    if ($type === 'divider') {
                        continue;
                    }
                    $label = $item['label'] ?? '';
                    $url = $item['url'] ?? '/';
                    $external = !empty($item['external']);

                    if ($type === 'page') {
                        $slug = $item['slug'] ?? '';
                        $page = $slug !== '' ? static::pagesTable()->selectOneByField('slug', $slug) : null;

                        if ($page && (int)($page['is_published'] ?? 0) === 1) {
                            if ($label === '') {
                                $label = $page['title'] ?? $slug;
                            }
                            $url = '/page/view~' . $slug;
                        } else {
                            continue;
                        }
                    } else {
                        // Substitute {support-email}, {year}, etc. for plain links
                        $label = static::renderVariables((string)$label);
                        $url = static::renderVariables((string)$url);

                        // Drop links with empty label, unsafe scheme, or shells
                        // like `mailto:` / `tel:` / `https://t.me/` left over
                        // when a {support-*} placeholder rendered empty.
                        if (trim((string)$label) === ''
                            || !static::isSafeUrl($url)
                            || preg_match('#^(mailto:|tel:)\s*$#i', (string)$url)
                            || preg_match('#^https?://(t\.me|wa\.me)/?$#i', (string)$url)) {
                            continue;
                        }
                    }
                    $renderedItems[] = [
                        'url' => $url,
                        'label' => $label,
                        'target_attr' => $external ? ' target="_blank" rel="noopener noreferrer"' : '',
                    ];
                }

                if (empty($renderedItems)) {
                    continue;
                }
                $renderedCols[] = ['title' => $title, 'items' => $renderedItems];
            }

            return Twig::get()->render('StaticPages/Footer.twig', [
                'columns' => $renderedCols,
                'copyright' => $copyright !== '' ? static::renderVariables($copyright) : '',
            ]);
        }

        /**
         * URL allowlist for admin-supplied snippet/markdown links.
         *
         * Reject anything that is not a relative path, a fragment, or one of
         * the known safe schemes — drops javascript:, data:, vbscript:, file:
         * and anything else that could execute in an <a href="..."> click.
         */
        public static function isSafeUrl(string $url): bool {
            $url = trim($url);

            if ($url === '') {
                return false;
            }
            $first = $url[0];

            if ($first === '/' || $first === '#' || $first === '?') {
                return true;
            }

            return (bool)preg_match('#^(https?://|mailto:|tel:)#i', $url);
        }

        /** @var MarkdownConverter|null */
        private static ?MarkdownConverter $markdownConverter = null;

        /**
         * CommonMark converter configured for untrusted admin input:
         * - html_input='escape' converts raw HTML to entities (no <script> leak),
         * - allow_unsafe_links=false drops javascript:/data:/etc. in [text](url),
         * - DisallowedRawHtmlExtension is a belt-and-braces second layer.
         */
        protected static function markdownConverter(): MarkdownConverter {
            if (static::$markdownConverter === null) {
                $env = new Environment([
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                    'renderer' => ['soft_break' => "<br/>\n"],
                ]);
                $env->addExtension(new CommonMarkCoreExtension());
                // GFM pipe tables (| col | col |) so admin markdown tables render.
                $env->addExtension(new TableExtension());
                $env->addExtension(new DisallowedRawHtmlExtension());
                static::$markdownConverter = new MarkdownConverter($env);
            }

            return static::$markdownConverter;
        }

        /**
         * Convert markdown to HTML using league/commonmark in safe mode.
         * Used for static-page text blocks and free-form snippet content
         * — both come from admin input via the dashboard.
         */
        public static function markdownToHtml(string $md): string {
            if ($md === '') {
                return '';
            }

            return (string)static::markdownConverter()->convert($md);
        }
    }
}
