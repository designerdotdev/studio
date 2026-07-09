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
    public static function pageUrl(string $slug): string
    {
        $homeSlug = config('studio.page_routing.home_slug', 'home');

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
