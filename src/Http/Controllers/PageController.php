<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Blade;

class PageController extends Controller
{
    protected static bool $componentsSynced = false;

    public function __construct(
        protected PageRepository $pages,
        protected ComponentRepository $components,
        protected LayoutRepository $layouts,
        protected DesignSyncService $designSync,
    ) {}

    public function show(string $slug)
    {
        // The home page lives at '/' — keep '/{home_slug}' canonical
        if ($slug === config('studio.page_routing.home_slug', 'home') && request()->path() !== '/') {
            return redirect('/', 301);
        }

        $this->ensureComponentsSynced();

        $page = $this->pages->find($slug);

        if (!$page) {
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

            $vars = $component->resolveVariables($instance['variables'] ?? []);

            try {
                $rendered[] = Blade::render($component->html, $vars);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $rendered;
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
