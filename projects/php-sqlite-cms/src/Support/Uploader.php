<?php

declare(strict_types=1);

namespace Cms\Support;

use Cms\App;

/**
 * Validated file uploads: extension/MIME allow-list, size limit, random names,
 * year/month folders and optional GD thumbnails for raster images.
 */
final class Uploader
{
    public function __construct(private App $app)
    {
    }

    /**
     * Store an uploaded file.
     *
     * @param array<string, mixed> $file Entry from $_FILES
     * @return array{ok: bool, error?: string, media?: array<string, mixed>}
     */
    public function store(array $file): array
    {
        $maxBytes = (int) $this->app->config('uploads.max_bytes', 5 * 1024 * 1024);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->uploadErrorMessage($error)];
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if (!is_uploaded_file($temporary) && !is_file($temporary)) {
            return ['ok' => false, 'error' => 'The upload could not be read.'];
        }
        if ($size > $maxBytes) {
            return ['ok' => false, 'error' => 'File is larger than ' . human_bytes($maxBytes) . '.'];
        }

        $allowed = (array) $this->app->config('uploads.allowed_mime', []);
        $mime = $this->detectMime($temporary);
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => sprintf('Unsupported file type (%s).', $mime)];
        }

        $original = (string) ($file['name'] ?? 'upload');
        $baseName = pathinfo($original, PATHINFO_FILENAME);
        $extension = $allowed[$mime];
        $slug = Str::slug($baseName);
        $filename = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

        $relativeDir = date('Y/m');
        $targetDir = $this->app->path('uploads') . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0o775, true) && !is_dir($targetDir)) {
            return ['ok' => false, 'error' => 'Upload directory is not writable.'];
        }

        $absolute = $targetDir . '/' . $filename;
        if (!move_uploaded_file($temporary, $absolute) && !rename($temporary, $absolute)) {
            return ['ok' => false, 'error' => 'Could not move the uploaded file.'];
        }

        @chmod($absolute, 0o664);

        $width = null;
        $height = null;
        $thumbRelative = null;

        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
            $dimensions = @getimagesize($absolute);
            if (is_array($dimensions)) {
                $width = (int) $dimensions[0];
                $height = (int) $dimensions[1];
            }
            $thumbRelative = $this->createThumbnail($absolute, $targetDir, $filename, $mime);
        }

        return [
            'ok' => true,
            'media' => [
                'filename' => $filename,
                'original_name' => $original,
                'path' => 'uploads/' . $relativeDir . '/' . $filename,
                'thumb_path' => $thumbRelative,
                'mime_type' => $mime,
                'size' => (int) (filesize($absolute) ?: $size),
                'width' => $width,
                'height' => $height,
                'alt_text' => Str::excerpt(str_replace(['-', '_'], ' ', $baseName), 90),
            ],
        ];
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $absolute = $this->app->path('uploads') . '/' . ltrim(str_replace('uploads/', '', $relativePath), '/');
        $uploadsRoot = realpath($this->app->path('uploads'));
        $target = realpath($absolute);

        // Never delete anything outside public/uploads.
        if ($uploadsRoot !== false && $target !== false && str_starts_with($target, $uploadsRoot) && is_file($target)) {
            @unlink($target);
        }
    }

    private function detectMime(string $path): string
    {
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return (string) (mime_content_type($path) ?: 'application/octet-stream');
    }

    private function createThumbnail(string $source, string $targetDir, string $filename, string $mime): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $thumbName = 'thumb-' . $filename;
        $thumbPath = $targetDir . '/' . $thumbName;
        $maxWidth = 480;
        $maxHeight = 480;

        try {
            $image = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($source),
                'image/png' => @imagecreatefrompng($source),
                'image/gif' => @imagecreatefromgif($source),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }

        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, (int) $transparent);
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $thumbPath, 82),
            'image/png' => imagepng($canvas, $thumbPath, 8),
            'image/gif' => imagegif($canvas, $thumbPath),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $thumbPath, 82) : false,
            default => false,
        };

        imagedestroy($image);
        imagedestroy($canvas);

        if (!$saved) {
            return null;
        }

        $relative = str_replace(dirname($targetDir), '', $thumbPath);

        return 'uploads' . str_replace('\\', '/', $relative);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder configured.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
            default => 'The upload failed.',
        };
    }
}
