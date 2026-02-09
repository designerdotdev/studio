<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\SampleDataSeeder;
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
        protected SampleDataSeeder $seeder
    ) {}

    public function index()
    {
        $pages = $this->pages->all();

        // Seed sample data if no pages exist
        if ($pages->isEmpty()) {
            $this->seeder->seed();
            $pages = $this->pages->all();
        }

        return view('studio::dashboard', [
            'pages' => $pages,
            'componentLibrary' => $this->components->all(),
        ]);
    }

    public function edit(string $slug)
    {
        $page = $this->pages->find($slug);

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
            'components' => $componentsData,
            'componentLibrary' => $this->components->all(),
        ]);
    }

    public function iframe(string $slug)
    {
        $page = $this->pages->find($slug);

        if (!$page) {
            abort(404);
        }

        $components = [];
        $variables = [];

        foreach ($page->components as $instance) {
            $component = $this->components->find($instance['component_ref']);
            if ($component) {
                $components[] = [
                    'id' => $instance['id'],
                    'html' => $component->html,
                    'order' => $instance['order'],
                ];

                // Merge instance variables with field defaults
                foreach ($component->fields as $key => $config) {
                    $variables[$key] = $instance['variables'][$key] ?? $config['default'] ?? '';
                }
            }
        }

        // Sort by order
        usort($components, fn($a, $b) => $a['order'] <=> $b['order']);

        return view('studio::iframe', [
            'components' => $components,
            'variables' => $variables,
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
