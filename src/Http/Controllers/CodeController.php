<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\CodeWorkspace;
use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Support\DevMode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * Code mode — browse and edit the Studio workspace (see CodeWorkspace for
 * what that covers and how paths are kept inside it).
 *
 * Every route 404s unless the dev-mode gate passes, exactly like the
 * single-section editor in DevModeController, which shares the same
 * workspace service.
 */
class CodeController extends Controller
{
    public function __construct(
        protected CodeWorkspace $workspace,
        protected DesignSyncService $designSync,
    ) {}

    public function tree(Request $request)
    {
        abort_unless(DevMode::enabled(), 404);

        $view = $request->query('view') === 'laravel' ? 'laravel' : 'design';

        return response()->json([
            'success' => true,
            'view' => $view,
            'nodes' => $this->workspace->tree($view),
        ]);
    }

    public function show(Request $request)
    {
        abort_unless(DevMode::enabled(), 404);

        $path = (string) $request->query('path', '');

        return $this->guard(fn () => array_merge(
            ['success' => true],
            $this->workspace->read($path)
        ));
    }

    public function update(Request $request)
    {
        abort_unless(DevMode::enabled(), 404);

        $validated = $request->validate([
            'path' => 'required|string|max:512',
            'contents' => 'present|string',
        ]);

        // Laravel's TrimStrings middleware runs over request input, which
        // would silently eat a file's trailing newline (and any leading
        // whitespace) on every save. File contents have to come off the raw
        // body instead — validation above still guards the shape.
        $contents = $this->rawContents($request) ?? $validated['contents'];

        return $this->guard(function () use ($validated, $contents) {
            $result = $this->workspace->write($validated['path'], $contents);

            // Section edits only reach the canvas once the library has them
            $synced = $this->workspace->isSectionSource($validated['path']);

            if ($synced) {
                $this->designSync->syncAll();
            }

            return array_merge(['success' => true, 'synced' => $synced], $result);
        });
    }

    public function store(Request $request)
    {
        abort_unless(DevMode::enabled(), 404);

        $validated = $request->validate([
            'category' => 'required|string|max:64',
            'name' => 'required|string|max:64',
            'label' => 'nullable|string|max:120',
        ]);

        return $this->guard(function () use ($validated) {
            $created = $this->workspace->createSection(
                $validated['category'],
                $validated['name'],
                $validated['label'] ?? ''
            );

            $this->designSync->syncAll();

            return array_merge(['success' => true], $created);
        });
    }

    public function destroy(Request $request)
    {
        abort_unless(DevMode::enabled(), 404);

        $validated = $request->validate([
            'path' => 'required|string|max:512',
        ]);

        return $this->guard(function () use ($validated) {
            $result = $this->workspace->delete($validated['path']);

            if ($this->workspace->isSectionSource($validated['path'])) {
                $this->designSync->syncAll();
            }

            return array_merge(['success' => true], $result);
        });
    }

    /**
     * The `contents` field exactly as the browser sent it, before any
     * middleware touched it. Null when the body is not the JSON object we
     * expect, so the caller can fall back to the validated input.
     */
    protected function rawContents(Request $request): ?string
    {
        if (! str_contains((string) $request->header('Content-Type'), 'json')) {
            return null;
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) && is_string($decoded['contents'] ?? null)
            ? $decoded['contents']
            : null;
    }

    /**
     * Workspace failures are user-facing messages about their own files
     * ("that path is outside the workspace", "invalid YAML"), so they come
     * back as 422s rather than exceptions.
     */
    protected function guard(callable $run)
    {
        try {
            return response()->json($run());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
