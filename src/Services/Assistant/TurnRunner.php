<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * One assistant turn: spawn the engine's CLI for a prompt, translate its
 * JSON-line output into a small set of events, and record the result on
 * the thread.
 *
 * Events handed to the emitter (name, payload):
 *   text     {delta}                                  streamed reply text
 *   activity {kind, label, path?}                     a tool the CLI is using
 *   files    {paths}                                  files touched so far
 *   done     {text, session, files, usage}            turn finished
 *   error    {message}
 *
 * A turn is a small JSON file under storage/studio/assistant/turns/ so the
 * SSE request (which does the actual work) can pick it up, and a stop flag
 * beside it lets a DELETE request end the process.
 */
class TurnRunner
{
    public function __construct(
        protected StudioStorage $storage,
        protected Engines $engines,
        protected Threads $threads,
        protected SystemPrompt $systemPrompt,
    ) {}

    /* ------------------------------------------------------------ */
    /*  Turn files                                                   */
    /* ------------------------------------------------------------ */

    protected function dir(): string
    {
        $dir = $this->storage->getBasePath() . '/assistant/turns';

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    public function find(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/', $id)) {
            return null;
        }

        $path = $this->dir() . '/' . $id . '.json';

        if (!File::exists($path)) {
            return null;
        }

        $turn = json_decode(File::get($path), true);

        return is_array($turn) ? $turn : null;
    }

    protected function save(array $turn): void
    {
        File::put($this->dir() . '/' . $turn['id'] . '.json', json_encode($turn, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Record a pending turn and the user's message on its thread.
     *
     * @param  array{thread: string, engine: string, prompt: string, context?: array}  $input
     */
    public function start(array $input): array
    {
        $thread = $this->threads->find($input['thread']);

        if (!$thread) {
            throw new \RuntimeException('That conversation no longer exists.');
        }

        $engine = $input['engine'] ?: ($thread['engine'] ?? $this->engines->default());

        if (!$engine || !$this->engines->isAvailable($engine)) {
            throw new \RuntimeException('No assistant engine is installed on this machine.');
        }

        $this->threads->setEngine($thread['id'], $engine);

        $this->threads->append($thread['id'], [
            'role' => 'user',
            'text' => $input['prompt'],
            'context' => $input['context'] ?? null,
        ]);

        // Prune old turn files so the folder never grows unbounded
        foreach (File::glob($this->dir() . '/*.json') as $file) {
            if (File::lastModified($file) < time() - 86400) {
                File::delete($file);
            }
        }

        $turn = [
            'id' => (string) Str::uuid(),
            'thread' => $thread['id'],
            'engine' => $engine,
            'prompt' => $input['prompt'],
            'context' => $input['context'] ?? [],
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ];

        $this->save($turn);

        return $turn;
    }

    public function stop(string $id): void
    {
        if ($this->find($id)) {
            File::put($this->dir() . '/' . $id . '.stop', '1');
        }
    }

    protected function stopped(string $id): bool
    {
        return File::exists($this->dir() . '/' . $id . '.stop');
    }

    /* ------------------------------------------------------------ */
    /*  Running                                                      */
    /* ------------------------------------------------------------ */

    /**
     * Run a pending turn to completion, emitting events as they happen.
     *
     * @param  callable(string $event, array $payload): void  $emit
     */
    public function run(string $id, callable $emit): void
    {
        $turn = $this->find($id);

        if (!$turn || $turn['status'] !== 'pending') {
            $emit('error', ['message' => 'That turn has already run.']);

            return;
        }

        $turn['status'] = 'running';
        $this->save($turn);

        $thread = $this->threads->find($turn['thread']);
        $session = $thread['session_id'] ?? null;

        try {
            $argv = $this->engines->command(
                $turn['engine'],
                $turn['prompt'],
                $session,
                $this->systemPrompt->build($turn['context'] ?? [])
            );
        } catch (\Throwable $e) {
            $this->finish($turn, $emit, ['error' => $e->getMessage()]);

            return;
        }

        $process = new Process($argv, base_path(), $this->environment());
        $process->setTimeout((float) config('studio.assistant.timeout', 900));
        $process->setIdleTimeout(null);

        $state = [
            'text' => '',            // the reply as streamed so far
            'deltaBuffer' => '',     // deltas since the last complete assistant message
            'activity' => [],
            'files' => [],
            'session' => $session,
            'usage' => [],
            'result' => null,
            'error' => null,
        ];

        $buffer = '';

        try {
            $process->start();

            foreach ($process as $type => $chunk) {
                if ($type !== Process::OUT) {
                    $state['stderr'] = ($state['stderr'] ?? '') . $chunk;

                    continue;
                }

                $buffer .= $chunk;

                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $newline));
                    $buffer = substr($buffer, $newline + 1);

                    if ($line !== '') {
                        $this->handleLine($turn['engine'], $line, $state, $emit);
                    }
                }

                if (connection_aborted() || $this->stopped($id)) {
                    $process->stop(2);
                    $state['error'] = $state['error'] ?? 'Stopped.';
                    break;
                }
            }

            if (trim($buffer) !== '') {
                $this->handleLine($turn['engine'], trim($buffer), $state, $emit);
            }

            if ($state['result'] === null && $state['error'] === null && !$process->isSuccessful()) {
                $state['error'] = $this->failureMessage($process, $state);
            }
        } catch (\Throwable $e) {
            $state['error'] = $e->getMessage();
        }

        $this->finish($turn, $emit, $state);
    }

    /** Finish: persist the assistant message + session, emit done/error */
    protected function finish(array $turn, callable $emit, array $state): void
    {
        $text = $state['result'] ?? $state['text'] ?? '';
        $error = $state['error'] ?? null;

        if (($state['session'] ?? null) && $turn['thread']) {
            $this->threads->setSession($turn['thread'], $state['session']);
        }

        $this->threads->append($turn['thread'], [
            'role' => 'assistant',
            'text' => $error && $text === '' ? $error : $text,
            'activity' => $state['activity'] ?? [],
            'files' => $state['files'] ?? [],
            'failed' => $error !== null,
        ]);

        $turn['status'] = $error ? 'failed' : 'done';
        $this->save($turn);
        File::delete($this->dir() . '/' . $turn['id'] . '.stop');

        if ($error) {
            $emit('error', ['message' => $error, 'text' => $text, 'files' => $state['files'] ?? []]);

            return;
        }

        $emit('done', [
            'text' => $text,
            'session' => $state['session'] ?? null,
            'files' => $state['files'] ?? [],
            'usage' => $state['usage'] ?? [],
        ]);
    }

    /* ------------------------------------------------------------ */
    /*  Event translation                                            */
    /* ------------------------------------------------------------ */

    protected function handleLine(string $engine, string $line, array &$state, callable $emit): void
    {
        $event = json_decode($line, true);

        if (!is_array($event)) {
            return;
        }

        $engine === Engines::CODEX
            ? $this->handleCodex($event, $state, $emit)
            : $this->handleClaude($event, $state, $emit);
    }

    protected function handleClaude(array $event, array &$state, callable $emit): void
    {
        $type = $event['type'] ?? '';

        if ($type === 'system' && ($event['subtype'] ?? '') === 'init') {
            $state['session'] = $event['session_id'] ?? $state['session'];

            return;
        }

        if ($type === 'stream_event') {
            $inner = $event['event'] ?? [];

            if (($inner['type'] ?? '') === 'content_block_delta' && ($inner['delta']['type'] ?? '') === 'text_delta') {
                $delta = (string) ($inner['delta']['text'] ?? '');
                $state['text'] .= $delta;
                $state['deltaBuffer'] .= $delta;
                $emit('text', ['delta' => $delta]);
            }

            if (($inner['type'] ?? '') === 'content_block_start' && ($inner['content_block']['type'] ?? '') === 'tool_use') {
                // The full input arrives with the assistant message; announce early
                $emit('activity', ['kind' => 'working', 'label' => 'Using ' . ($inner['content_block']['name'] ?? 'a tool') . '…']);
            }

            return;
        }

        if ($type === 'assistant') {
            foreach ($event['message']['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text = (string) ($block['text'] ?? '');

                    // Emit whatever the deltas did not already cover
                    if ($text !== '' && !str_ends_with($state['deltaBuffer'], $text)) {
                        $missing = str_starts_with($text, $state['deltaBuffer']) ? substr($text, strlen($state['deltaBuffer'])) : "\n" . $text;
                        $state['text'] .= $missing;
                        $emit('text', ['delta' => $missing]);
                    }

                    $state['deltaBuffer'] = '';
                }

                if (($block['type'] ?? '') === 'tool_use') {
                    $activity = $this->describeClaudeTool((string) ($block['name'] ?? ''), (array) ($block['input'] ?? []));
                    $state['activity'][] = $activity;
                    $emit('activity', $activity);

                    if (!empty($activity['path']) && in_array($activity['kind'], ['edit', 'write'], true)) {
                        $state['files'] = array_values(array_unique([...$state['files'], $activity['path']]));
                        $emit('files', ['paths' => $state['files']]);
                    }
                }
            }

            return;
        }

        if ($type === 'result') {
            $state['session'] = $event['session_id'] ?? $state['session'];
            $state['usage'] = array_filter([
                'cost_usd' => $event['total_cost_usd'] ?? null,
                'duration_ms' => $event['duration_ms'] ?? null,
                'turns' => $event['num_turns'] ?? null,
            ], fn ($v) => $v !== null);

            if (!empty($event['is_error'])) {
                $state['error'] = (string) ($event['result'] ?? 'The assistant reported an error.');

                return;
            }

            $state['result'] = (string) ($event['result'] ?? $state['text']);
        }
    }

    protected function describeClaudeTool(string $name, array $input): array
    {
        $path = $input['file_path'] ?? $input['path'] ?? $input['notebook_path'] ?? null;
        $path = is_string($path) ? $this->relative($path) : null;

        return match ($name) {
            'Read' => ['kind' => 'read', 'label' => 'Reading ' . $path, 'path' => $path],
            'Edit', 'MultiEdit', 'NotebookEdit' => ['kind' => 'edit', 'label' => 'Editing ' . $path, 'path' => $path],
            'Write' => ['kind' => 'write', 'label' => 'Writing ' . $path, 'path' => $path],
            'Bash' => ['kind' => 'bash', 'label' => 'Running ' . Str::limit((string) ($input['command'] ?? ''), 80), 'path' => null],
            'Grep' => ['kind' => 'search', 'label' => 'Searching for ' . Str::limit((string) ($input['pattern'] ?? ''), 40), 'path' => null],
            'Glob' => ['kind' => 'search', 'label' => 'Finding ' . Str::limit((string) ($input['pattern'] ?? ''), 40), 'path' => null],
            'LS' => ['kind' => 'read', 'label' => 'Listing ' . ($path ?? '.'), 'path' => $path],
            'TodoWrite', 'Task', 'Agent' => ['kind' => 'other', 'label' => 'Planning', 'path' => null],
            default => ['kind' => 'other', 'label' => $name ?: 'Working', 'path' => $path],
        };
    }

    protected function handleCodex(array $event, array &$state, callable $emit): void
    {
        $type = $event['type'] ?? '';

        if ($type === 'thread.started') {
            $state['session'] = $event['thread_id'] ?? $state['session'];

            return;
        }

        if ($type === 'error') {
            $state['error'] = (string) ($event['message'] ?? 'Codex reported an error.');

            return;
        }

        if ($type === 'item.completed' || $type === 'item.started' || $type === 'item.updated') {
            $item = $event['item'] ?? [];
            $itemType = $item['type'] ?? '';

            if ($itemType === 'agent_message' && $type === 'item.completed') {
                $text = (string) ($item['text'] ?? '');
                $delta = $state['text'] === '' ? $text : "\n\n" . $text;
                $state['text'] .= $delta;
                $emit('text', ['delta' => $delta]);
            }

            if ($itemType === 'command_execution' && $type === 'item.started') {
                $activity = ['kind' => 'bash', 'label' => 'Running ' . Str::limit((string) ($item['command'] ?? ''), 80), 'path' => null];
                $state['activity'][] = $activity;
                $emit('activity', $activity);
            }

            if ($itemType === 'file_change' && $type === 'item.completed') {
                foreach ($item['changes'] ?? [] as $change) {
                    $path = $this->relative((string) ($change['path'] ?? ''));
                    $kind = ($change['kind'] ?? 'update') === 'add' ? 'write' : 'edit';
                    $activity = ['kind' => $kind, 'label' => ($kind === 'write' ? 'Writing ' : 'Editing ') . $path, 'path' => $path];
                    $state['activity'][] = $activity;
                    $emit('activity', $activity);
                    $state['files'] = array_values(array_unique([...$state['files'], $path]));
                }

                $emit('files', ['paths' => $state['files']]);
            }

            if ($itemType === 'reasoning' && $type === 'item.completed') {
                $emit('activity', ['kind' => 'other', 'label' => 'Thinking', 'path' => null]);
            }

            return;
        }

        if ($type === 'turn.completed') {
            $usage = $event['usage'] ?? [];
            $state['usage'] = array_filter([
                'input_tokens' => $usage['input_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ], fn ($v) => $v !== null);
            $state['result'] = $state['text'];
        }

        if ($type === 'turn.failed') {
            $state['error'] = (string) ($event['error']['message'] ?? 'The turn failed.');
        }
    }

    /* ------------------------------------------------------------ */

    protected function environment(): array
    {
        // The CLIs need the user's HOME (login, settings) and PATH; PHP-FPM
        // often runs without either. Never inherit a nested-session marker.
        $env = [
            'HOME' => $_SERVER['HOME'] ?? getenv('HOME') ?: (posix_getpwuid(posix_geteuid())['dir'] ?? '/tmp'),
            'PATH' => (getenv('PATH') ?: '') . ':/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin',
            'TERM' => 'dumb',
            'NO_COLOR' => '1',
            'CI' => '1',
        ];

        if (!str_contains($env['PATH'], $env['HOME'] . '/.local/bin')) {
            $env['PATH'] = $env['HOME'] . '/.local/bin:' . $env['PATH'];
        }

        return $env;
    }

    protected function failureMessage(Process $process, array $state): string
    {
        $stderr = trim((string) ($state['stderr'] ?? $process->getErrorOutput()));

        if ($stderr !== '') {
            return Str::limit(preg_replace('/\s+/', ' ', $stderr), 400);
        }

        return 'The assistant exited with code ' . $process->getExitCode() . '.';
    }

    protected function relative(string $path): string
    {
        $base = rtrim(base_path(), '/') . '/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
