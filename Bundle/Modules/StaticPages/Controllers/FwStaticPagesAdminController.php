<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\StaticPages\FwStaticPagesService;
    use PHPCraftdream\Garnet\Bundle\Utils\Upload\PublicImageUploadTrait;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    abstract class FwStaticPagesAdminController extends FrameworkController {
        // Public image upload/delete endpoints (post__uploadImage / post__deleteImage)
        // are shared with other admin areas (e.g. System Settings OG image).
        use PublicImageUploadTrait;

        abstract protected static function isAllowed(): bool;

        abstract protected static function service(): FwStaticPagesService;

        abstract protected static function getLabels(): array;

        /**
         * Return the filesystem path for storing page images (must be web-accessible).
         */
        abstract protected static function uploadDir(): string;

        /**
         * Return the web-accessible URL prefix for page images (e.g. '/upload/MyApp/pages/').
         */
        abstract protected static function uploadWebPath(): string;

        public static function post__list(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $pages = static::service()::listPages();

            return ControllerTools::JSON(['pages' => $pages]);
        }

        public static function post__create(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $slug = trim((string)$globals->readPostValue('slug', ''));
            $title = trim((string)$globals->readPostValue('title', ''));

            if ($slug === '') {
                return ControllerTools::JSON(['error' => 'Slug is required'], status: 400);
            }
            $actor = Account::fromSession();
            $page = static::service()::createPage($slug, $title, $actor ? $actor->id() : 0);

            return ControllerTools::JSON(['success' => true, 'page' => $page]);
        }

        public static function post__update(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $id = (int)$globals->readPostValue('id', '0');

            if ($id <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            $fields = [];
            $title = $globals->readPostValue('title');

            if ($title !== null) {
                $fields['title'] = trim((string)$title);
            }
            $slug = $globals->readPostValue('slug');

            if ($slug !== null) {
                $fields['slug'] = trim((string)$slug);
            }
            $isPublished = $globals->readPostValue('is_published');

            if ($isPublished !== null) {
                $fields['is_published'] = (int)$isPublished;
            }
            $metaDesc = $globals->readPostValue('meta_description');

            if ($metaDesc !== null) {
                $fields['meta_description'] = trim((string)$metaDesc);
            }
            $seoTitle = $globals->readPostValue('seo_title');

            if ($seoTitle !== null) {
                $fields['seo_title'] = substr(trim((string)$seoTitle), 0, 255);
            }
            $ogImage = $globals->readPostValue('og_image');

            if ($ogImage !== null) {
                $fields['og_image'] = substr(trim((string)$ogImage), 0, 500);
            }
            $sortOrder = $globals->readPostValue('sort_order');

            if ($sortOrder !== null) {
                $fields['sort_order'] = (int)$sortOrder;
            }
            $maxWidth = $globals->readPostValue('max_width');

            if ($maxWidth !== null) {
                $fields['max_width'] = trim((string)$maxWidth);
            }
            $visibility = $globals->readPostValue('visibility');

            if ($visibility !== null) {
                $allowed = ['all', 'guest', 'auth', 'moderator'];
                $fields['visibility'] = in_array($visibility, $allowed, true) ? $visibility : 'all';
            }

            $headerSnippetId = $globals->readPostValue('header_snippet_id');

            if ($headerSnippetId !== null) {
                $fields['header_snippet_id'] = $headerSnippetId === '' || $headerSnippetId === '0' ? null : (int)$headerSnippetId;
            }
            $footerSnippetId = $globals->readPostValue('footer_snippet_id');

            if ($footerSnippetId !== null) {
                $fields['footer_snippet_id'] = $footerSnippetId === '' || $footerSnippetId === '0' ? null : (int)$footerSnippetId;
            }

            $actor = Account::fromSession();
            static::service()::updatePage($id, $fields, $actor ? $actor->id() : 0);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__delete(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $id = (int)$globals->readPostValue('id', '0');

            if ($id <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            static::service()::deletePage($id);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__blocks(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $pageId = (int)$globals->readPostValue('page_id', '0');

            if ($pageId <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            $blocks = static::service()::getBlocksForPage($pageId);

            return ControllerTools::JSON(['blocks' => $blocks]);
        }

        public static function post__addBlock(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $pageId = (int)$globals->readPostValue('page_id', '0');
            $blockType = trim((string)$globals->readPostValue('block_type', 'text'));
            $content = (string)$globals->readPostValue('content', '');
            $sortOrder = (int)$globals->readPostValue('sort_order', '0');

            if ($pageId <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            $block = static::service()::addBlock($pageId, $blockType, $content, $sortOrder);

            return ControllerTools::JSON(['success' => true, 'block' => $block]);
        }

        public static function post__updateBlock(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $blockId = (int)$globals->readPostValue('id', '0');

            if ($blockId <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            $fields = [];
            $content = $globals->readPostValue('content');

            if ($content !== null) {
                $fields['content'] = (string)$content;
            }
            $blockType = $globals->readPostValue('block_type');

            if ($blockType !== null) {
                $fields['block_type'] = trim((string)$blockType);
            }
            $isHidden = $globals->readPostValue('is_hidden');

            if ($isHidden !== null) {
                $fields['is_hidden'] = (int)$isHidden;
            }

            static::service()::updateBlock($blockId, $fields);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__deleteBlock(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $blockId = (int)$globals->readPostValue('id', '0');

            if ($blockId <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            static::service()::deleteBlock($blockId);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__saveBlocks(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $pageId = (int)$globals->readPostValue('page_id', '0');

            if ($pageId <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $blocksJson = $globals->readPostValue('blocks', '[]');
            $blocks = is_array($blocksJson) ? $blocksJson : json_decode((string)$blocksJson, true);

            if (!is_array($blocks)) {
                return ControllerTools::JSON(['error' => 'Invalid blocks data'], status: 400);
            }

            static::service()::saveAllBlocks($pageId, $blocks);

            // Return fresh blocks
            $freshBlocks = static::service()::getBlocksForPage($pageId);

            return ControllerTools::JSON(['success' => true, 'blocks' => $freshBlocks]);
        }

        public static function post__reorderBlocks(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $pageId = (int)$globals->readPostValue('page_id', '0');
            $blockIdsRaw = $globals->readPostValue('block_ids', '');

            if ($pageId <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            $blockIds = is_array($blockIdsRaw) ? $blockIdsRaw : json_decode((string)$blockIdsRaw, true);

            if (!is_array($blockIds)) {
                return ControllerTools::JSON(['error' => 'Invalid block_ids'], status: 400);
            }
            static::service()::reorderBlocks($pageId, $blockIds);

            return ControllerTools::JSON(['success' => true]);
        }

        // ── Snippet endpoints ──

        public static function post__snippetsList(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $snippets = static::service()::listSnippets();

            return ControllerTools::JSON(['snippets' => $snippets]);
        }

        public static function post__snippetCreate(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $slug = trim((string)$globals->readPostValue('slug', ''));
            $name = trim((string)$globals->readPostValue('name', ''));
            $type = trim((string)$globals->readPostValue('snippet_type', 'block'));
            $sortOrder = (int)$globals->readPostValue('sort_order', '0');

            if ($slug === '') {
                return ControllerTools::JSON(['error' => 'Slug is required'], status: 400);
            }
            $allowedTypes = ['header', 'footer', 'variable', 'block'];

            if (!in_array($type, $allowedTypes, true)) {
                $type = 'block';
            }
            $snippet = static::service()::createSnippet($slug, $name, $type, $sortOrder);

            return ControllerTools::JSON(['success' => true, 'snippet' => $snippet]);
        }

        public static function post__snippetUpdate(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $id = (int)$globals->readPostValue('id', '0');

            if ($id <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            $fields = [];
            $name = $globals->readPostValue('name');

            if ($name !== null) {
                $fields['name'] = trim((string)$name);
            }
            $slug = $globals->readPostValue('slug');

            if ($slug !== null) {
                $fields['slug'] = trim((string)$slug);
            }
            $snippetType = $globals->readPostValue('snippet_type');

            if ($snippetType !== null) {
                $fields['snippet_type'] = trim((string)$snippetType);
            }
            $content = $globals->readPostValue('content');

            if ($content !== null) {
                $fields['content'] = (string)$content;
            }
            $isActive = $globals->readPostValue('is_active');

            if ($isActive !== null) {
                $fields['is_active'] = (int)$isActive;
            }
            $sortOrder = $globals->readPostValue('sort_order');

            if ($sortOrder !== null) {
                $fields['sort_order'] = (int)$sortOrder;
            }

            static::service()::updateSnippet($id, $fields);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__snippetDelete(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $id = (int)$globals->readPostValue('id', '0');

            if ($id <= 0) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }
            static::service()::deleteSnippet($id);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__headerFooterSnippets(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            $headers = static::service()::listSnippetsByType('header');
            $footers = static::service()::listSnippetsByType('footer');

            return ControllerTools::JSON(['headers' => $headers, 'footers' => $footers]);
        }

        public static function post__variables(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            return ControllerTools::JSON(['variables' => [
                '{title}', '{base-url}', '{support-email}', '{support-phone}',
                '{support-telegram}', '{year}', '{date}', '{include:slug}', '{var:slug}', '{link:slug}',
            ]]);
        }
    }
}
