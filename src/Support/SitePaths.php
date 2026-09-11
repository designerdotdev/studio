<?php

namespace Designer\Studio\Support;

/**
 * Where an installed site lives in the host application.
 *
 * A template's `files/resources` tree is installed to `resources/designer`
 * and its `files/public` tree to `public/designer`, so a developer always
 * knows where every file of the site is. The locations are fixed on purpose:
 * the runtime provider Studio installs into the app reads the same two
 * folders, and it has to keep working after Studio itself is removed.
 */
final class SitePaths
{
    /** The folder name used under both resources/ and public/. */
    public const FOLDER = 'designer';

    /** Studio's own settings for the site (titles, SEO, page order). */
    public const MANIFEST = 'designer.json';

    /** Folder (under components/) that holds global blocks. */
    public const BLOCKS = 'blocks';

    /** Folder (under components/) that holds page layouts. */
    public const LAYOUTS = 'layouts';

    /** Folder (under public/designer) where media uploads land. */
    public const UPLOADS = 'uploads';

    /** Absolute path inside resources/designer. */
    public static function resources(string $path = ''): string
    {
        return self::join(resource_path(self::FOLDER), $path);
    }

    /** Absolute path inside public/designer. */
    public static function public(string $path = ''): string
    {
        return self::join(public_path(self::FOLDER), $path);
    }

    /** Root-relative URL for a file inside public/designer ("/designer/…"). */
    public static function url(string $path = ''): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return '/' . self::FOLDER . ($path === '' ? '' : '/' . $path);
    }

    public static function views(string $path = ''): string
    {
        return self::join(self::resources('views'), $path);
    }

    public static function pages(string $path = ''): string
    {
        return self::join(self::views('pages'), $path);
    }

    public static function components(string $path = ''): string
    {
        return self::join(self::views('components'), $path);
    }

    public static function data(string $path = ''): string
    {
        return self::join(self::resources('data'), $path);
    }

    public static function manifest(): string
    {
        return self::resources(self::MANIFEST);
    }

    /** A site is installed once its pages folder exists. */
    public static function installed(): bool
    {
        return is_dir(self::pages());
    }

    /** Path relative to the application root, for messages and the code tree. */
    public static function relative(string $absolute): string
    {
        $base = rtrim(base_path(), '/') . '/';

        return str_starts_with($absolute, $base) ? substr($absolute, strlen($base)) : $absolute;
    }

    protected static function join(string $base, string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === '' ? $base : $base . '/' . $path;
    }
}
