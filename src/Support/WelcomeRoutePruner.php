<?php

namespace Designer\Studio\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * Removes the stock Laravel welcome route from the host app so the site's
 * runtime can serve the home page at '/'.
 *
 * The runtime never shadows app routes (it answers from Route::fallback),
 * so a fresh Laravel app — whose routes/web.php always defines '/' — would
 * otherwise serve the welcome page forever. The pruner edits
 * routes/web.php ONLY when the root route is byte-for-byte stock
 * boilerplate (whitespace aside); anything customized is never touched.
 */
class WelcomeRoutePruner
{
    /**
     * The stock welcome route: Route::get('/', function () { return
     * view('welcome'); });  — tolerant of whitespace/quote style, strict
     * about structure. Nothing else ever matches.
     */
    protected const STOCK_PATTERN = '/Route::get\(\s*[\'"]\/[\'"]\s*,\s*function\s*\(\s*\)\s*\{\s*return\s+view\(\s*[\'"]welcome[\'"]\s*\)\s*;\s*\}\s*\)\s*;[^\S\n]*\n?/';

    /**
     * Remove the stock welcome route if (and only if) it is safe to.
     * Idempotent and cheap once claimed. Returns true when a route was
     * actually pruned on this call.
     */
    public function claimHome(): bool
    {
        // Without a live home page, '/' would 404 — leaving the welcome
        // page in place is the better failure mode.
        if (!$this->liveHomePageExists()) {
            return false;
        }

        $path = $this->routesFile();

        if (!is_file($path) || !is_writable($path)) {
            return false;
        }

        $contents = file_get_contents($path);
        $pruned = preg_replace(self::STOCK_PATTERN, '', $contents, 1, $count);

        if ($count === 0 || $pruned === null) {
            return false;
        }

        // Tidy the gap the route leaves behind
        $pruned = preg_replace("/\n{3,}/", "\n\n", $pruned);

        file_put_contents($path, $pruned);

        // A cached route table still contains the welcome route — rebuild
        // so the claim takes effect on the next request.
        if (app()->routesAreCached()) {
            Artisan::call('route:clear');
        }

        return true;
    }

    public function stockWelcomeDetected(): bool
    {
        $path = $this->routesFile();

        return is_file($path)
            && preg_match(self::STOCK_PATTERN, (string) file_get_contents($path)) === 1;
    }

    /** Does the app define a GET '/' route of its own? */
    public function appDefinesRootRoute(): bool
    {
        return SiteUrls::appDefinesRootRoute();
    }

    /** The live home page is the installed site's index.blade.php. */
    public function liveHomePageExists(): bool
    {
        return is_file(SitePaths::pages('index.blade.php'));
    }

    protected function routesFile(): string
    {
        return base_path('routes/web.php');
    }
}
