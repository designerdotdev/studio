<?php

namespace Designer\Studio\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Designer\Studio\Support\SitePaths;
use InvalidArgumentException;

/**
 * The site's asset library: everything under public/designer — the images
 * and scripts the template shipped, and whatever has been uploaded since
 * (image fields upload into public/designer/uploads).
 *
 * Paths handed in and out are relative to that root ("images/hero.jpg",
 * "" for the root itself) and are normalised and fenced — a path that
 * tries to escape the root throws an InvalidArgumentException (the
 * controller answers 422). URLs are root-relative ("/designer/images/…"),
 * so a page that stores one works on any host.
 */
final class MediaLibrary
{
    /** Folders listed but never modified here (none — the site owns them all) */
    protected const READ_ONLY = [];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    public function root(): string
    {
        return SitePaths::public();
    }

    /** Public URL for a relative path */
    public function url(string $path): string
    {
        return SitePaths::url($path);
    }

    /**
     * @return array{dir: string, parent: ?string, folders: array, files: array}
     */
    public function list(string $dir = ''): array
    {
        $dir = $this->normalise($dir);
        $absolute = $this->absolute($dir);

        if (!File::isDirectory($absolute)) {
            if ($dir === '') {
                File::makeDirectory($absolute, 0755, true);
            } else {
                throw new InvalidArgumentException('That folder does not exist.');
            }
        }

        $folders = [];
        $files = [];

        foreach (File::directories($absolute) as $folder) {
            $name = basename($folder);
            $relative = ltrim($dir . '/' . $name, '/');

            $folders[] = [
                'name' => $name,
                'path' => $relative,
                'count' => count(array_filter(File::files($folder), fn ($file) => $this->isMedia($file->getFilename()))),
                'readonly' => $this->isReadOnly($relative),
            ];
        }

        // Only images: the folder also holds the site's scripts and text
        // files, which belong to Code mode, not to an image picker.
        foreach (File::files($absolute) as $file) {
            if ($this->isMedia($file->getFilename())) {
                $files[] = $this->entry(ltrim($dir . '/' . $file->getFilename(), '/'));
            }
        }

        usort($folders, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return [
            'dir' => $dir,
            'parent' => $dir === '' ? null : (str_contains($dir, '/') ? dirname($dir) : ''),
            'readonly' => $this->isReadOnly($dir),
            'folders' => $folders,
            'files' => $files,
        ];
    }

    /** Store an uploaded image under $dir, keeping a readable, unique name */
    public function upload(UploadedFile $file, string $dir = ''): array
    {
        $dir = $this->writable($this->normalise($dir));
        $absoluteDir = $this->absolute($dir);

        if (!File::isDirectory($absoluteDir)) {
            File::makeDirectory($absoluteDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $name = $this->uniqueName($absoluteDir, $base, $extension);

        $file->move($absoluteDir, $name);

        return $this->entry(ltrim($dir . '/' . $name, '/'));
    }

    public function createFolder(string $dir, string $name): string
    {
        $dir = $this->writable($this->normalise($dir));
        $name = $this->cleanName($name);
        $relative = ltrim($dir . '/' . $name, '/');
        $absolute = $this->absolute($relative);

        if (File::exists($absolute)) {
            throw new InvalidArgumentException('A folder with that name already exists.');
        }

        File::makeDirectory($absolute, 0755, true);

        return $relative;
    }

    /** Rename a file (extension preserved) or a folder in place */
    public function rename(string $path, string $newName): string
    {
        $path = $this->writable($this->normalise($path));
        $absolute = $this->absolute($path);

        if (!File::exists($absolute)) {
            throw new InvalidArgumentException('That item no longer exists.');
        }

        $dir = str_contains($path, '/') ? dirname($path) : '';

        if (File::isDirectory($absolute)) {
            $name = $this->cleanName($newName);
        } else {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $stem = Str::slug(pathinfo($newName, PATHINFO_FILENAME)) ?: 'image';
            $name = $extension !== '' ? "{$stem}.{$extension}" : $stem;
        }

        $target = ltrim($dir . '/' . $name, '/');

        if ($target === $path) {
            return $path;
        }

        if (File::exists($this->absolute($target))) {
            throw new InvalidArgumentException('Something with that name already exists here.');
        }

        File::move($absolute, $this->absolute($target));

        return $target;
    }

    public function move(string $path, string $toDir): string
    {
        $path = $this->writable($this->normalise($path));
        $toDir = $this->writable($this->normalise($toDir));
        $absolute = $this->absolute($path);

        if (!File::exists($absolute)) {
            throw new InvalidArgumentException('That item no longer exists.');
        }

        if (File::isDirectory($absolute) && ($toDir === $path || str_starts_with($toDir, $path . '/'))) {
            throw new InvalidArgumentException('A folder cannot be moved into itself.');
        }

        $targetDir = $this->absolute($toDir);

        if (!File::isDirectory($targetDir)) {
            throw new InvalidArgumentException('That destination folder does not exist.');
        }

        $target = ltrim($toDir . '/' . basename($path), '/');

        if ($target === $path) {
            return $path;
        }

        if (File::exists($this->absolute($target))) {
            throw new InvalidArgumentException('Something with that name already exists in the destination.');
        }

        File::move($absolute, $this->absolute($target));

        return $target;
    }

    /** Copy a file beside itself as "<name>-copy.<ext>" (unique) */
    public function duplicate(string $path): array
    {
        $path = $this->writable($this->normalise($path));
        $absolute = $this->absolute($path);

        if (!File::isFile($absolute)) {
            throw new InvalidArgumentException('Only files can be duplicated.');
        }

        $dir = str_contains($path, '/') ? dirname($path) : '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $stem = pathinfo($path, PATHINFO_FILENAME) . '-copy';
        $name = $this->uniqueName($this->absolute($dir), $stem, $extension);

        File::copy($absolute, $this->absolute(ltrim($dir . '/' . $name, '/')));

        return $this->entry(ltrim($dir . '/' . $name, '/'));
    }

    /** Delete a file, or an empty folder */
    public function delete(string $path): void
    {
        $path = $this->writable($this->normalise($path));

        if ($path === '') {
            throw new InvalidArgumentException('The library root cannot be deleted.');
        }

        $absolute = $this->absolute($path);

        if (File::isDirectory($absolute)) {
            if (count(File::allFiles($absolute)) > 0 || count(File::directories($absolute)) > 0) {
                throw new InvalidArgumentException('Empty the folder before deleting it.');
            }

            File::deleteDirectory($absolute);

            return;
        }

        if (!File::isFile($absolute)) {
            throw new InvalidArgumentException('That file no longer exists.');
        }

        File::delete($absolute);
    }

    /** Every folder path, for "Move to…" menus */
    public function folders(): array
    {
        $root = $this->root();

        if (!File::isDirectory($root)) {
            return [];
        }

        $paths = [];

        foreach (File::directories($root) as $folder) {
            $paths = [...$paths, ...$this->walk($folder, basename($folder))];
        }

        sort($paths);

        return array_values(array_filter($paths, fn ($p) => !$this->isReadOnly($p)));
    }

    /* ---------------------------------------------------------------- */

    protected function walk(string $absolute, string $relative): array
    {
        $out = [$relative];

        foreach (File::directories($absolute) as $child) {
            $out = [...$out, ...$this->walk($child, $relative . '/' . basename($child))];
        }

        return $out;
    }

    protected function isMedia(string $filename): bool
    {
        return !str_starts_with($filename, '.')
            && in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    protected function entry(string $relative): array
    {
        $absolute = $this->absolute($relative);
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);

        $width = $height = null;

        if ($isImage && $extension !== 'svg' && ($size = @getimagesize($absolute))) {
            [$width, $height] = $size;
        }

        return [
            'name' => basename($relative),
            'path' => $relative,
            'url' => $this->url($relative),
            'size' => File::size($absolute),
            'mtime' => File::lastModified($absolute),
            'type' => $isImage ? 'image' : 'file',
            'ext' => $extension,
            'width' => $width,
            'height' => $height,
            'readonly' => $this->isReadOnly($relative),
        ];
    }

    protected function uniqueName(string $absoluteDir, string $stem, string $extension): string
    {
        $suffix = $extension !== '' ? '.' . $extension : '';
        $name = $stem . $suffix;
        $counter = 2;

        while (File::exists($absoluteDir . '/' . $name)) {
            $name = "{$stem}-{$counter}{$suffix}";
            $counter++;
        }

        return $name;
    }

    protected function cleanName(string $name): string
    {
        $clean = Str::slug($name);

        if ($clean === '') {
            throw new InvalidArgumentException('Give it a name made of letters and numbers.');
        }

        return $clean;
    }

    /** Collapse a user-supplied path and reject anything that escapes the root */
    protected function normalise(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || str_contains($segment, "\0")) {
                throw new InvalidArgumentException('That path is not allowed.');
            }

            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    protected function absolute(string $relative): string
    {
        return rtrim($this->root() . '/' . $relative, '/');
    }

    protected function isReadOnly(string $relative): bool
    {
        foreach (self::READ_ONLY as $folder) {
            if ($relative === $folder || str_starts_with($relative, $folder . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function writable(string $relative): string
    {
        if ($this->isReadOnly($relative)) {
            throw new InvalidArgumentException('That folder is managed by the section library and cannot be changed here.');
        }

        return $relative;
    }
}
