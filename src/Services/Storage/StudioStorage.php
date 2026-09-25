<?php

namespace Designer\Studio\Services\Storage;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StudioStorage
{
    /** Document trees that exist per-workspace (draft vs live) */
    public const WORKSPACE_TREES = ['pages', 'layouts', 'blocks', 'collections', 'site'];

    protected string $basePath;

    /** '' = live, 'draft' = the working copy the editor operates on */
    protected string $workspace = '';

    /**
     * Set when a live site document was written this request. The live
     * tree mirrors the site's files, so the provider flushes it back to them
     * once the request is done (only happens with draft mode off).
     */
    protected bool $liveChanged = false;

    public function __construct()
    {
        $this->basePath = config('studio.storage_path', storage_path('studio'));
    }

    /* ------------------------------------------------------------ */
    /*  Workspaces (draft mode)                                      */
    /* ------------------------------------------------------------ */

    /**
     * Route all page/layout/block operations to the draft tree.
     * Called by editor entry points when draft mode is enabled;
     * public page rendering stays on the live tree.
     */
    public function useDraft(): void
    {
        $this->workspace = 'draft';
    }

    public function useLive(): void
    {
        $this->workspace = '';
    }

    public function workspace(): string
    {
        return $this->workspace;
    }

    /** Run a callback against the live tree, restoring the workspace after */
    public function inLive(callable $callback): mixed
    {
        $previous = $this->workspace;
        $this->workspace = '';

        try {
            return $callback();
        } finally {
            $this->workspace = $previous;
        }
    }

    /**
     * Only the per-site document trees are workspaced — the component
     * library (and anything else) is shared between draft and live.
     */
    protected function prefixed(string $path): string
    {
        if ($this->workspace === '') {
            return $path;
        }

        foreach (self::WORKSPACE_TREES as $tree) {
            if ($path === $tree || str_starts_with($path, $tree . '/')) {
                return $this->workspace . '/' . $path;
            }
        }

        return $path;
    }

    /** Whether live site documents changed this request; resets the flag. */
    public function consumeLiveChanges(): bool
    {
        $changed = $this->liveChanged;
        $this->liveChanged = false;

        return $changed;
    }

    protected function touch(string $path): void
    {
        if ($this->workspace !== '') {
            return;
        }

        foreach (self::WORKSPACE_TREES as $tree) {
            if ($path === $tree || str_starts_with($path, $tree . '/')) {
                $this->liveChanged = true;

                return;
            }
        }
    }

    /* ------------------------------------------------------------ */
    /*  File I/O                                                     */
    /* ------------------------------------------------------------ */

    public function ensureDirectoryExists(string $subPath = ''): string
    {
        $path = $this->basePath . ($subPath ? '/' . $this->prefixed($subPath) : '');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        return $path;
    }

    public function read(string $path): ?array
    {
        $fullPath = $this->basePath . '/' . $this->prefixed($path);

        if (!File::exists($fullPath)) {
            return null;
        }

        $content = File::get($fullPath);

        return json_decode($content, true);
    }

    public function write(string $path, array $data): bool
    {
        $fullPath = $this->basePath . '/' . $this->prefixed($path);
        $directory = dirname($fullPath);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $this->touch($path);

        return File::put(
            $fullPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->basePath . '/' . $this->prefixed($path);

        if (File::exists($fullPath)) {
            $this->touch($path);

            return File::delete($fullPath);
        }

        return true;
    }

    public function exists(string $path): bool
    {
        return File::exists($this->basePath . '/' . $this->prefixed($path));
    }

    public function list(string $directory, string $extension = 'json'): array
    {
        $fullPath = $this->basePath . '/' . $this->prefixed($directory);

        if (!File::isDirectory($fullPath)) {
            return [];
        }

        return collect(File::files($fullPath))
            ->filter(fn($file) => $file->getExtension() === $extension)
            ->map(fn($file) => $file->getFilenameWithoutExtension())
            ->values()
            ->toArray();
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Remove all studio data (for clean uninstall). Top-level entries named
     * in `$keep` survive (dev-reset keeps the template download cache).
     */
    public function purge(array $keep = []): bool
    {
        if (!File::isDirectory($this->basePath)) {
            return true;
        }

        if ($keep === []) {
            return File::deleteDirectory($this->basePath);
        }

        foreach (File::directories($this->basePath) as $directory) {
            if (!in_array(basename($directory), $keep, true)) {
                File::deleteDirectory($directory);
            }
        }

        foreach (File::files($this->basePath, true) as $file) {
            if (!in_array($file->getFilename(), $keep, true)) {
                File::delete($file->getPathname());
            }
        }

        return true;
    }
}
