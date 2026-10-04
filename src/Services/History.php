<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\TemplateLink;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Undo and redo for the whole site: one line of history, whoever made the
 * change — a field edit, a moved section, a content row, a Code view save,
 * an Assistant turn.
 *
 * It does not know what a change was. An entry is a picture of everything a
 * change can touch — the site's files (resources/designer) and Studio's
 * documents of them (live, draft, and the mirror's own state, so a restored
 * site needs no re-reading) — taken at the end of any request that wrote to
 * either (the provider's `terminating` hook calls commit()). Undo puts the
 * previous picture back, file for file.
 *
 * Kept under storage/studio/history/: `index.json` (the entries and the
 * cursor) and `blobs/` — every file version and every manifest, named by
 * the hash of its contents, so a file that did not change costs nothing.
 *
 * Not covered: the site's public files (uploaded media). Publishing starts
 * the history again — undo never takes a live site back.
 */
class History
{
    /** Entries kept; the oldest fall off */
    public const LIMIT = 60;

    /** Files larger than this are left alone: never recorded, never restored or removed */
    protected const MAX_FILE = 1048576;

    /** Edits to the same documents this close together are one step (typing in a field) */
    protected const TYPING_WINDOW = 2.0;

    /** What follows an Assistant turn this soon is part of it (the editor re-reading the files it changed) */
    protected const TURN_WINDOW = 30.0;

    protected bool $dirty = false;

    /** Set once this request restored an entry: what it writes after that is the restore itself */
    protected bool $restored = false;

    protected ?string $label = null;

    protected string $kind = 'edit';

    public function __construct(
        protected StudioStorage $storage,
    ) {}

    /* ------------------------------------------------------------ */
    /*  Recording                                                    */
    /* ------------------------------------------------------------ */

    /**
     * Something this request did may have changed the site. A label names
     * the step where the changed files alone would not ("Assistant · …").
     */
    public function mark(?string $label = null, string $kind = 'edit'): void
    {
        $this->dirty = true;

        if ($label !== null) {
            $this->label = $label;
            $this->kind = $kind;
        }
    }

    /** The end of the request: record what it changed, if it changed anything. */
    public function commit(): void
    {
        if (!$this->dirty || $this->restored || !SitePaths::installed()) {
            return;
        }

        $this->dirty = false;

        try {
            $this->locked(fn () => $this->record($this->label, $this->kind));
        } catch (\Throwable $e) {
            // History is a convenience: a failure to record never fails the edit
            report($e);
        }
    }

    /** Forget everything; the site as it stands becomes the first entry. */
    public function reset(): void
    {
        File::deleteDirectory($this->dir());
        $this->dirty = true;
        $this->label = null;
        $this->kind = 'edit';
    }

    protected function record(?string $label, string $kind): void
    {
        $index = $this->index();
        $manifest = $this->scan(store: true);
        $hash = $this->put(json_encode($manifest));
        $now = microtime(true);

        if (!$index['entries']) {
            $index['entries'][] = ['manifest' => $hash, 'label' => null, 'kind' => 'base', 'at' => $now, 'paths' => []];
            $index['cursor'] = 0;
            $this->save($index);

            return;
        }

        $head = $index['entries'][$index['cursor']];

        if ($head['manifest'] === $hash) {
            return;
        }

        $paths = $this->changed($this->manifest($head['manifest']), $manifest);
        $last = $index['cursor'] === count($index['entries']) - 1;

        $continues = $last && $index['cursor'] > 0 && $label === null && (
            // The editor re-reading the files a turn changed (the mirror's state moves with it)
            ($head['kind'] === 'assistant' && $now - $head['at'] < self::TURN_WINDOW && in_array('studio/mirror.json', $paths, true))
            || ($head['kind'] === 'edit' && $head['paths'] === $paths && $now - $head['at'] < self::TYPING_WINDOW)
        );

        if ($continues) {
            $head['manifest'] = $hash;
            $head['paths'] = array_values(array_unique([...$head['paths'], ...$paths]));
            // An edit's window slides with the typing; a turn's does not
            $head['at'] = $head['kind'] === 'edit' ? $now : $head['at'];
            $index['entries'][$index['cursor']] = $head;
            $this->save($index);

            return;
        }

        $dropped = count($index['entries']) - 1 - $index['cursor'];
        $index['entries'] = array_slice($index['entries'], 0, $index['cursor'] + 1);
        $index['entries'][] = ['manifest' => $hash, 'label' => $label ?? $this->describe($paths), 'kind' => $kind, 'at' => $now, 'paths' => $paths];

        if (count($index['entries']) > self::LIMIT) {
            $dropped += count($index['entries']) - self::LIMIT;
            $index['entries'] = array_slice($index['entries'], -self::LIMIT);
        }

        $index['cursor'] = count($index['entries']) - 1;
        $this->save($index);

        if ($dropped > 0) {
            $this->collect($index);
        }
    }

    /* ------------------------------------------------------------ */
    /*  Undo / redo                                                  */
    /* ------------------------------------------------------------ */

    /** @return array{undo: ?string, redo: ?string} what each would take back or bring back, or null */
    public function state(): array
    {
        $index = $this->index();
        $entries = $index['entries'];
        $cursor = $index['cursor'];

        return [
            'undo' => $cursor > 0 ? $entries[$cursor]['label'] : null,
            'redo' => isset($entries[$cursor + 1]) ? $entries[$cursor + 1]['label'] : null,
        ];
    }

    /** @return ?array{label: string, paths: string[]} the step taken back, or null when there is none */
    public function undo(): ?array
    {
        return $this->step(-1);
    }

    /** @return ?array{label: string, paths: string[]} */
    public function redo(): ?array
    {
        return $this->step(1);
    }

    protected function step(int $direction): ?array
    {
        if (!SitePaths::installed()) {
            return null;
        }

        $done = $this->locked(function () use ($direction) {
            // A change nobody recorded (a file edited by hand since) becomes
            // a step of its own first, so undo takes back that and no more
            $this->record(null, 'edit');

            $index = $this->index();
            $target = $index['cursor'] + $direction;

            if (!isset($index['entries'][$target]) || !isset($index['entries'][$index['cursor']])) {
                return null;
            }

            // Undo takes back the entry under the cursor; redo brings the next one
            $entry = $index['entries'][$direction < 0 ? $index['cursor'] : $target];
            $paths = $this->restore($this->manifest($index['entries'][$target]['manifest']));

            $index['cursor'] = $target;
            $this->save($index);

            return ['label' => $entry['label'] ?? 'Change', 'paths' => $paths];
        });

        if ($done === null) {
            return null;
        }

        $this->restored = true;

        if (array_filter($done['paths'], fn ($path) => str_starts_with($path, 'site/'))) {
            // A linked template follows the site
            app(TemplateLink::class)->touch();
        }

        // The documents came back with the files, so there is nothing to
        // re-read — but the section library is derived from the files
        app(SiteMirror::class)->sync();

        return $done;
    }

    /**
     * Make the tracked files match a manifest.
     *
     * @return string[] the paths that changed
     */
    protected function restore(array $target): array
    {
        $current = $this->scan(store: false);
        $paths = $this->changed($current, $target);

        foreach ($paths as $path) {
            $absolute = $this->absolute($path);

            if (!isset($target[$path])) {
                File::delete($absolute);

                continue;
            }

            File::ensureDirectoryExists(dirname($absolute));
            File::put($absolute, File::get($this->blob($target[$path])));
        }

        clearstatcache();

        return $paths;
    }

    /* ------------------------------------------------------------ */
    /*  What is tracked                                              */
    /* ------------------------------------------------------------ */

    /**
     * Every tracked file by its path (`site/…` under resources/designer,
     * `studio/…` under the storage folder) and the hash of its contents.
     *
     * @return array<string, string>
     */
    protected function scan(bool $store): array
    {
        $manifest = [];
        $base = $this->storage->getBasePath();

        $roots = ['site' => [SitePaths::resources()], 'studio' => [$base . '/mirror.json']];

        foreach (StudioStorage::WORKSPACE_TREES as $tree) {
            $roots['studio'][] = $base . '/' . $tree;
            $roots['studio'][] = $base . '/draft/' . $tree;
        }

        foreach ($roots as $prefix => $paths) {
            $root = $prefix === 'site' ? rtrim(SitePaths::resources(), '/') : $base;

            foreach ($paths as $path) {
                foreach ($this->files($path) as $file) {
                    $hash = sha1_file($file);
                    $manifest[$prefix . '/' . ltrim(substr($file, strlen($root)), '/')] = $hash;

                    if ($store && !is_file($this->blob($hash))) {
                        File::ensureDirectoryExists(dirname($this->blob($hash)));
                        File::copy($file, $this->blob($hash));
                    }
                }
            }
        }

        ksort($manifest);

        return $manifest;
    }

    /** @return string[] */
    protected function files(string $path): array
    {
        if (is_file($path)) {
            return filesize($path) <= self::MAX_FILE ? [$path] : [];
        }

        if (!is_dir($path)) {
            return [];
        }

        $files = [];

        foreach (File::allFiles($path, true) as $file) {
            if ($file->getSize() <= self::MAX_FILE) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function absolute(string $path): string
    {
        [$prefix, $relative] = explode('/', $path, 2);

        return ($prefix === 'site' ? rtrim(SitePaths::resources(), '/') : $this->storage->getBasePath()) . '/' . $relative;
    }

    /** @return string[] paths whose contents differ between two manifests, or exist in only one */
    protected function changed(array $from, array $to): array
    {
        $paths = [];

        foreach ($to as $path => $hash) {
            if (($from[$path] ?? null) !== $hash) {
                $paths[] = $path;
            }
        }

        foreach ($from as $path => $hash) {
            if (!isset($to[$path])) {
                $paths[] = $path;
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * A step's name, from what it changed: the draft documents say what was
     * edited; without any, it was the files themselves.
     */
    protected function describe(array $paths): string
    {
        $things = [];
        $files = [];

        foreach ($paths as $path) {
            if (preg_match('#^studio/(?:draft/)?(pages|layouts|blocks|collections|site)/(.+)\.json$#', $path, $m)) {
                $name = Str::headline(basename($m[2]));
                $things[] = match ($m[1]) {
                    'pages' => $name . ' page',
                    'layouts' => $name . ' layout',
                    'blocks' => $name . ' block',
                    'collections' => $name . ' content',
                    default => 'Site settings',
                };
            } elseif (str_starts_with($path, 'site/')) {
                $files[] = basename($path);
            }
        }

        $things = array_values(array_unique($things ?: $files));

        return match (true) {
            $things === [] => 'Change',
            count($things) <= 2 => 'Edit · ' . implode(' and ', $things),
            default => 'Edit · ' . $things[0] . ' and ' . (count($things) - 1) . ' more',
        };
    }

    /* ------------------------------------------------------------ */
    /*  Storage                                                      */
    /* ------------------------------------------------------------ */

    protected function dir(): string
    {
        return $this->storage->getBasePath() . '/history';
    }

    protected function blob(string $hash): string
    {
        return $this->dir() . '/blobs/' . substr($hash, 0, 2) . '/' . $hash;
    }

    protected function put(string $contents): string
    {
        $hash = sha1($contents);

        if (!is_file($this->blob($hash))) {
            File::ensureDirectoryExists(dirname($this->blob($hash)));
            File::put($this->blob($hash), $contents);
        }

        return $hash;
    }

    protected function manifest(string $hash): array
    {
        return json_decode(File::get($this->blob($hash)), true) ?: [];
    }

    /** @return array{cursor: int, entries: array<int, array>} */
    protected function index(): array
    {
        $index = is_file($this->dir() . '/index.json') ? json_decode(File::get($this->dir() . '/index.json'), true) : null;

        return is_array($index) && isset($index['entries'], $index['cursor']) ? $index : ['cursor' => 0, 'entries' => []];
    }

    protected function save(array $index): void
    {
        File::ensureDirectoryExists($this->dir());
        File::put($this->dir() . '/index.json', json_encode($index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
    }

    /** Throw away the blobs no entry points at any more. */
    protected function collect(array $index): void
    {
        $keep = [];

        foreach ($index['entries'] as $entry) {
            $keep[$entry['manifest']] = true;

            foreach ($this->manifest($entry['manifest']) as $hash) {
                $keep[$hash] = true;
            }
        }

        foreach (File::allFiles($this->dir() . '/blobs') as $file) {
            if (!isset($keep[$file->getFilename()])) {
                File::delete($file->getPathname());
            }
        }
    }

    /** One writer at a time: two tabs, or an edit landing while a turn finishes. */
    protected function locked(callable $callback): mixed
    {
        File::ensureDirectoryExists($this->dir());
        $handle = fopen($this->dir() . '/.lock', 'c');

        try {
            flock($handle, LOCK_EX);

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
