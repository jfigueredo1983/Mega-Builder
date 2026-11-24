<?php
/**
 * Mega Builder — Stage 4 & Stage 5 Media Manager Service
 * Manages folders, image uploads, SVG XML sanitization (SvgSanitizer), search, and deletion.
 */

declare(strict_types=1);

namespace MegaBuilder\Service;

use MegaBuilder\Security\HtmlSanitizer;
use MegaBuilder\Security\SvgSanitizer;

final class MediaManagerService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'];
    private const MAX_UPLOAD_BYTES = 10485760; // 10 MB

    public static function getBaseUploadDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/views/img/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            self::writeIndexGuard($dir);
        }
        return $dir;
    }

    public static function getBaseUploadUrl(): string
    {
        return '/modules/megabuilder/views/img/uploads';
    }

    /**
     * Sanitize a relative folder slug, blocking any ../ path traversal.
     */
    public static function sanitizeFolderSlug(string $folder): string
    {
        $folder = trim(str_replace(['\\', '..'], ['/', ''], $folder), '/ ');
        if ($folder === '') {
            return '';
        }
        $parts = array_filter(explode('/', $folder), static fn(string $p): bool => $p !== '' && $p !== '.');
        $cleanParts = [];
        foreach (array_slice($parts, 0, 3) as $part) {
            $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', $part));
            $slug = trim($slug, '-');
            if ($slug !== '') {
                $cleanParts[] = substr($slug, 0, 48);
            }
        }
        return implode('/', $cleanParts);
    }

    /**
     * List all folders and media assets (with optional search query & folder filter).
     *
     * @return array{folders: array<int, string>, files: array<int, array<string, mixed>>}
     */
    public static function listMedia(string $folder = '', string $search = ''): array
    {
        $baseDir = self::getBaseUploadDir();
        $baseUrl = self::getBaseUploadUrl();
        $cleanFolder = self::sanitizeFolderSlug($folder);
        $targetDir = $cleanFolder !== '' ? $baseDir . '/' . $cleanFolder : $baseDir;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
            self::writeIndexGuard($targetDir);
        }

        // Collect all top-level and nested folders
        $folders = ['showroom', 'banners', 'icons'];
        foreach ($folders as $defaultSub) {
            $subPath = $baseDir . '/' . $defaultSub;
            if (!is_dir($subPath)) {
                @mkdir($subPath, 0755, true);
                self::writeIndexGuard($subPath);
            }
        }

        $entries = @scandir($baseDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php') {
                continue;
            }
            if (is_dir($baseDir . '/' . $entry) && !in_array($entry, $folders, true)) {
                $folders[] = $entry;
            }
        }

        $files = [];
        $dirEntries = @scandir($targetDir) ?: [];
        $q = strtolower(trim($search));

        foreach ($dirEntries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php') {
                continue;
            }
            $fullPath = $targetDir . '/' . $entry;
            if (!is_file($fullPath)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }
            if ($q !== '' && !str_contains(strtolower($entry), $q)) {
                continue;
            }
            $relPath = ($cleanFolder !== '' ? $cleanFolder . '/' : '') . $entry;
            $files[] = [
                'name'       => $entry,
                'folder'     => $cleanFolder,
                'rel_path'   => $relPath,
                'url'        => $baseUrl . '/' . $relPath,
                'ext'        => $ext,
                'size_bytes' => (int) @filesize($fullPath),
                'updated_at' => date('Y-m-d H:i:s', (int) @filemtime($fullPath)),
            ];
        }

        return [
            'folders' => array_values(array_unique($folders)),
            'files'   => $files,
        ];
    }

    /**
     * Create a new subfolder inside uploads.
     *
     * @return array{ok: bool, folder: string, message: string}
     */
    public static function createFolder(string $folderName): array
    {
        $slug = self::sanitizeFolderSlug($folderName);
        if ($slug === '') {
            return ['ok' => false, 'folder' => '', 'message' => 'Invalid folder name.'];
        }
        $fullDir = self::getBaseUploadDir() . '/' . $slug;
        if (!is_dir($fullDir)) {
            @mkdir($fullDir, 0755, true);
            self::writeIndexGuard($fullDir);
        }
        return ['ok' => true, 'folder' => $slug, 'message' => 'Folder created.'];
    }

    /**
     * Save an uploaded file or inline base64/SVG payload with strict MIME & SvgSanitizer checks.
     *
     * @return array{ok: bool, file?: array<string, mixed>, message: string}
     */
    public static function saveMediaContent(string $rawFilename, string $rawBytes, string $folder = ''): array
    {
        if (strlen($rawBytes) === 0 || strlen($rawBytes) > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'message' => 'File is empty or exceeds the 10 MB limit.'];
        }

        $ext = strtolower(pathinfo($rawFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['ok' => false, 'message' => 'Unsupported file extension. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS)];
        }

        $baseName = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($rawFilename, PATHINFO_FILENAME)));
        $baseName = trim($baseName, '-') ?: 'asset';
        $safeFilename = substr($baseName, 0, 60) . '.' . $ext;

        // If SVG, run SvgSanitizer and reject if malformed
        if ($ext === 'svg') {
            $cleanedSvg = SvgSanitizer::sanitizeSvg($rawBytes);
            if ($cleanedSvg === null) {
                return ['ok' => false, 'message' => 'Malicious or malformed SVG XML rejected by SvgSanitizer.'];
            }
            $rawBytes = $cleanedSvg;
        }

        $cleanFolder = self::sanitizeFolderSlug($folder);
        $targetDir = self::getBaseUploadDir() . ($cleanFolder !== '' ? '/' . $cleanFolder : '');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
            self::writeIndexGuard($targetDir);
        }

        $destPath = $targetDir . '/' . $safeFilename;
        if (@file_put_contents($destPath, $rawBytes) === false) {
            return ['ok' => false, 'message' => 'Failed to write file to disk.'];
        }

        $relPath = ($cleanFolder !== '' ? $cleanFolder . '/' : '') . $safeFilename;
        return [
            'ok'      => true,
            'message' => 'Media uploaded and sanitized.',
            'file'    => [
                'name'       => $safeFilename,
                'folder'     => $cleanFolder,
                'rel_path'   => $relPath,
                'url'        => self::getBaseUploadUrl() . '/' . $relPath,
                'ext'        => $ext,
                'size_bytes' => strlen($rawBytes),
            ],
        ];
    }

    /**
     * Delete a media file by relative path.
     */
    public static function deleteMedia(string $relPath): bool
    {
        $relPath = trim(str_replace(['\\', '..'], ['/', ''], $relPath), '/ ');
        if ($relPath === '' || str_ends_with($relPath, 'index.php')) {
            return false;
        }
        $fullPath = self::getBaseUploadDir() . '/' . $relPath;
        if (is_file($fullPath)) {
            return @unlink($fullPath);
        }
        return false;
    }

    public static function writeIndexGuard(string $dir): void
    {
        $guardFile = rtrim($dir, '/\\') . '/index.php';
        if (!is_file($guardFile)) {
            @file_put_contents(
                $guardFile,
                "<?php\nheader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');\nheader('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');\nheader('Cache-Control: no-store, no-cache, must-revalidate');\nheader('Cache-Control: post-check=0, pre-check=0', false);\nheader('Pragma: no-cache');\nheader('Location: ../');\nexit;\n"
            );
        }
    }
}
