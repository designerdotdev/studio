<?php

namespace Designer\Studio\Support;

use Illuminate\Support\Facades\Route;

class SiteUrls
{
    /**
     * Public URL for a page slug. The home page lives at '/' only when
     * Studio actually owns the root route — when the app defines its own
     * '/', the home page is reachable at '/{home_slug}' instead.
     */
    /**
     * The slug of the home page. The site document's `home_slug` (set from
     * the Pages panel, published with the site) wins over the config
     * default. Read through the current storage workspace, so the editor
     * sees the draft choice and public requests the live one.
     */
    public static function homeSlug(): string
    {
        $fromSite = app(\Designer\Studio\Services\Storage\SiteRepository::class)->get()['home_slug'] ?? null;

        if (is_string($fromSite) && $fromSite !== '') {
            return $fromSite;
        }

        return (string) config('studio.page_routing.home_slug', 'home');
    }

    public static function pageUrl(string $slug): string
    {
        $homeSlug = self::homeSlug();

        if ($slug === $homeSlug && Route::has('studio.page.home')) {
            return url('/');
        }

        return url('/' . $slug);
    }

    /** Whether Studio registered the '/' route for the home page. */
    public static function ownsRoot(): bool
    {
        return Route::has('studio.page.home');
    }
}
