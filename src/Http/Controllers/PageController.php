<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\SectionRenderer;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Routing\Controller;

class PageController extends Controller
{
    protected static bool $componentsSynced = false;

    public function __construct(
        protected PageRepository $pages,
        protected ComponentRepository $components,
        protected LayoutRepository $layouts,
        protected DesignSyncService $designSync,
        protected SectionRenderer $renderer,
    ) {}

    public function show(string $slug)
    {
        // The home page lives at '/' — keep '/{home_slug}' canonical. Only
        // when Studio owns the root route, though: if the app defines its
        // own '/', redirecting would make the homepage unreachable, so it
        // serves at its slug instead.
        if (
            $slug === \Designer\Studio\Support\SiteUrls::homeSlug()
            && request()->path() !== '/'
            && \Designer\Studio\Support\SiteUrls::ownsRoot()
        ) {
            return redirect('/', 301);
        }

        $this->ensureComponentsSynced();

        $page = $this->pages->find($slug);

        if (!$page) {
            // Renamed page? 301 old slugs to their new home
            if ($target = $this->redirectForPreviousSlug($slug)) {
                return redirect($target, 301);
            }

            abort(404);
        }

        $regions = $this->layouts->regions($page->layout_ref);
        $pageComponents = collect($page->components)->sortBy('order')->values()->all();

        $renderedSections = $this->renderInstances(
            app(\Designer\Studio\Services\Storage\BlockRepository::class)->hydrate([
                ...$regions['before'],
                ...$pageComponents,
                ...$regions['after'],
            ])
        );

        return view('studio::page', [
            'renderedSections' => $renderedSections,
            'page' => $page,
        ]);
    }

    protected function renderInstances(array $instances): array
    {
        $rendered = [];

        foreach ($instances as $instance) {
            if (!empty($instance['hidden'])) {
                continue;
            }

            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $rendered[] = $this->renderer->render(
                $component,
                $component->resolveVariables($instance['variables'] ?? []),
                $instance['bindings'] ?? []
            );
        }

        return $rendered;
    }

    /**
     * URL a retired slug should 301 to, if any page remembers it.
     */
    protected function redirectForPreviousSlug(string $slug): ?string
    {
        $storage = app(\Designer\Studio\Services\Storage\StudioStorage::class);

        foreach ($storage->list('pages') as $pageSlug) {
            $doc = $storage->read("pages/{$pageSlug}.json");

            if (in_array($slug, $doc['previous_slugs'] ?? [], true)) {
                return \Designer\Studio\Support\SiteUrls::pageUrl($pageSlug);
            }
        }

        return null;
    }

    /**
     * sitemap.xml for all published, indexable pages.
     */
    public function sitemap()
    {
        $this->ensureComponentsSynced();

        $homeSlug = \Designer\Studio\Support\SiteUrls::homeSlug();

        $urls = $this->pages->all()
            ->reject(fn ($page) => !empty($page->meta['noindex']))
            ->sortBy(fn ($page) => $page->slug === $homeSlug ? 0 : 1)
            ->map(function ($page) {
                $loc = \Designer\Studio\Support\SiteUrls::pageUrl($page->slug);
                $lastmod = substr($page->updated_at, 0, 10);

                return "    <url>\n        <loc>" . e($loc) . "</loc>\n        <lastmod>{$lastmod}</lastmod>\n    </url>";
            });

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $urls->implode("\n") . "\n"
            . '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    protected function ensureComponentsSynced(): void
    {
        if (static::$componentsSynced) {
            return;
        }

        static::$componentsSynced = true;

        // If component library is empty, sync from design files
        if ($this->components->all()->isEmpty()) {
            $this->designSync->syncAll();
        }
    }
}
