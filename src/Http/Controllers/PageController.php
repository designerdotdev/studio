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

        foreach ($page->components as $instance) {
            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            // Merge instance variables with field defaults
            $vars = [];
            foreach ($component->fields as $key => $config) {
                $vars[$key] = $instance['variables'][$key] ?? $config['default'] ?? '';
            }

            $renderedSections[] = [
                'html' => Blade::render($component->html, $vars),
                'order' => $instance['order'],
            ];
        }

        // Sort by order
        usort($renderedSections, fn($a, $b) => $a['order'] <=> $b['order']);

        // Extract just the rendered HTML strings
        $renderedSections = array_map(fn($s) => $s['html'], $renderedSections);

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
