<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Utils\Upload {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    /**
     * Reusable controller endpoints for uploading and deleting a PUBLIC image
     * (web-accessible, no auth on read — e.g. CMS block images, OG/social
     * preview images that crawlers must fetch anonymously).
     *
     * The using controller MUST provide three static methods:
     *   - isAllowed(): bool        — write-access gate for this admin area.
     *   - uploadDir(): string      — filesystem dir to store files (created if missing).
     *   - uploadWebPath(): string  — public URL prefix that maps to uploadDir().
     *
     * Routes are auto-dispatched: `<controller-url>~uploadImage` (multipart
     * `file`) returns {success, url}; `<controller-url>~deleteImage` (JSON
     * {url}) removes a previously uploaded file (URL must live under
     * uploadWebPath() — external URLs are rejected, so callers can clear an
     * external value client-side without a server round-trip failing loudly).
     */
    trait PublicImageUploadTrait {
        public static function post__uploadImage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $uploadDir = static::uploadDir();

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0o755, true);
            }

            $files = $globals->readFilesValue('file');

            if (empty($files) || empty($files['tmp_name'])) {
                return ControllerTools::JSON(['error' => 'No file uploaded'], status: 400);
            }

            // Validate: images only
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $mime = mime_content_type($files['tmp_name']);

            if (!in_array($mime, $allowedMimes, true)) {
                return ControllerTools::JSON(['error' => 'Only images allowed'], status: 400);
            }

            // Max 5MB
            if ($files['size'] > 5 * 1024 * 1024) {
                return ControllerTools::JSON(['error' => 'File too large (max 5MB)'], status: 400);
            }

            $ext = pathinfo($files['name'], PATHINFO_EXTENSION);
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array(strtolower($ext), $allowedExts, true)) {
                $ext = 'jpg';
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($files['tmp_name'], $destPath)) {
                return ControllerTools::JSON(['error' => 'Upload failed'], status: 500);
            }

            $webUrl = static::uploadWebPath() . $storedName;

            return ControllerTools::JSON([
                'success' => true,
                'url' => $webUrl,
                'name' => $files['name'],
                'size' => $files['size'],
            ]);
        }

        public static function post__deleteImage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $url = trim((string)$globals->readPostValue('url', ''));
            $prefix = static::uploadWebPath();

            if ($url === '' || !str_starts_with($url, $prefix)) {
                return ControllerTools::JSON(['error' => 'Invalid URL'], status: 400);
            }

            $filename = basename($url);
            $filePath = static::uploadDir() . DIRECTORY_SEPARATOR . $filename;

            if (is_file($filePath)) {
                unlink($filePath);
            }

            return ControllerTools::JSON(['success' => true]);
        }
    }
}
