<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\Site\RuntimeInstaller;
use Designer\Studio\Services\Site\SiteInstaller;
use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\SiteUrls;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StudioController extends Controller
{
    public function __construct(
        protected PageRepository $pages,
        protected ComponentRepository $components,
        protected SiteMirror $mirror,
    ) {
        // The editor (and everything it calls) works on the draft site
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    public function index(Request $request)
    {
        // No site yet: pick a template to install
        if (!SitePaths::installed()) {
            $catalog = app(\Designer\Studio\Services\Templates\TemplateCatalog::class);

            return view('studio::onboarding', [
                'templates' => $catalog->all(),
                'categories' => $catalog->categories(),
            ]);
        }

        // The site is served by the runtime provider in the app. If it has
        // gone missing (or was never registered), put it back.
        $runtime = app(RuntimeInstaller::class);

        if (!$runtime->installed()) {
            $runtime->install();
        }

        // Pull in anything that changed in the site's files since the last
        // visit (Code mode, the Assistant, the developer's own editor, a
        // fresh deploy with empty storage) and re-sync the section library.
        $this->mirror->sync();

        // Self-heal: sites published while the stock welcome route still
        // owned '/' get claimed here.
        $homeClaimed = $this->pruner()->claimHome();

        $homeSlug = SiteUrls::homeSlug();

        // A site whose pages are all hand-written still needs one page to edit
        if ($this->pages->all()->isEmpty()) {
            $this->pages->create(['title' => 'Home', 'slug' => $homeSlug]);
        }

        // Home first, then the Pages-panel order (PageRepository::all sorts by it)
        $pages = $this->pages->all()
            ->sortBy(fn($p, $i) => [$p->slug === $homeSlug ? 0 : 1, $i])
            ->values();

        // Load the requested page, preferring the home page as the default
        $defaultSlug = $pages->firstWhere('slug', $homeSlug)?->slug ?? $pages->first()?->slug;

        $slug = $request->query('page', $defaultSlug);
        $page = $slug ? $this->pages->find($slug) : null;

        if (!$page && $pages->isNotEmpty()) {
            $page = $this->pages->find($defaultSlug);
        }

        if (!$page) {
            abort(404);
        }

        $blocks = app(\Designer\Studio\Services\Storage\BlockRepository::class);
        $draftMode = (bool) config('studio.draft_mode', true);

        return view('studio::home', [
            'page' => $page,
            'pages' => $pages,
            'library' => $this->components->grouped(),
            'blocks' => $blocks->all()->map(fn ($block) => [
                'slug' => $block['slug'],
                'name' => $block['name'],
                'usage' => $blocks->usage($block['slug'])['count'],
            ])->values()->all(),
            'draftMode' => $draftMode,
            'publishStatus' => $draftMode ? $this->publisher()->status() : null,
            'notices' => $this->editorNotices($homeClaimed),
        ]);
    }

    /**
     * Operational warnings surfaced inside the editor: an unprotected
     * Studio in production, storage that can't be written to, or an app
     * '/' route that keeps the homepage off the root URL.
     */
    protected function editorNotices(bool $homeClaimed = false): array
    {
        $notices = [];

        $storagePath = config('studio.storage_path', storage_path('studio'));

        if (is_dir($storagePath) && !is_writable($storagePath)) {
            $notices[] = [
                'id' => 'storage-unwritable',
                'tone' => 'danger',
                'dismissible' => false,
                'text' => 'Studio can\'t write to ' . basename($storagePath) . ' — check directory permissions. Changes will not save.',
            ];
        }

        $unprotected = app()->environment('production')
            && config('studio.middleware', ['web']) === ['web']
            && !config('studio.gate');

        if ($unprotected) {
            $notices[] = [
                'id' => 'unprotected',
                'tone' => 'warn',
                'dismissible' => true,
                'text' => 'The Studio is open to anyone who can reach this URL. Add auth middleware or a gate in config/studio.php before sharing this site.',
            ];
        }

        // The app owns '/' with something Studio won't touch (a customized
        // route, or an unwritable routes file) — the homepage can't serve
        // at the root URL. Skipped when claimHome() just pruned the stock
        // route: the route collection still lists '/' for THIS request,
        // but the next one is clean.
        $pruner = $this->pruner();
        $homeSlug = SiteUrls::homeSlug();

        $rootBlocked = !$homeClaimed
            && $pruner->liveHomePageExists()
            && $pruner->appDefinesRootRoute();

        if ($rootBlocked) {
            $notices[] = [
                'id' => 'app-owns-root',
                'tone' => 'warn',
                'dismissible' => true,
                'text' => "Your app defines its own / route, so your homepage is served at /{$homeSlug} instead. Remove that route from routes/web.php to serve it at /.",
            ];
        }

        return $notices;
    }

    protected function pruner(): \Designer\Studio\Support\WelcomeRoutePruner
    {
        return app(\Designer\Studio\Support\WelcomeRoutePruner::class);
    }

    /**
     * The live-editing preview document loaded inside the canvas iframe.
     *
     * When the page uses a layout, the layout's header/footer sections are
     * rendered around the page's own sections. Every section carries its
     * scope ('page' or 'layout') plus insert/ordering metadata relative to
     * the document it belongs to, so the overlay chrome edits the right one.
     */
    public function iframe(string $slug)
    {
        $page = $this->pages->find($slug);

        if (!$page) {
            abort(404);
        }

        $layouts = app(\Designer\Studio\Services\Storage\LayoutRepository::class);
        $blocks = app(\Designer\Studio\Services\Storage\BlockRepository::class);
        $regions = $layouts->regions($page->layout_ref);
        $layout = $page->layout_ref ? $layouts->find($page->layout_ref) : null;

        $sections = [];
        $componentVariables = [];
        $componentBindings = [];
        $blockByInstance = [];
        $binder = app(\Designer\Studio\Services\CollectionBinder::class);
        $pageComponents = collect($page->components)->sortBy('order')->values()->all();
        // Layout doc length includes the content slot entry
        $layoutCount = $layout ? count($layout['components'] ?? []) : 0;

        $push = function (array $instances, string $scope, int $indexOffset, int $docCount) use (&$sections, &$componentVariables, &$componentBindings, &$blockByInstance, $blocks, $binder) {
            // Hydrate WITHOUT dropping missing blocks so doc indexes stay
            // aligned with the raw components array (insertion positions).
            foreach (array_values($instances) as $i => $instance) {
                $docIndex = $i + $indexOffset;

                if (!empty($instance['block_ref'])) {
                    $hydrated = $blocks->hydrate([$instance]);

                    if (empty($hydrated)) {
                        continue;
                    }

                    $instance = $hydrated[0];
                    $blockByInstance[$instance['id']] = $instance['block_ref'];
                }

                $component = $this->components->find($instance['component_ref']);

                if (!$component) {
                    continue;
                }

                $sections[] = [
                    'id' => $instance['id'],
                    'ref' => $component->name,
                    'title' => $component->title,
                    'html' => $component->html,
                    // The field contract travels with the section so the
                    // canvas can be rendered with inline-editing sentinels.
                    'fields' => $component->fields,
                    'fixed' => $component->fixed,
                    'hidden' => (bool) ($instance['hidden'] ?? false),
                    'scope' => $scope,
                    'block' => $instance['block_ref'] ?? null,
                    // Position inside its own document (layout indexes count
                    // the content slot, so footer sections continue past it)
                    'docIndex' => $docIndex,
                    'docFirst' => $docIndex === 0,
                    'docLast' => $docIndex === $docCount - 1,
                ];

                $componentBindings[$instance['id']] = $instance['bindings'] ?? [];
                $componentVariables[$instance['id']] = $binder->apply(
                    $component->resolveVariables($instance['variables'] ?? []),
                    $instance['bindings'] ?? []
                );
            }
        };

        // Layout header → page content → layout footer. A header section is
        // never docLast (the slot follows it) and a footer section is never
        // docFirst — so move-down/up across the slot moves between regions.
        $push($regions['before'], 'layout', 0, $layoutCount);
        $push($pageComponents, 'page', 0, count($pageComponents));
        $push($regions['after'], 'layout', count($regions['before']) + 1, $layoutCount);

        return view('studio::iframe', [
            'page' => $page,
            'sections' => $sections,
            'componentVariables' => $componentVariables,
            'componentBindings' => $componentBindings,
            'blockByInstance' => $blockByInstance,
            'layout' => $layout,
            'layoutBeforeCount' => count($regions['before']),
            'layoutAfterCount' => count($regions['after']),
            'layoutComponentCount' => $layoutCount,
            'pageSectionCount' => count($pageComponents),
        ]);
    }

    /**
     * Standalone rendered preview of a single library component
     * (used for the thumbnails inside the section picker).
     */
    public function componentPreview(string $name)
    {
        $component = $this->components->find($name);

        if (!$component) {
            abort(404);
        }

        // Show the section with the data it would start bound to (a logo
        // strip with the site's logos, not an empty one)
        $variables = app(\Designer\Studio\Services\CollectionBinder::class)->apply(
            $component->resolveVariables([], usePreviewDefaults: true),
            PageRepository::defaultBindings($component->name)
        );

        $html = $this->renderSection($component->html, $variables, $component->name);

        return response()
            ->view('studio::preview', ['title' => $component->title, 'sections' => [$html]])
            ->header('Cache-Control', 'private, max-age=30');
    }

    /**
     * Standalone rendered preview of a global block (picker thumbnails).
     */
    public function blockPreview(string $slug)
    {
        $block = app(\Designer\Studio\Services\Storage\BlockRepository::class)->find($slug);
        $component = $block ? $this->components->find($block['component_ref']) : null;

        if (!$component) {
            abort(404);
        }

        $html = $this->renderSection(
            $component->html,
            app(\Designer\Studio\Services\CollectionBinder::class)->apply(
                $component->resolveVariables($block['variables'] ?? []),
                $block['bindings'] ?? []
            ),
            $component->name
        );

        return response()
            ->view('studio::preview', ['title' => $block['name'], 'sections' => [$html]])
            ->header('Cache-Control', 'private, max-age=10');
    }

    /**
     * Install the template picked in onboarding: its files are copied into
     * resources/designer and public/designer, and the editor opens on it.
     */
    public function applyTemplate(Request $request)
    {
        $validated = $request->validate([
            'template' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $name = $validated['template'];

        if (!app(\Designer\Studio\Services\Templates\TemplateCatalog::class)->has($name)) {
            return response()->json([
                'success' => false,
                'message' => "Template [{$name}] is not available.",
            ], 422);
        }

        try {
            $report = app(SiteInstaller::class)->install($name);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // Land on the home page when the template has one
        $homeSlug = SiteUrls::homeSlug();
        $slug = in_array($homeSlug, $report['pages'], true) ? $homeSlug : ($report['pages'][0] ?? null);

        return response()->json([
            'success' => true,
            'redirect' => $slug
                ? route('studio.index', ['page' => $slug])
                : route('studio.index'),
        ]);
    }

    /**
     * The picture a template ships of itself: from the local copy once it
     * has been downloaded, from its repository before that.
     */
    public function templateThumbnail(string $name)
    {
        $catalog = app(\Designer\Studio\Services\Templates\TemplateCatalog::class);
        $path = $catalog->thumbnailPath($name);

        if ($path) {
            return response()->file($path, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        $remote = $catalog->remoteThumbnail($name);

        abort_unless($remote !== null, 404);

        return redirect()->away($remote);
    }

    /* ------------------------------------------------------------ */
    /*  Draft mode — preview + publish                               */
    /* ------------------------------------------------------------ */

    protected function publisher(): \Designer\Studio\Services\PublishService
    {
        return app(\Designer\Studio\Services\PublishService::class);
    }

    /**
     * Render a draft page exactly like the live site would — the whole
     * draft site is browsable under /studio/preview.
     */
    public function previewPage(?string $slug = null)
    {
        $slug = $slug ?: SiteUrls::homeSlug();

        $page = $this->pages->find($slug);

        if (!$page) {
            abort(404);
        }

        $regions = app(\Designer\Studio\Services\Storage\LayoutRepository::class)->regions($page->layout_ref);
        $pageComponents = collect($page->components)->sortBy('order')->values()->all();

        $instances = app(\Designer\Studio\Services\Storage\BlockRepository::class)->hydrate([
            ...$regions['before'],
            ...$pageComponents,
            ...$regions['after'],
        ]);

        // Same renderer as the live page (PageController): DataBag-wrapped
        // rows, $site injected, failures shown in place instead of dropped.
        $renderer = app(\Designer\Studio\Services\SectionRenderer::class);
        $renderedSections = [];

        foreach ($instances as $instance) {
            if (!empty($instance['hidden'])) {
                continue;
            }

            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $renderedSections[] = $renderer->render(
                $component,
                $component->resolveVariables($instance['variables'] ?? []),
                $instance['bindings'] ?? []
            );
        }

        return view('studio::page', [
            'renderedSections' => $renderedSections,
            'page' => $page,
            'noindex' => true,
            'preview' => true,
        ]);
    }

    public function publishStatus()
    {
        // Files edited outside the editor since it loaded count too
        $this->mirror->refresh();

        return response()->json($this->publisher()->status());
    }

    public function publishSite()
    {
        try {
            $published = $this->publisher()->publishAll();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Publishing failed — check that storage/studio and resources/designer are writable.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'published' => $published['items'],
            'count' => count($published['items']),
            // Anything the site's files could not take (a hand-written page
            // sitting at the same URL, say)
            'notes' => $published['notes'] ?? [],
            // Publishing is the moment the site goes live — take over '/'
            // from the stock Laravel welcome route if it's still there.
            'home_claimed' => $this->pruner()->claimHome(),
        ]);
    }

    public function discardDraft()
    {
        try {
            $discarded = $this->publisher()->discardAll();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Discard failed — check that storage/studio is writable.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'discarded' => count($discarded['items']),
        ]);
    }

    public function createPage(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
        ]);

        $page = $this->pages->create($validated);

        return response()->json([
            'success' => true,
            'page' => $page->toArray(),
            'editor_url' => route('studio.index', ['page' => $page->slug]),
        ]);
    }

    public function updatePage(Request $request, string $slug)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'meta' => 'sometimes|array',
            'meta.seo_title' => 'sometimes|nullable|string|max:255',
            'meta.seo_description' => 'sometimes|nullable|string|max:500',
        ]);

        $page = $this->pages->update($slug, $validated);

        if (!$page) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $page->toArray(),
            'editor_url' => route('studio.index', ['page' => $page->slug]),
        ]);
    }

    public function duplicatePage(string $slug)
    {
        $page = $this->pages->duplicate($slug);

        if (!$page) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $page->toArray(),
            'editor_url' => route('studio.index', ['page' => $page->slug]),
        ]);
    }

    public function deletePage(string $slug)
    {
        $this->pages->delete($slug);

        return response()->json([
            'success' => true,
            'redirect' => route('studio.index'),
        ]);
    }

    /**
     * Image uploads for image fields. Files are stored with the rest of the
     * site's public files, in public/designer/uploads, so they work without
     * a storage symlink and stay with the site if Studio is removed.
     */
    public function upload(Request $request)
    {
        if ($message = $this->phpUploadLimitError($request)) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $request->validate([
            'file' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
        ], [
            'file.max' => 'This image is too large — the maximum upload size is 5 MB.',
            'file.image' => 'That file is not an image.',
            'file.mimes' => 'Only JPEG, PNG, GIF, WebP, and AVIF images can be uploaded.',
            'file.required' => 'No image was received by the server.',
        ]);

        $file = $request->file('file');

        $directory = SitePaths::public(SitePaths::UPLOADS);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
        $file->move($directory, $filename);

        return response()->json([
            'success' => true,
            // Root-relative, so the page file works on any host
            'url' => SitePaths::url(SitePaths::UPLOADS . '/' . $filename),
        ]);
    }

    /**
     * PHP silently discards uploads that exceed upload_max_filesize or
     * post_max_size, so Laravel validation only sees a missing/broken file
     * and produces a misleading error. Detect those cases and name the
     * php.ini setting that needs raising.
     */
    private function phpUploadLimitError(Request $request): ?string
    {
        $file = $request->file('file');

        if ($file && $file->getError() === UPLOAD_ERR_INI_SIZE) {
            return sprintf(
                "This image exceeds the server's PHP upload limit of %s. Raise `upload_max_filesize` in php.ini to allow larger uploads.",
                ini_get('upload_max_filesize')
            );
        }

        // post_max_size overflow: PHP drops the entire request body.
        $postMax = $this->iniBytes(ini_get('post_max_size'));
        if (!$file && $postMax > 0 && (int) $request->server('CONTENT_LENGTH') > $postMax) {
            return sprintf(
                "This image exceeds the server's PHP post limit of %s. Raise `post_max_size` (and `upload_max_filesize`) in php.ini to allow larger uploads.",
                ini_get('post_max_size')
            );
        }

        return null;
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        $bytes = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $bytes * 1024 * 1024 * 1024,
            'M' => $bytes * 1024 * 1024,
            'K' => $bytes * 1024,
            default => $bytes,
        };
    }

    public function addComponentToPage(Request $request, string $slug)
    {
        $validated = $request->validate([
            'component_ref' => 'required|string',
            'insert_at' => 'nullable|integer|min:0',
        ]);

        $component = $this->components->find($validated['component_ref']);

        if (!$component) {
            return response()->json(['success' => false, 'error' => 'Component not found'], 404);
        }

        // Snapshot curated preview values as the instance's starting content
        $defaultVariables = $component->resolveVariables([], usePreviewDefaults: true);

        $insertAt = $validated['insert_at'] ?? null;
        $page = $this->pages->addComponent($slug, $validated['component_ref'], $defaultVariables, $insertAt);

        return response()->json([
            'success' => true,
            'page' => $page?->toArray(),
        ]);
    }

    public function removeComponentFromPage(string $slug, string $componentId)
    {
        $page = $this->pages->removeComponent($slug, $componentId);

        return response()->json([
            'success' => true,
            'page' => $page?->toArray(),
        ]);
    }

    public function moveComponentOnPage(Request $request, string $slug, string $componentId)
    {
        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $page = $this->pages->find($slug);
        if (!$page) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        }

        $components = collect($page->components)->sortBy('order')->values();
        $currentIndex = $components->search(fn($c) => $c['id'] === $componentId);

        if ($currentIndex === false) {
            return response()->json(['success' => false, 'error' => 'Component not found'], 404);
        }

        $newIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if ($newIndex < 0 || $newIndex >= $components->count()) {
            return response()->json(['success' => false, 'error' => 'Cannot move further'], 400);
        }

        $ids = $components->pluck('id')->toArray();
        [$ids[$currentIndex], $ids[$newIndex]] = [$ids[$newIndex], $ids[$currentIndex]];

        $page = $this->pages->reorderComponents($slug, $ids);

        return response()->json([
            'success' => true,
            'page' => $page?->toArray(),
        ]);
    }

    public function updatePageComponents(Request $request, string $slug)
    {
        $validated = $request->validate([
            'components' => 'required|array',
        ]);

        $page = $this->pages->update($slug, ['components' => $validated['components']]);

        return response()->json([
            'success' => true,
            'page' => $page?->toArray(),
        ]);
    }

    /**
     * Render a section's Blade template, never letting a broken
     * template take down the whole preview document.
     */
    protected function renderSection(string $html, array $variables, string $ref): string
    {
        return app(\Designer\Studio\Services\SectionRenderer::class)
            ->renderHtml($html, $variables, $ref);
    }
}
