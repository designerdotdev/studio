<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Facades\File;

/**
 * Small copies of the images attached to assistant messages, kept under
 * storage/studio/assistant/attachments/. An attachment is only a URL into
 * the media library, and that file can be renamed, moved or deleted later;
 * the copy taken when the message is sent keeps the conversation readable.
 *
 * Copies are named by a hash of the original's contents, so the same image
 * attached twice is stored once.
 */
class Attachments
{
    /** The longest side of a stored preview, in pixels. */
    protected const SIZE = 480;

    /** Originals no larger than this are copied whole when they cannot be resized (SVG, GIF, no GD). */
    protected const COPY_LIMIT = 524288;

    public function __construct(
        protected StudioStorage $storage
    ) {}

    protected function dir(): string
    {
        $dir = $this->storage->getBasePath() . '/assistant/attachments';

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    /** The file an attachment URL points at, or null when it is gone or outside the site's public files. */
    public function original(string $url): ?string
    {
        $public = rtrim(SitePaths::public(), '/');
        $relative = ltrim(preg_replace('#^/designer/#', '', parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $absolute = $relative !== '' ? realpath($public . '/' . $relative) : false;

        return $absolute && str_starts_with($absolute, $public . '/') && is_file($absolute) ? $absolute : null;
    }

    /**
     * Take a preview of each attachment that can still be read.
     *
     * @param  array<int, mixed>  $urls
     * @return array<string, string> attachment URL => preview file name
     */
    public function snapshot(array $urls): array
    {
        $previews = [];

        foreach ($urls as $url) {
            if (!is_string($url) || !($original = $this->original($url))) {
                continue;
            }

            try {
                if ($name = $this->preview($original)) {
                    $previews[$url] = $name;
                }
            } catch (\Throwable $e) {
                // A preview is a nicety; the turn goes ahead without it
                report($e);
            }
        }

        return $previews;
    }

    /** The stored preview's path, or null when there is none by that name. */
    public function path(string $name): ?string
    {
        if (!preg_match('/^[a-f0-9]{40}\.(webp|png|jpe?g|gif|svg|avif)$/', $name)) {
            return null;
        }

        $path = $this->dir() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    protected function preview(string $original): ?string
    {
        $hash = sha1_file($original);

        foreach (File::glob($this->dir() . '/' . $hash . '.*') as $existing) {
            return basename($existing);
        }

        if ($image = $this->read($original)) {
            $name = $hash . '.webp';
            $saved = imagewebp($this->fit($image), $this->dir() . '/' . $name, 80);

            return $saved ? $name : null;
        }

        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (in_array($extension, ['webp', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'avif'], true) && filesize($original) <= self::COPY_LIMIT) {
            $name = $hash . '.' . $extension;
            File::copy($original, $this->dir() . '/' . $name);

            return $name;
        }

        return null;
    }

    /** Decode a raster image with GD; null for anything it cannot (or should not) redraw. */
    protected function read(string $path): ?\GdImage
    {
        if (!function_exists('imagewebp') || !($info = @getimagesize($path))) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false, // GIFs may be animated, SVGs are not rasters: both are copied instead
        };

        return $image ?: null;
    }

    protected function fit(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::SIZE / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        return $canvas;
    }
}
