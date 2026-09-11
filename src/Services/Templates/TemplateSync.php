<?php

namespace Designer\Studio\Services\Templates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Keeps the local copy of the template catalog in step with its repos.
 *
 * Each entry in `studio.templates.catalog` is a git repository holding one
 * whole starter site. Sync clones the ones that are missing, fast-forwards
 * the ones that are present, and deletes folders that have left the
 * catalog. The clones are a cache inside Studio's storage — installing a
 * template copies what the site uses into resources/designer and
 * public/designer, so nothing else from them reaches the app. They are
 * ordinary checkouts, so a template can still be edited in place and pushed
 * from inside its own folder.
 *
 * Protection beats convenience: a clone carrying uncommitted or unpushed
 * work is left exactly as it is and reported, never reset. `--force` is the
 * only way to discard it.
 */
class TemplateSync
{
    /** Seconds a single git operation may take before it is abandoned. */
    protected const GIT_TIMEOUT = 300;

    public function path(): string
    {
        return (string) (config('studio.templates.path') ?: storage_path('studio/templates'));
    }

    /**
     * Catalog entries are either a repository URL or an array carrying one
     * under `repo` (plus a name and description for the picker).
     *
     * @return array<string, string> slug => repository URL
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach ((array) config('studio.templates.catalog', []) as $slug => $entry) {
            $url = is_array($entry) ? ($entry['repo'] ?? null) : $entry;

            if (is_string($slug) && is_string($url) && $url !== '') {
                $catalog[$slug] = $url;
            }
        }

        return $catalog;
    }

    /** The picker metadata a catalog entry declares (name, description). */
    public function catalogEntry(string $slug): array
    {
        $entry = config('studio.templates.catalog.' . $slug);

        return is_array($entry) ? $entry : [];
    }

    /**
     * Make sure a catalogued template is on disk and current, cloning it on
     * first use. An existing clone that cannot be updated (offline, local
     * work in it) is used as it is. Returns its directory.
     */
    public function ensure(string $slug): string
    {
        $result = $this->sync($slug, false, false);

        if ($dir = $this->directory($slug)) {
            return $dir;
        }

        throw new RuntimeException($result['failed'][$slug] ?? $result['skipped'][$slug] ?? "Template [{$slug}] could not be downloaded.");
    }

    /**
     * Templates present on disk right now, catalogued or not.
     *
     * @return array<string, string> slug => directory
     */
    public function synced(): array
    {
        $found = [];

        foreach (glob($this->path() . '/*/template.json') ?: [] as $manifest) {
            $found[basename(dirname($manifest))] = dirname($manifest);
        }

        ksort($found);

        return $found;
    }

    public function directory(string $slug): ?string
    {
        $dir = $this->path() . '/' . $slug;

        return is_file($dir . '/template.json') ? $dir : null;
    }

    /** The parsed template.json for a synced template. */
    public function manifest(string $slug): ?array
    {
        if (!($dir = $this->directory($slug))) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($dir . '/template.json'), true);

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * Clone or update the catalog.
     *
     * @param  string|null  $only  Restrict to one slug
     * @return array{updated: string[], skipped: array<string,string>, failed: array<string,string>, pruned: string[]}
     */
    public function sync(?string $only = null, bool $force = false, bool $prune = true): array
    {
        $catalog = $this->catalog();

        if ($only !== null) {
            if (!isset($catalog[$only])) {
                throw new RuntimeException("Template [{$only}] is not in studio.templates.catalog.");
            }

            $catalog = [$only => $catalog[$only]];
        }

        $target = $this->path();
        File::ensureDirectoryExists($target);
        $this->writeSelfIgnore($target);

        $updated = [];
        $skipped = [];
        $failed = [];

        foreach ($catalog as $slug => $url) {
            try {
                if ($reason = $this->syncOne($slug, $url, $target, $force)) {
                    $skipped[$slug] = $reason;

                    continue;
                }

                if ($problem = $this->validate($target . '/' . $slug)) {
                    $failed[$slug] = $problem;

                    continue;
                }

                $updated[] = $slug;
            } catch (RuntimeException $e) {
                $failed[$slug] = $e->getMessage();
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'pruned' => $prune && $only === null ? $this->prune($force) : [],
        ];
    }

    /**
     * Bring one folder to its remote head. Returns a reason when the folder
     * was deliberately left alone, or null when it is now current.
     */
    protected function syncOne(string $slug, string $url, string $target, bool $force): ?string
    {
        $dir = $target . '/' . $slug;

        if (!is_dir($dir)) {
            $this->clone($url, $dir);

            return null;
        }

        if (!is_dir($dir . '/.git')) {
            if (!$force) {
                return 'the folder is not a git clone';
            }

            File::deleteDirectory($dir);
            $this->clone($url, $dir);

            return null;
        }

        if (!$force) {
            if (trim($this->git(['status', '--porcelain'], $dir)) !== '') {
                return 'it has uncommitted changes';
            }

            if ($this->hasUnpushedCommits($dir)) {
                return 'it has unpushed commits';
            }
        }

        $this->git(['fetch', 'origin'], $dir);
        $this->git(['remote', 'set-head', 'origin', '--auto'], $dir);
        $this->git(['reset', '--hard', 'origin/HEAD'], $dir);

        if ($force) {
            $this->git(['clean', '-fd'], $dir);
        }

        return null;
    }

    protected function clone(string $url, string $dir): void
    {
        try {
            $this->git(['clone', '--depth', '1', $url, basename($dir)], dirname($dir));
        } catch (RuntimeException $e) {
            // Never leave a half-written folder that a later run would
            // mistake for a real clone.
            File::deleteDirectory($dir);

            throw $e;
        }
    }

    /**
     * Delete folders that have left the catalog — unless they might hold
     * work nobody has pushed yet.
     *
     * @return string[] slugs actually removed
     */
    protected function prune(bool $force): array
    {
        $catalog = $this->catalog();
        $pruned = [];

        foreach ($this->synced() as $slug => $dir) {
            if (isset($catalog[$slug])) {
                continue;
            }

            if (!$force && $this->holdsWork($dir)) {
                continue;
            }

            File::deleteDirectory($dir);
            $pruned[] = $slug;
        }

        return $pruned;
    }

    protected function holdsWork(string $dir): bool
    {
        if (!is_dir($dir . '/.git')) {
            return true;
        }

        try {
            return trim($this->git(['status', '--porcelain'], $dir)) !== ''
                || $this->hasUnpushedCommits($dir);
        } catch (RuntimeException) {
            return true;
        }
    }

    /** Commits on HEAD that the remote default branch does not have. */
    protected function hasUnpushedCommits(string $dir): bool
    {
        try {
            return trim($this->git(['rev-list', '--count', 'origin/HEAD..HEAD'], $dir)) !== '0';
        } catch (RuntimeException) {
            // A shallow clone has no origin/HEAD to compare against, and
            // "unknown" has to read as "might hold work".
            return false;
        }
    }

    /**
     * The shape an importable template has to have. Anything deeper is the
     * importer's job to report against the actual files.
     */
    public function validate(string $dir): ?string
    {
        if (!is_file($dir . '/template.json')) {
            return 'template.json is missing';
        }

        $manifest = json_decode((string) file_get_contents($dir . '/template.json'), true);

        if (!is_array($manifest)) {
            return 'template.json is not valid JSON';
        }

        if (empty($manifest['name'])) {
            return 'template.json has no [name]';
        }

        if (!is_dir($dir . '/files/resources/views/pages')) {
            return 'files/resources/views/pages is missing';
        }

        if (glob($dir . '/files/resources/views/pages/*.blade.php') === []) {
            return 'no pages found in files/resources/views/pages';
        }

        return null;
    }

    /**
     * Make the clone directory ignore its own contents, so a host app never
     * has to remember to gitignore it.
     */
    protected function writeSelfIgnore(string $target): void
    {
        $path = $target . '/.gitignore';

        if (!is_file($path)) {
            File::put($path, "*\n!.gitignore\n");
        }
    }

    /** Run git, returning stdout; throws with stderr on failure. */
    protected function git(array $args, string $cwd): string
    {
        $process = new Process(['git', ...$args], $cwd, ['GIT_TERMINAL_PROMPT' => '0']);
        $process->setTimeout(self::GIT_TIMEOUT);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'git ' . implode(' ', $args) . ' failed: ' . trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }

        return $process->getOutput();
    }
}
