<?php

namespace Designer\Studio\Support;

use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Removes the stock Laravel welcome route from the host app so Studio's
 * home route can serve the published homepage at '/'.
 *
 * Studio never shadows app routes, so a fresh Laravel app — whose
 * routes/web.php always defines '/' — would otherwise serve the welcome
 * page forever while publishes silently succeed. The pruner edits
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

    public function __construct(
        protected StudioStorage $storage
    ) {}

    /**
     * Remove the stock welcome route if (and only if) it is safe to.
     * Idempotent and cheap once claimed. Returns true when a route was
     * actually pruned on this call.
     */
    public function claimHome(): bool
    {
        if (!config('studio.page_routing.enabled', true)) {
            return false;
        }

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

    /**
     * Does the app (anything but Studio) define a GET '/' route? Studio's
     * own home route must be ignored — after Studio claims the root, a
     * '/' route always exists, and it's ours.
     */
    public function appDefinesRootRoute(): bool
    {
        foreach (Route::getRoutes()->get('GET') as $route) {
            if ($route->uri() === '/' && $route->getName() !== 'studio.page.home') {
                return true;
            }
        }

        return false;
    }

    /**
     * Live-tree check on raw paths (workspace-immune), mirroring how
     * PublishService reads the live site.
     */
    public function liveHomePageExists(): bool
    {
        $homeSlug = \Designer\Studio\Support\SiteUrls::homeSlug();

        return is_file($this->storage->getBasePath() . '/pages/' . $homeSlug . '.json');
    }

    protected function routesFile(): string
    {
        return base_path('routes/web.php');
    }
}
