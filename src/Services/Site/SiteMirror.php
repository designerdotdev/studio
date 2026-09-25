<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Facades\File;

/**
 * Keeps Studio's live documents and the installed site's files in step.
 *
 * The files in resources/designer ARE the live site — the runtime provider
 * serves them, with or without Studio. Studio's live JSON tree is a mirror
 * of those files, which lets every part of the editor that reads "the live
 * site" (publish status, discard, the draft seed) keep working on documents:
 *
 *   refresh()  files → live documents. Runs whenever the files may have
 *              changed behind Studio's back (a Code mode save, the Assistant,
 *              an edit in the developer's own editor, a deploy). Only
 *              documents whose own files changed are re-read, and a change
 *              reaches the draft only where the draft had no unpublished
 *              edits of its own — so nobody's work is overwritten.
 *
 *   flush()    live documents → files. Runs after a publish (and after each
 *              edit when draft mode is off).
 *
 * The state file records a hash of every file the mirror depends on and,
 * for every page, layout, and block, the instance id behind each section
 * tag — which is what lets the writer edit a file in place.
 */
class SiteMirror
{
    public const STATE = 'mirror.json';

    public const TREES = ['pages', 'layouts', 'blocks', 'collections'];

    public function __construct(
        protected StudioStorage $storage,
        protected DesignSyncService $designSync,
        protected SiteReader $reader,
        protected SiteWriter $writer,
    ) {}

    /** Bring the section library and the live documents up to date. */
    public function sync(): bool
    {
        if (!SitePaths::installed()) {
            return false;
        }

        $this->designSync->syncAll();

        return $this->refresh();
    }

    /**
     * Re-read whatever changed on disk since the last sync. Returns true
     * when any live document changed.
     */
    public function refresh(bool $force = false): bool
    {
        if (!SitePaths::installed()) {
            return false;
        }

        $current = $this->fingerprint();
        $state = $this->state();

        if (!$force && $current === ($state['hashes'] ?? null)) {
            return false;
        }

        $this->designSync->syncAll();

        $previous = $this->liveDocs();
        $read = $this->reader->read($previous);
        $changed = fn (string $relative) => ($state['hashes'][$relative] ?? null) !== ($current[$relative] ?? null);

        // A new or changed section contract, or edited settings, can change
        // how every page reads — re-take them all in that case.
        $wholesale = $force || $changed(SitePaths::relative(SitePaths::manifest())) || $this->libraryChanged($state['hashes'] ?? [], $current);

        $next = ['pages' => [], 'layouts' => [], 'blocks' => [], 'collections' => [], 'site' => null];
        $nextState = ['pages' => [], 'layouts' => [], 'blocks' => [], 'collections' => []];

        foreach (['pages', 'layouts', 'blocks', 'collections'] as $tree) {
            foreach ($read[$tree] as $key => $entry) {
                $recorded = $state[$tree][$key] ?? null;
                $keep = !$wholesale
                    && isset($previous[$tree][$key])
                    && $recorded !== null
                    && ($recorded['path'] ?? null) === $entry['path']
                    && !$changed($entry['path'])
                    && ($tree !== 'collections' || !$changed($this->schemaPath($entry['path'])));

                $next[$tree][$key] = $keep ? $previous[$tree][$key] : $entry['doc'];
                $nextState[$tree][$key] = $keep ? $recorded : $this->stateEntry($tree, $entry);
            }

            // A live document that was never written to a file (a publish
            // refused it — a hand-written page held its URL) is still the
            // user's work: keep it rather than read its absence as a delete.
            foreach ($previous[$tree] ?? [] as $key => $doc) {
                if (!isset($next[$tree][$key]) && !isset($state[$tree][$key])) {
                    $next[$tree][$key] = $doc;
                }
            }
        }

        $siteChanged = $wholesale || $changed($read['site']['path']) || ($previous['site'] ?? null) === null;
        $next['site'] = $siteChanged ? $read['site']['doc'] : $previous['site'];

        $this->writeLive($next);
        $this->propagateToDraft($previous, $next);

        $this->saveState([
            'hashes' => $current,
            ...$nextState,
            'code_pages' => $read['code_pages'],
        ]);

        return SiteReader::normalise(['x' => $previous]) !== SiteReader::normalise(['x' => $next]);
    }

    /**
     * Write the live documents into the site's files. Returns notes about
     * anything that could not be written.
     *
     * @return string[]
     */
    public function flush(): array
    {
        if (!SitePaths::installed()) {
            return [];
        }

        $state = $this->state();

        // Files edited behind Studio's back since the last sync would be
        // written over blind — pull them in first.
        if ($this->fingerprint() !== ($state['hashes'] ?? null)) {
            $this->refresh();
            $state = $this->state();
        }

        $this->designSync->syncAll();

        $result = $this->writer->write($this->liveDocs(), $state);

        $this->saveState([
            ...$state,
            ...$result['state'],
            'hashes' => $this->fingerprint(),
        ]);

        return $result['notes'];
    }

    /**
     * Start over from the files: forget every document (live and draft) and
     * read the site afresh. Used right after a template is installed.
     */
    public function rebuild(): void
    {
        $base = $this->storage->getBasePath();

        foreach ([...self::TREES, 'site'] as $tree) {
            foreach ([$base . '/' . $tree, $base . '/draft/' . $tree] as $directory) {
                foreach (glob($directory . '/*.json') ?: [] as $document) {
                    File::delete($document);
                }
            }
        }

        File::delete($base . '/' . self::STATE);

        $this->designSync->syncAll();
        $this->refresh(true);
    }

    /** Slugs a new Studio page may not take — hand-written pages own them. */
    public function reservedSlugs(): array
    {
        return array_values(array_unique([...($this->state()['code_pages'] ?? []), 'index']));
    }

    /** The recorded hashes and ids from the last sync. */
    public function state(): array
    {
        $path = $this->storage->getBasePath() . '/' . self::STATE;
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * A hash of every file the mirror depends on, keyed by path relative to
     * the app root.
     *
     * @return array<string, string>
     */
    public function fingerprint(): array
    {
        $files = [
            SitePaths::manifest(),
            SitePaths::data('site.json'),
            ...(glob(SitePaths::data('collections/*.json')) ?: []),
            ...(glob(SitePaths::data('collections/*.yml')) ?: []),
            ...(glob(SitePaths::pages('*.blade.php')) ?: []),
            ...(glob(SitePaths::components(SitePaths::LAYOUTS . '/*.blade.php')) ?: []),
            ...(glob(SitePaths::components(SitePaths::BLOCKS . '/*.blade.php')) ?: []),
            ...$this->componentContracts(),
        ];

        $hashes = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $hashes[SitePaths::relative($file)] = md5_file($file);
            }
        }

        ksort($hashes);

        return $hashes;
    }

    /* ------------------------------------------------------------ */

    /** @return string[] every section `.yml` (their set decides what a page is made of) */
    protected function componentContracts(): array
    {
        $base = SitePaths::components();

        if (!is_dir($base)) {
            return [];
        }

        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'yml') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function libraryChanged(array $before, array $after): bool
    {
        $prefix = SitePaths::relative(SitePaths::components()) . '/';
        $contracts = fn (array $hashes) => array_filter(
            $hashes,
            fn ($hash, $path) => str_starts_with($path, $prefix) && str_ends_with($path, '.yml'),
            ARRAY_FILTER_USE_BOTH
        );

        return $contracts($before) !== $contracts($after);
    }

    protected function schemaPath(string $json): string
    {
        return substr($json, 0, -5) . '.yml';
    }

    protected function stateEntry(string $tree, array $entry): array
    {
        return match ($tree) {
            'pages' => ['path' => $entry['path'], 'ids' => $entry['ids']],
            'layouts' => ['path' => $entry['path'], 'before' => $entry['before'], 'after' => $entry['after']],
            'blocks' => ['path' => $entry['path']],
            'collections' => ['path' => $entry['path'], 'source' => $entry['doc']['source']],
            default => throw new \InvalidArgumentException("Unknown document tree [{$tree}]."),
        };
    }

    /** Every live document, read straight off disk (workspace-immune). */
    public function liveDocs(): array
    {
        $base = $this->storage->getBasePath();
        $docs = [];

        foreach (self::TREES as $tree) {
            $docs[$tree] = $this->readTree($base . '/' . $tree);
        }

        $site = $base . '/site/data.json';
        $docs['site'] = is_file($site) ? json_decode((string) file_get_contents($site), true) : null;

        return $docs;
    }

    protected function readTree(string $directory): array
    {
        $docs = [];

        foreach (glob($directory . '/*.json') ?: [] as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            if (is_array($decoded)) {
                $docs[basename($file, '.json')] = $decoded;
            }
        }

        ksort($docs);

        return $docs;
    }

    /** Replace the live trees with these documents. */
    protected function writeLive(array $docs): void
    {
        $base = $this->storage->getBasePath();

        foreach (self::TREES as $tree) {
            $this->replaceTree($base . '/' . $tree, $docs[$tree]);
        }

        if ($docs['site'] !== null) {
            $this->putJson($base . '/site/data.json', $docs['site']);
        }
    }

    /**
     * Carry live changes into the draft, one document at a time — but only
     * where the draft still matched the old live document. A draft with
     * edits of its own keeps them; the difference then shows up as an
     * unpublished change, which is exactly what it is.
     */
    protected function propagateToDraft(array $previous, array $next): void
    {
        if (!config('studio.draft_mode', true)) {
            return;
        }

        $base = $this->storage->getBasePath() . '/draft';

        foreach (self::TREES as $tree) {
            $draft = $this->readTree($base . '/' . $tree);

            foreach (array_unique([...array_keys($previous[$tree] ?? []), ...array_keys($next[$tree])]) as $key) {
                $old = $previous[$tree][$key] ?? null;
                $new = $next[$tree][$key] ?? null;

                if (SiteReader::normalise($old) === SiteReader::normalise($new)) {
                    continue; // the live side did not change
                }

                if (SiteReader::normalise($draft[$key] ?? null) !== SiteReader::normalise($old)) {
                    continue;
                }

                $path = $base . '/' . $tree . '/' . $key . '.json';

                if ($new === null) {
                    File::delete($path);
                } else {
                    $this->putJson($path, $new);
                }
            }
        }

        $draftSite = $base . '/site/data.json';
        $draftDoc = is_file($draftSite) ? json_decode((string) file_get_contents($draftSite), true) : null;

        if ($next['site'] !== null && SiteReader::normalise($draftDoc) === SiteReader::normalise($previous['site'] ?? null)) {
            $this->putJson($draftSite, $next['site']);
        }
    }

    protected function replaceTree(string $directory, array $docs): void
    {
        File::ensureDirectoryExists($directory);

        foreach ($docs as $key => $doc) {
            $this->putJson($directory . '/' . $key . '.json', $doc);
        }

        foreach (glob($directory . '/*.json') ?: [] as $file) {
            if (!array_key_exists(basename($file, '.json'), $docs)) {
                File::delete($file);
            }
        }
    }

    protected function putJson(string $path, array $doc): void
    {
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (is_file($path) && file_get_contents($path) === $json) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json);
    }

    protected function saveState(array $state): void
    {
        $this->putJson($this->storage->getBasePath() . '/' . self::STATE, $state);
    }
}
