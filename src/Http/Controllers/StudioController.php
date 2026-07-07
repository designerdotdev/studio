<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\BladeGenerator;
use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\SampleDataSeeder;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\TemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StudioController extends Controller
{
    public function __construct(
        protected PageRepository $pages,
        protected ComponentRepository $components,
        protected BladeGenerator $generator,
        protected DesignSyncService $designSync,
        protected SampleDataSeeder $seeder,
        protected TemplateRegistry $templates
    ) {}

    public function index(Request $request)
    {
        // Always sync designs from resource files so edits are reflected
        $this->designSync->syncAll();

        $homeSlugForSort = config('studio.page_routing.home_slug', 'home');
        $pages = $this->pages->all()
            ->sortBy(fn($p) => [$p->slug === $homeSlugForSort ? 0 : 1, $p->title])
            ->values();

        // Show onboarding when no pages exist
        if ($pages->isEmpty()) {
            return view('studio::onboarding', [
                'templates' => $this->templates->all(),
            ]);
        }

        // Load the requested page, preferring the home page as the default
        $homeSlug = config('studio.page_routing.home_slug', 'home');
        $defaultSlug = $pages->firstWhere('slug', $homeSlug)?->slug ?? $pages->first()?->slug;

        $slug = $request->query('page', $defaultSlug);
        $page = $slug ? $this->pages->find($slug) : null;

        if (!$page && $pages->isNotEmpty()) {
            $page = $this->pages->find($defaultSlug);
        }

        if (!$page) {
            abort(404);
        }

        return view('studio::home', [
            'page' => $page,
            'pages' => $pages,
            'library' => $this->components->grouped(),
        ]);
    }

    /**
     * The live-editing preview document loaded inside the canvas iframe.
     */
    public function iframe(string $slug)
    {
        $page = $this->pages->find($slug);

        if (!$page) {
            abort(404);
        }

        $sections = [];
        $componentVariables = [];

        foreach (collect($page->components)->sortBy('order')->values() as $instance) {
            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $sections[] = [
                'id' => $instance['id'],
                'ref' => $component->name,
                'title' => $component->title,
                'html' => $component->html,
                'hidden' => (bool) ($instance['hidden'] ?? false),
            ];

            $componentVariables[$instance['id']] = $component->resolveVariables($instance['variables'] ?? []);
        }

        return view('studio::iframe', [
            'page' => $page,
            'sections' => $sections,
            'componentVariables' => $componentVariables,
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

        $html = $this->renderSection(
            $component->html,
            $component->resolveVariables([], usePreviewDefaults: true),
            $component->name
        );

        return response()
            ->view('studio::preview', ['title' => $component->title, 'sections' => [$html]])
            ->header('Cache-Control', 'private, max-age=30');
    }

    /**
     * Standalone rendered preview of an onboarding template's first page.
     */
    public function templatePreview(string $name)
    {
        $template = $this->templates->find($name);

        if (!$template) {
            abort(404);
        }

        $sections = [];
        $pageDef = $template['pages'][0] ?? null;

        foreach ($pageDef['components'] ?? [] as $instance) {
            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $sections[] = $this->renderSection(
                $component->html,
                $component->resolveVariables($instance['variables'] ?? [], usePreviewDefaults: true),
                $component->name
            );
        }

        return response()
            ->view('studio::preview', ['title' => $template['title'], 'sections' => $sections])
            ->header('Cache-Control', 'private, max-age=30');
    }

    public function applyTemplate(Request $request)
    {
        $validated = $request->validate([
            'template' => 'required|string',
        ]);

        $pages = $this->seeder->seedFromTemplate($validated['template']);

        $firstPage = $pages[0] ?? null;

        return response()->json([
            'success' => true,
            'redirect' => $firstPage
                ? route('studio.index', ['page' => $firstPage->slug])
                : route('studio.index'),
        ]);
    }

    public function generate()
    {
        $generated = $this->generator->generateAll();

        return response()->json([
            'success' => true,
            'generated' => $generated,
            'count' => count($generated),
        ]);
    }

    public function generatePage(string $slug)
    {
        try {
            $path = $this->generator->generatePage($slug);

            return response()->json([
                'success' => true,
                'path' => $path,
                'relative_path' => str_replace(base_path() . '/', '', $path),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
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
     * Image uploads for image fields. Files are stored in
     * public/studio-uploads so they work without a storage symlink.
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

        $directory = public_path('studio-uploads');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
        $file->move($directory, $filename);

        return response()->json([
            'success' => true,
            'url' => url('studio-uploads/' . $filename),
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
        try {
            return Blade::render($html, $variables);
        } catch (\Throwable $e) {
            report($e);

            return '<div style="padding:48px 24px;text-align:center;font-family:ui-sans-serif,system-ui,sans-serif;color:#991b1b;background:#fef2f2;border:1px dashed #fecaca;">'
                . 'Section “' . e($ref) . '” failed to render: ' . e($e->getMessage())
                . '</div>';
        }
    }
}
