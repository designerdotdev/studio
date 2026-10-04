<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Variations of an image, made by Codex's image generation.
 *
 * A job is a folder under storage/studio/assistant/variations/<id>/ holding
 * `job.json`, a copy of the source image, and whatever Codex writes there:
 * it is asked to save each result as `variation-N.png`, and the folder is
 * watched while it works, so every image is reported the moment it lands.
 * Nothing touches the site until one is kept — keeping moves that file into
 * the media library's uploads and hands back its URL.
 *
 * Codex is the only engine that can draw; without it the feature is off.
 *
 * A job has a `kind`: `image` varies a picture from the media library;
 * `section` varies the design of a page section, from a snapshot of it
 * (SectionSnapshot) — the snapshot is a job folder of its own (status
 * `snapshot`, never run) that each generation copies its source from, and
 * nothing of a section job is kept: the chosen concept goes to the Assistant
 * as an attachment, beside the snapshot, to be built.
 */
class ImageVariations
{
    public const MAX = 4;

    public const DEFAULT_PROMPT = 'Create a few variations of this';

    public const DEFAULT_SECTION_PROMPT = 'Create a few variations of this design';

    protected const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public function __construct(
        protected StudioStorage $storage,
        protected Engines $engines,
        protected Attachments $attachments,
        protected SectionSnapshot $snapshots,
    ) {}

    public function available(): bool
    {
        return $this->engines->isAvailable(Engines::CODEX);
    }

    /** Design variations also need the snapshot they start from. */
    public function sectionsAvailable(): bool
    {
        return $this->available() && $this->snapshots->available();
    }

    protected function root(): string
    {
        $dir = $this->storage->getBasePath() . '/assistant/variations';

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    protected function dir(string $id): string
    {
        return $this->root() . '/' . $id;
    }

    public function find(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/', $id) || !File::exists($this->dir($id) . '/job.json')) {
            return null;
        }

        $job = json_decode(File::get($this->dir($id) . '/job.json'), true);

        return is_array($job) ? $job : null;
    }

    protected function save(array $job): void
    {
        File::put($this->dir($job['id']) . '/job.json', json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Record a pending job for an image of the site.
     *
     * @param  string  $url  the image's URL, as an image field holds it
     */
    public function start(string $url, string $prompt, int $count): array
    {
        $original = $this->attachments->original($url);

        if (!$original) {
            throw new \RuntimeException('Variations need an image stored in this site — upload it or pick it from the media library first.');
        }

        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, [...self::EXTENSIONS, 'gif', 'avif'], true)) {
            throw new \RuntimeException('Variations cannot be made from a .' . $extension . ' file.');
        }

        return $this->create('image', $original, pathinfo($original, PATHINFO_FILENAME), $prompt, $count);
    }

    /**
     * Take the picture a section's variations start from. The folder it
     * lands in is a job that never runs: startSection() copies from it.
     *
     * @param  string  $origin  the running site, where the section's styles and images load from
     *
     * @throws \RuntimeException with a message worth showing
     */
    public function snapshot(string $page, string $section, string $origin): array
    {
        if (!$this->sectionsAvailable()) {
            throw new \RuntimeException('Design variations need Codex and Playwright on this machine.');
        }

        if (!$document = $this->snapshots->document($page, $section)) {
            throw new \RuntimeException('That section is no longer on the page.');
        }

        $this->prune();

        $job = [
            'id' => (string) Str::uuid(),
            'kind' => 'section',
            'source' => 'source.png',
            'name' => $document['title'],
            'ref' => $document['ref'],
            'status' => 'snapshot',
            'created_at' => now()->toIso8601String(),
        ];

        File::makeDirectory($this->dir($job['id']), 0755, true);

        try {
            $this->snapshots->capture($document['html'], $origin, $this->dir($job['id']) . '/' . $job['source']);
        } catch (\RuntimeException $e) {
            File::deleteDirectory($this->dir($job['id']));

            throw $e;
        }

        $this->save($job);

        return $job;
    }

    /** Record a pending job for a section's snapshot. */
    public function startSection(string $snapshot, string $prompt, int $count): array
    {
        $from = $this->find($snapshot);

        if (!$from || ($from['status'] ?? '') !== 'snapshot' || !is_file($this->dir($snapshot) . '/' . $from['source'])) {
            throw new \RuntimeException('The picture of this section has expired. Close this and open it again.');
        }

        return $this->create('section', $this->dir($snapshot) . '/' . $from['source'], $from['name'], $prompt, $count);
    }

    protected function create(string $kind, string $source, string $name, string $prompt, int $count): array
    {
        if (!$this->available()) {
            throw new \RuntimeException('Codex is not installed on this machine.');
        }

        $this->prune();

        $job = [
            'id' => (string) Str::uuid(),
            'kind' => $kind,
            'source' => 'source.' . strtolower(pathinfo($source, PATHINFO_EXTENSION)),
            'name' => $name,
            'prompt' => trim($prompt) !== '' ? trim($prompt) : ($kind === 'section' ? self::DEFAULT_SECTION_PROMPT : self::DEFAULT_PROMPT),
            'count' => max(1, min(self::MAX, $count)),
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ];

        File::makeDirectory($this->dir($job['id']), 0755, true);
        File::copy($source, $this->dir($job['id']) . '/' . $job['source']);
        $this->save($job);

        return $job;
    }

    /**
     * Run a pending job to completion, emitting events as they happen:
     * `activity` {label}, `image` {file}, `ping` while it waits, then `done` {images}
     * or `error` {message, images}.
     *
     * @param  callable(string $event, array $payload): void  $emit
     */
    public function run(string $id, callable $emit): void
    {
        $job = $this->find($id);

        if (!$job || $job['status'] !== 'pending') {
            $emit('error', ['message' => 'That request has already run.', 'images' => []]);

            return;
        }

        $job['status'] = 'running';
        $this->save($job);

        // Drawing takes minutes: see TurnRunner::run()
        @set_time_limit(0);
        ignore_user_abort(true);

        $dir = $this->dir($id);
        $seen = [];
        $error = null;
        $buffer = '';
        $stderr = '';

        $thread = null;
        $collected = [];

        $report = function () use ($dir, $job, &$seen, &$thread, &$collected, $emit): void {
            $this->collect($thread, $dir, $job, $collected);

            foreach ($this->images($dir, $job) as $file) {
                if (!in_array($file, $seen, true)) {
                    $seen[] = $file;
                    $emit('image', ['file' => $file]);
                }
            }
        };

        try {
            $process = new Process($this->command($job, $dir), $dir, $this->environment());
            $process->setTimeout((float) config('studio.assistant.timeout', 900));
            $process->setIdleTimeout(null);
            $process->start();

            $emit('activity', ['label' => ($job['kind'] ?? 'image') === 'section' ? 'Looking at the section…' : 'Looking at the image…']);

            while ($process->isRunning()) {
                $buffer .= $process->getIncrementalOutput();
                $stderr .= $process->getIncrementalErrorOutput();

                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $newline));
                    $buffer = substr($buffer, $newline + 1);

                    $event = json_decode($line, true);

                    if (!is_array($event)) {
                        continue;
                    }

                    if (($event['type'] ?? '') === 'thread.started' && preg_match('/^[A-Za-z0-9-]+$/', (string) ($event['thread_id'] ?? ''))) {
                        $thread = $event['thread_id'];
                    }

                    if ($label = $this->activity($event)) {
                        $emit('activity', ['label' => $label]);
                    }
                }

                $report();

                if (count($seen) >= $job['count']) {
                    // Everything asked for is on disk; the closing remarks are not worth the wait
                    $process->stop(2);
                    break;
                }

                if (connection_aborted() || File::exists($dir . '/.stop')) {
                    $process->stop(2);
                    $error = 'Stopped.';
                    break;
                }

                $process->checkTimeout();
                usleep(300000);
                // Output is what lets PHP notice a closed connection
                $emit('ping', []);
            }

            $stderr .= $process->getIncrementalErrorOutput();
            $report();

            if (!$seen && $error === null) {
                $error = $this->failure($stderr);
            }
        } catch (\Throwable $e) {
            $report();
            $error = $seen ? null : $e->getMessage();
        }

        $job['status'] = $error ? 'failed' : 'done';
        $job['images'] = $seen;
        $this->save($job);

        $error
            ? $emit('error', ['message' => $error, 'images' => $seen])
            : $emit('done', ['images' => $seen]);
    }

    public function stop(string $id): void
    {
        if ($this->find($id)) {
            File::put($this->dir($id) . '/.stop', '1');
        }
    }

    /** The path of a job's image — one it drew, or the one it started from — for serving it. */
    public function path(string $id, string $file): ?string
    {
        $job = $this->find($id);

        if (!$job) {
            return null;
        }

        $known = $file === $job['source']
            ? is_file($this->dir($id) . '/' . $file)
            : in_array($file, $this->images($this->dir($id), $job + ['count' => self::MAX]), true);

        return $known ? $this->dir($id) . '/' . $file : null;
    }

    /** Move one result into the media library; returns its URL. */
    public function keep(string $id, string $file): ?string
    {
        if (!($path = $this->path($id, $file))) {
            return null;
        }

        $job = $this->find($id);

        // A section's concepts are briefs for the Assistant, never site images
        if (($job['kind'] ?? 'image') !== 'image') {
            return null;
        }

        $directory = SitePaths::public(SitePaths::UPLOADS);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $name = Str::slug($job['name'] ?? 'image') ?: 'image';
        $filename = $name . '-variation-' . Str::lower(Str::random(6)) . '.' . strtolower(pathinfo($file, PATHINFO_EXTENSION));

        File::copy($path, $directory . '/' . $filename);

        return SitePaths::url(SitePaths::UPLOADS . '/' . $filename);
    }

    /** The results in a job's folder, in order: complete image files other than the source. */
    protected function images(string $dir, array $job): array
    {
        $files = [];

        foreach (File::glob($dir . '/*') as $path) {
            $file = basename($path);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if ($file === $job['source'] || !in_array($extension, self::EXTENSIONS, true) || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
                continue;
            }

            // A file still being written has no readable dimensions yet
            if (is_file($path) && filesize($path) > 0 && @getimagesize($path)) {
                $files[] = $file;
            }
        }

        natsort($files);

        return array_slice(array_values($files), 0, $job['count']);
    }

    protected function command(array $job, string $dir): array
    {
        $bin = $this->engines->available()[Engines::CODEX]['bin'];

        $argv = [
            $bin, 'exec', '--json', '--skip-git-repo-check',
            '-C', $dir,
            '--sandbox', 'workspace-write',
            '-c', 'approval_policy="never"',
            '--enable', 'image_generation',
            '-i', $dir . '/' . $job['source'],
        ];

        if ($model = config('studio.assistant.engines.codex.model')) {
            array_push($argv, '--model', $model);
        }

        // `-i` takes any number of files, so the prompt goes after `--`
        array_push($argv, '--', $this->prompt($job));

        return $argv;
    }

    protected function prompt(array $job): string
    {
        $names = implode(', ', array_map(fn ($n) => "variation-{$n}.png", range(1, $job['count'])));

        if (($job['kind'] ?? 'image') === 'section') {
            return implode("\n", [
                "You are a web designer exploring new directions for one section of a website. The attached image, `{$job['source']}` in the current folder, is a screenshot of that section (\"{$job['name']}\") as it looks now, " . SectionSnapshot::WIDTH . 'px wide.',
                '',
                "Use your image generation tool to make exactly {$job['count']} " . Str::plural('mockup', $job['count']) . ' of the same section, redesigned. What the user asked for:',
                '',
                '"""',
                $job['prompt'],
                '"""',
                '',
                'Rules:',
                '- Each mockup is a distinct design direction: a different layout, composition or visual treatment — not the original with small changes, and not the same idea twice.',
                '- Unless the request says otherwise, keep what the section says and shows: the same headings, copy, buttons, logos and pictures, and the same brand — its colours, typefaces and tone — so the redesign still belongs on this site.',
                '- Each one is a flat, straight-on screenshot of the section alone, edge to edge, in the same landscape proportions as the original: no browser window, no device, no perspective, no annotations or labels, no surrounding page.',
                '- Keep it buildable in HTML and CSS: real text set in type, plain shapes, a sensible grid. Text must be legible and spelled as in the original.',
                "- Generate them one at a time, and save each to the current folder as soon as it is made, as: {$names}.",
                '- Write nothing else: no other files, no edits to the source, no code.',
                '- Do not ask questions. When all are saved, reply with one short line.',
            ]);
        }

        return implode("\n", [
            "You are making image variations for a website's media library. The attached image is `{$job['source']}` in the current folder.",
            '',
            "Use your image generation tool to make exactly {$job['count']} new " . Str::plural('image', $job['count']) . ' based on it. What the user asked for:',
            '',
            '"""',
            $job['prompt'],
            '"""',
            '',
            'Rules:',
            '- Each image is a distinct take, not a copy. Unless the request says otherwise, keep the subject, purpose and aspect ratio of the original so any of them could replace it on the page.',
            "- Generate them one at a time, and save each to the current folder as soon as it is made, as: {$names}.",
            '- Write nothing else: no other files, no edits to the source, no code.',
            '- Do not ask questions. When all are saved, reply with one short line.',
        ]);
    }

    /**
     * Codex draws into its own folder (~/.codex/generated_images/<thread>/)
     * and only copies the results over at the end, all at once. Picking each
     * one up from there as it is drawn is what lets the picker fill in one
     * image at a time — and means a result is never lost to a missed copy.
     *
     * @param  array<string, string>  $collected  drawn file => the name it was saved under
     */
    protected function collect(?string $thread, string $dir, array $job, array &$collected): void
    {
        if ($thread === null) {
            return;
        }

        $drawn = File::glob($this->environment()['HOME'] . '/.codex/generated_images/' . $thread . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
        usort($drawn, fn ($a, $b) => filemtime($a) <=> filemtime($b));

        foreach ($drawn as $path) {
            if (isset($collected[$path]) || count($collected) >= $job['count'] || !@getimagesize($path)) {
                continue;
            }

            $name = 'variation-' . (count($collected) + 1) . '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (@File::copy($path, $dir . '/' . $name)) {
                $collected[$path] = $name;
            }
        }
    }

    /** One line of what Codex is doing, from an event of its JSON stream. */
    protected function activity(array $event): ?string
    {
        $item = $event['item'] ?? null;

        if (!is_array($item)) {
            return ($event['type'] ?? '') === 'turn.started' ? 'Drawing — this takes a minute or two…' : null;
        }

        return match ($item['type'] ?? '') {
            'reasoning' => 'Thinking it through…',
            'command_execution' => str_contains((string) ($item['command'] ?? ''), 'generated_images') ? 'Saving the images…' : null,
            default => null,
        };
    }

    protected function failure(string $stderr): string
    {
        $stderr = trim(preg_replace('/\s+/', ' ', $stderr));

        if (preg_match('/(not logged in|login|unauthorized|401)/i', $stderr)) {
            return 'Codex is not signed in. Run `codex` in a terminal once to log in, then try again.';
        }

        return $stderr !== ''
            ? 'Codex did not return any images: ' . Str::limit($stderr, 240)
            : 'Codex finished without returning any images. Try a more specific prompt.';
    }

    /** Job folders older than a day are thrown away. */
    protected function prune(): void
    {
        foreach (File::directories($this->root()) as $dir) {
            if (preg_match('/^[a-f0-9-]{36}$/', basename($dir)) && File::lastModified($dir) < time() - 86400) {
                File::deleteDirectory($dir);
            }
        }
    }

    /** See TurnRunner::environment() */
    protected function environment(): array
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: (posix_getpwuid(posix_geteuid())['dir'] ?? '/tmp');

        // Installed through a Node version manager, the CLI is a script that
        // needs the `node` sitting beside it
        $bin = dirname((string) ($this->engines->available()[Engines::CODEX]['bin'] ?? ''));

        return [
            'HOME' => $home,
            'PATH' => $bin . ':' . $home . '/.local/bin:' . (getenv('PATH') ?: '') . ':/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin',
            'TERM' => 'dumb',
            'NO_COLOR' => '1',
            'CI' => '1',
        ];
    }
}
