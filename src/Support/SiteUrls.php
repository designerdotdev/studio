<?php

namespace Designer\Studio\Support;

use Illuminate\Support\Facades\Route;

class SiteUrls
{
    /**
     * The slug of the home page — the page written to index.blade.php. Read
     * through the current storage workspace, so the editor sees the draft
     * choice (Pages panel → Set as home) and everything else the live one.
     */
    public static function homeSlug(): string
    {
        $fromSite = app(\Designer\Studio\Services\Storage\SiteRepository::class)->get()['home_slug'] ?? null;

        return is_string($fromSite) && $fromSite !== '' ? $fromSite : 'home';
    }

    /**
     * Public URL for a page slug. The home page lives at '/' unless the app
     * answers '/' with a route of its own — then the site's runtime serves
     * it at '/{home_slug}' instead.
     */
    public static function pageUrl(string $slug): string
    {
        if ($slug === self::homeSlug() && self::ownsRoot()) {
            return url('/');
        }

        return url('/' . $slug);
    }

    /** Whether '/' reaches the site (no app route of its own claims it). */
    public static function ownsRoot(): bool
    {
        return !self::appDefinesRootRoute();
    }

    /** Does the app define a GET '/' route (the site's fallback aside)? */
    public static function appDefinesRootRoute(): bool
    {
        foreach (Route::getRoutes()->get('GET') as $route) {
            if ($route->uri() === '/' && !$route->isFallback) {
                return true;
            }
        }

        return false;
    }
}
