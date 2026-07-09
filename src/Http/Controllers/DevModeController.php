<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Support\DevMode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Dev mode — read and write a section's source files
 * (resources/views/designer/<category>/<name>.{html,yml}) from the editor.
 *
 * Writes always land in the APP copy (which wins over the package copy in
 * DesignSyncService), never inside the package. Only available when the
 * dev-mode gate passes (local environment by default).
 */
class DevModeController extends Controller
{
    public function __construct(
        protected DesignSyncService $designSync
    ) {}

    public function show(string $name)
    {
        abort_unless(DevMode::enabled(), 404);

        $relative = $this->designSync->findDesignPath($name);

        if (!$relative) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        $base = $this->designSync->getDesignsPath() . '/' . $relative;

        return response()->json([
            'success' => true,
            'name' => $name,
            'html' => (string) file_get_contents($base . '.html'),
            'yaml' => (string) file_get_contents($base . '.yml'),
            'paths' => [
                'html' => $this->displayPath($base . '.html'),
                'yaml' => $this->displayPath($base . '.yml'),
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

        try {
            $parsed = Yaml::parse($validated['yaml']);
        } catch (ParseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid YAML: ' . $e->getMessage(),
            ], 422);
        }

        if (!is_array($parsed) || ($parsed['name'] ?? null) !== $name) {
            return response()->json([
                'success' => false,
                'message' => "The YAML `name` key must stay \"{$name}\" — it has to match the filename.",
            ], 422);
        }

        $relative = $this->designSync->findDesignPath($name);

        if (!$relative) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        // Copy-on-write into the app's designer directory. If it doesn't
        // exist yet, publish the whole package set first — an app copy of
        // a single section would otherwise hide every other packaged one
        // (DesignSyncService reads app OR package, never both).
        $appDir = resource_path('views/designer');

        if (!File::isDirectory($appDir)) {
            $packageDir = dirname(__DIR__, 3) . '/resources/views/designer';
            File::ensureDirectoryExists($appDir);

            if (File::isDirectory($packageDir)) {
                File::copyDirectory($packageDir, $appDir);
            }
        }

        $target = $appDir . '/' . $relative;
        File::ensureDirectoryExists(dirname($target));
        File::put($target . '.yml', $validated['yaml']);
        File::put($target . '.html', $validated['html']);

        // Pull the edit into the component library (skips unchanged files)
        $this->designSync->syncAll();

        return response()->json([
            'success' => true,
            'paths' => [
                'html' => $this->displayPath($target . '.html'),
                'yaml' => $this->displayPath($target . '.yml'),
            ],
        ]);
    }

    protected function displayPath(string $absolute): string
    {
        return str_starts_with($absolute, base_path())
            ? ltrim(substr($absolute, strlen(base_path())), '/')
            : $absolute;
    }
}
