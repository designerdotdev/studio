<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Blade;

class PageController extends Controller
{
    protected static bool $componentsSynced = false;

    public function __construct(
        protected PageRepository $pages,
        protected ComponentRepository $components,
        protected DesignSyncService $designSync,
    ) {}

    public function show(string $slug)
    {
        $this->ensureComponentsSynced();

        $page = $this->pages->find($slug);

        if (!$page) {
            abort(404);
        }

        $renderedSections = [];

        foreach (collect($page->components)->sortBy('order')->values() as $instance) {
            if (!empty($instance['hidden'])) {
                continue;
            }

            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $vars = $component->resolveVariables($instance['variables'] ?? []);

            try {
                $renderedSections[] = Blade::render($component->html, $vars);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('studio::page', [
            'renderedSections' => $renderedSections,
            'page' => $page,
        ]);
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
