<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Support\Facades\File;

/**
 * Draft mode — the editor works on storage/studio/draft/*, while the live
 * site is the installed files in resources/designer (mirrored as documents
 * in storage/studio/{pages,layouts,…} by SiteMirror). Publishing mirrors the
 * whole draft to live and writes it into the files in one operation;
 * discarding re-reads the files and mirrors live back to draft.
 * Deliberately whole-site: layouts and global blocks are shared across
 * pages, so partial publishes could go live referencing draft-only state.
 *
 * This service works on raw paths (never through the storage workspace
 * prefix) so it behaves identically no matter which workspace the current
 * request is using.
 */
class PublishService
{
    /** Volatile keys ignored when deciding whether a document changed */
    protected const IGNORED_KEYS = ['updated_at', 'created_at'];

    public function __construct(
        protected StudioStorage $storage
    ) {}

    protected function livePath(string $tree): string
    {
        return $this->storage->getBasePath() . '/' . $tree;
    }

    protected function draftPath(string $tree): string
    {
        return $this->storage->getBasePath() . '/draft/' . $tree;
    }

    /**
     * First-boot seed: if the draft tree doesn't exist yet, mirror the
     * live documents into it so editing starts from the live site.
     */
    public function ensureDraftSeeded(): void
    {
        $draftRoot = $this->storage->getBasePath() . '/draft';

        if (File::isDirectory($draftRoot)) {
            return;
        }

        File::makeDirectory($draftRoot, 0755, true);

        foreach (StudioStorage::WORKSPACE_TREES as $tree) {
            File::makeDirectory($this->draftPath($tree), 0755, true);

            if (File::isDirectory($this->livePath($tree))) {
                File::copyDirectory($this->livePath($tree), $this->draftPath($tree));
            }
        }
    }

    /**
     * Everything that differs between draft and live.
     *
     * @return array{dirty: bool, items: array<int, array{type: string, slug: string, label: string, state: string}>}
     */
    public function status(): array
    {
        $items = [];

        foreach (StudioStorage::WORKSPACE_TREES as $tree) {
            $draftFiles = $this->jsonFiles($this->draftPath($tree));
            $liveFiles = $this->jsonFiles($this->livePath($tree));

            foreach (array_unique([...array_keys($draftFiles), ...array_keys($liveFiles)]) as $slug) {
                $draft = $draftFiles[$slug] ?? null;
                $live = $liveFiles[$slug] ?? null;

                $state = match (true) {
                    $draft !== null && $live === null => 'added',
                    $draft === null && $live !== null => 'removed',
                    $this->normalize($draft) !== $this->normalize($live) => 'updated',
                    default => null,
                };

                if ($state === null) {
                    continue;
                }

                $doc = $draft ?? $live;

                $items[] = [
                    'type' => rtrim($tree, 's'), // page | layout | block
                    'slug' => $slug,
                    'label' => $doc['title'] ?? $doc['name'] ?? $slug,
                    'state' => $state,
                ];
            }
        }

        return ['dirty' => $items !== [], 'items' => $items];
    }

    /**
     * Mirror the draft tree onto live. Returns the published status
     * (what changed) for messaging.
     */
    public function publishAll(): array
    {
        // Edits made to the files outside Studio reach the draft first, so
        // publishing never writes over them unseen.
        $this->site()->refresh();

        $status = $this->status();
        $this->mirrorTrees(fn ($tree) => [$this->draftPath($tree), $this->livePath($tree)]);

        $status['notes'] = $this->site()->flush();

        return $status;
    }

    /**
     * Throw the draft away: re-read the site's files, then mirror live back
     * onto the draft tree.
     */
    public function discardAll(): array
    {
        $this->site()->refresh();

        $status = $this->status();
        $this->mirrorTrees(fn ($tree) => [$this->livePath($tree), $this->draftPath($tree)]);

        return $status;
    }

    protected function site(): SiteMirror
    {
        return app(SiteMirror::class);
    }

    protected function mirrorTrees(callable $paths): void
    {
        foreach (StudioStorage::WORKSPACE_TREES as $tree) {
            [$from, $to] = $paths($tree);

            File::ensureDirectoryExists($from);
            File::ensureDirectoryExists($to);

            $sourceFiles = $this->fileNames($from);

            foreach ($sourceFiles as $name) {
                // Copy-then-rename so a half-written file can never be served
                $temp = $to . '/.' . $name . '.tmp';
                File::copy($from . '/' . $name, $temp);
                File::move($temp, $to . '/' . $name);
            }

            // Remove anything on the target that's gone from the source
            foreach (array_diff($this->fileNames($to), $sourceFiles) as $stale) {
                File::delete($to . '/' . $stale);
            }
        }
    }

    /** @return array<string, array> slug => decoded document */
    protected function jsonFiles(string $directory): array
    {
        if (!File::isDirectory($directory)) {
            return [];
        }

        $docs = [];

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $decoded = json_decode(File::get($file->getPathname()), true);

            if (is_array($decoded)) {
                $docs[$file->getFilenameWithoutExtension()] = $decoded;
            }
        }

        return $docs;
    }

    /** @return string[] json filenames in a directory */
    protected function fileNames(string $directory): array
    {
        if (!File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->map(fn ($file) => $file->getFilename())
            ->values()
            ->all();
    }

    protected function normalize(?array $doc): ?string
    {
        if ($doc === null) {
            return null;
        }

        foreach (self::IGNORED_KEYS as $key) {
            unset($doc[$key]);
        }

        return json_encode($doc);
    }
}
