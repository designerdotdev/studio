<?php

namespace Designer\Studio\Http\Middleware;

use Closure;
use Designer\Studio\Services\Site\SiteManifest;
use Designer\Studio\Support\SitePaths;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a small "designer studio · Edit" badge to pages the site's runtime
 * serves, linking to that page in the editor. The runtime answers from
 * Route::fallback, so a successful HTML response from the fallback route
 * (with a site installed) is a Studio page. Shown only to people who could
 * open the editor — see `studio.badge`.
 */
class InjectEditBadge
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->applies($request, $response)) {
            return $response;
        }

        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');

        if ($position === false) {
            return $response;
        }

        $slug = $this->slugFor(trim($request->path(), '/'));
        $badge = view('studio::partials.edit-badge', [
            'url' => route('studio.index', $slug !== null ? ['page' => $slug] : []),
        ])->render();

        $response->setContent(substr_replace($content, $badge, $position, 0));

        return $response;
    }

    public static function enabled(): bool
    {
        $configured = config('studio.badge');
        $enabled = $configured !== null ? (bool) $configured : app()->environment('local');

        if (! $enabled) {
            return false;
        }

        $gate = config('studio.gate');

        return ! $gate || Gate::allows($gate);
    }

    protected function applies(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $request->route()?->isFallback
            && $response->getStatusCode() === 200
            && ! $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            && ! $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && SitePaths::installed()
            && static::enabled();
    }

    /** The editor's page slug for a URL path; null for nested and collection pages. */
    protected function slugFor(string $path): ?string
    {
        if ($path === '') {
            return SiteManifest::read()['home'] ?? 'home';
        }

        return ! str_contains($path, '/') && is_file(SitePaths::pages($path . '.blade.php')) ? $path : null;
    }
}
