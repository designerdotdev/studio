<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Support\DevMode;
use Designer\Studio\Support\SitePaths;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Dev mode — read and write one section's source from the editor: its
 * component (`resources/designer/views/components/<path>.blade.php`) and
 * field contract (the `.yml` beside it). Only available when the dev-mode
 * gate passes (local environment by default).
 */
class DevModeController extends Controller
{
    public function __construct(
        protected DesignSyncService $designSync
    ) {}

    public function show(string $name)
    {
        abort_unless(DevMode::enabled(), 404);

        $files = $this->designSync->sourceFiles($name);

        if (!$files) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        // The modal's two tabs keep their original keys: `html` is the Blade
        return response()->json([
            'success' => true,
            'name' => $name,
            'html' => (string) file_get_contents($files['blade']),
            'yaml' => (string) file_get_contents($files['yaml']),
            'paths' => [
                'html' => SitePaths::relative($files['blade']),
                'yaml' => SitePaths::relative($files['yaml']),
            ],
        ]);
    }

    public function update(Request $request, string $name)
    {
        abort_unless(DevMode::enabled(), 404);

        $validated = $request->validate([
            'html' => 'required|string',
            'yaml' => 'required|string',
        ]);

        // TrimStrings would eat each file's trailing newline — take the
        // contents off the raw body (validation above still guards shape)
        $raw = str_contains((string) $request->header('Content-Type'), 'json') ? json_decode($request->getContent(), true) : null;

        foreach (['html', 'yaml'] as $key) {
            if (is_array($raw) && is_string($raw[$key] ?? null)) {
                $validated[$key] = $raw[$key];
            }
        }

        try {
            Yaml::parse($validated['yaml']);
        } catch (ParseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid YAML: ' . $e->getMessage(),
            ], 422);
        }

        $files = $this->designSync->sourceFiles($name);

        if (!$files) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        File::put($files['yaml'], $validated['yaml']);
        File::put($files['blade'], $validated['html']);

        // Pull the edit into the library and the documents (a changed field
        // contract changes how the site's pages read)
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }

        app(SiteMirror::class)->sync();

        return response()->json([
            'success' => true,
            'paths' => [
                'html' => SitePaths::relative($files['blade']),
                'yaml' => SitePaths::relative($files['yaml']),
            ],
        ]);
    }
}
