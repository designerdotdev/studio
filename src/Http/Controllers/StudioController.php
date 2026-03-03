<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\SampleDataSeeder;
use Designer\Studio\Services\TemplateRegistry;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\BladeGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        $pages = $this->pages->all();

        // Show onboarding when no pages exist
        if ($pages->isEmpty()) {
            return view('studio::onboarding', [
                'templates' => $this->templates->all(),
            ]);
        }

        // Load the requested page or fall back to the first page
        $slug = $request->query('page', $pages->first()?->slug);
        $page = $slug ? $this->pages->find($slug) : null;

        // If page not found, fall back to first page
        if (!$page && $pages->isNotEmpty()) {
            $page = $pages->first();
        }

        if (!$page) {
            abort(404);
        }

        // Build component data with full definitions
        $componentsData = [];
        foreach ($page->components as $instance) {
            $component = $this->components->find($instance['component_ref']);
            if ($component) {
                $componentsData[$instance['id']] = [
                    'id' => $instance['id'],
                    'component_ref' => $instance['component_ref'],
                    'name' => $component->name,
                    'title' => $component->title,
                    'description' => $component->description,
                    'html' => $component->html,
                    'fields' => $component->fields,
                    'variables' => $instance['variables'] ?? [],
                    'order' => $instance['order'],
                ];
            }
        }

        return view('studio::home', [
            'page' => $page,
            'pages' => $pages,
            'components' => $componentsData,
            'componentLibrary' => $this->components->all(),
        ]);
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

    public function iframe(string $slug)
    {
        $page = $this->pages->find($slug);

        if (!$page) {
            abort(404);
        }

        $components = [];
        $componentVariables = [];

        foreach ($page->components as $instance) {
            $component = $this->components->find($instance['component_ref']);
            if ($component) {
                $components[] = [
                    'id' => $instance['id'],
                    'html' => $component->html,
                    'order' => $instance['order'],
                ];

                // Build per-component variables
                $vars = [];
                foreach ($component->fields as $key => $config) {
                    $vars[$key] = $instance['variables'][$key] ?? $config['default'] ?? '';
                }
                $componentVariables[$instance['id']] = $vars;
            }
        }

        // Sort by order
        usort($components, fn($a, $b) => $a['order'] <=> $b['order']);

        return view('studio::iframe', [
            'components' => $components,
            'componentVariables' => $componentVariables,
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
        ]);
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

        // Use preview_variables as default variable values
        $defaultVariables = [];
        foreach ($component->fields as $key => $config) {
            $defaultVariables[$key] = $component->preview_variables[$key] ?? $config['default'] ?? '';
        }

        $insertAt = $validated['insert_at'] ?? null;
        $page = $this->pages->addComponent($slug, $validated['component_ref'], $defaultVariables, $insertAt);

        return response()->json([
            'success' => true,
            'page' => $page?->toArray(),
        ]);
    }

    public function deletePage(string $slug)
    {
        $this->pages->delete($slug);

        return response()->json([
            'success' => true,
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
}
