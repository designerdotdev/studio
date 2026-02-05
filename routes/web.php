<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

Route::group([
    'prefix' => config('studio.path', 'designer/studio'),
    'middleware' => config('studio.middleware', ['web']),
    'as' => 'studio.',
], function () {
    Route::get('/{project}', function ($projectId) {
        $projectModel = config('studio.models.project', 'App\\Models\\Project');

        $project = $projectModel::with(['pages.components' => function ($query) {
            $query->orderBy('order');
        }])->findOrFail($projectId);

        $page = $project->pages->first();

        // Build components data with parsed YAML fields
        $components = [];
        if ($page) {
            foreach ($page->components as $component) {
                $componentData = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'html' => $component->html,
                    'fields' => [],
                ];

                if ($component->yml) {
                    $yaml = Yaml::parse($component->yml);
                    $componentData['title'] = $yaml['name'] ?? $component->name;
                    $componentData['description'] = $yaml['description'] ?? '';
                    $componentData['fields'] = $yaml['fields'] ?? [];
                }

                $components[$component->id] = $componentData;
            }
        }

        return view('studio::home', [
            'project' => $project,
            'page' => $page,
            'components' => $components,
        ]);
    })->name('home');

    Route::get('/{project}/iframe', function ($projectId) {
        $projectModel = config('studio.models.project', 'App\\Models\\Project');

        $project = $projectModel::with(['pages.components' => function ($query) {
            $query->orderBy('order');
        }])->findOrFail($projectId);

        $page = $project->pages->first();

        // Build components with default variable values from YAML
        $components = [];
        $variables = [];

        if ($page) {
            foreach ($page->components as $component) {
                $componentData = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'html' => $component->html,
                ];

                if ($component->yml) {
                    $yaml = Yaml::parse($component->yml);
                    if (!empty($yaml['fields'])) {
                        foreach ($yaml['fields'] as $key => $config) {
                            $variables[$key] = $config['default'] ?? '';
                        }
                    }
                }

                $components[] = $componentData;
            }
        }

        return view('studio::iframe', [
            'components' => $components,
            'variables' => $variables,
        ]);
    })->name('iframe');
});
