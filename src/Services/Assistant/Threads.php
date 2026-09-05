<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Assistant conversations, one JSON file each under
 * storage/studio/assistant/threads/. Not workspaced — a conversation is
 * about the site, not part of it — and never published.
 *
 * Thread shape:
 *   { id, title, engine, session_id, created_at, updated_at,
 *     messages: [{ id, role: user|assistant, text, activity: [{kind,label,path}], files: [path], at, failed? }] }
 */
class Threads
{
    public function __construct(
        protected StudioStorage $storage
    ) {}

    protected function dir(): string
    {
        $dir = $this->storage->getBasePath() . '/assistant/threads';

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    protected function path(string $id): string
    {
        return $this->dir() . '/' . $id . '.json';
    }

    /** Newest first, without messages (for the history menu) */
    public function all(): array
    {
        $threads = [];

        foreach (File::glob($this->dir() . '/*.json') as $file) {
            $thread = json_decode(File::get($file), true);

            if (!is_array($thread) || empty($thread['id'])) {
                continue;
            }

            $threads[] = [
                'id' => $thread['id'],
                'title' => $thread['title'] ?? 'New conversation',
                'engine' => $thread['engine'] ?? null,
                'updated_at' => $thread['updated_at'] ?? $thread['created_at'] ?? '',
                'count' => count($thread['messages'] ?? []),
            ];
        }

        usort($threads, fn ($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return $threads;
    }

    public function find(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/', $id) || !File::exists($this->path($id))) {
            return null;
        }

        $thread = json_decode(File::get($this->path($id)), true);

        return is_array($thread) ? $thread : null;
    }

    public function create(string $engine): array
    {
        $thread = [
            'id' => (string) Str::uuid(),
            'title' => 'New conversation',
            'engine' => $engine,
            'session_id' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'messages' => [],
        ];

        $this->write($thread);

        return $thread;
    }

    public function rename(string $id, string $title): void
    {
        if ($thread = $this->find($id)) {
            $thread['title'] = trim($title) !== '' ? Str::limit(trim($title), 80, '') : $thread['title'];
            $this->write($thread);
        }
    }

    public function setEngine(string $id, string $engine): void
    {
        if ($thread = $this->find($id)) {
            // A new engine cannot resume the old one's session
            if (($thread['engine'] ?? null) !== $engine) {
                $thread['session_id'] = null;
            }

            $thread['engine'] = $engine;
            $this->write($thread);
        }
    }

    public function setSession(string $id, ?string $sessionId): void
    {
        if ($thread = $this->find($id)) {
            $thread['session_id'] = $sessionId;
            $this->write($thread);
        }
    }

    public function delete(string $id): void
    {
        if ($this->find($id)) {
            File::delete($this->path($id));
        }
    }

    /** Append a message; the first user message titles the thread */
    public function append(string $id, array $message): ?array
    {
        $thread = $this->find($id);

        if (!$thread) {
            return null;
        }

        $message = [
            'id' => $message['id'] ?? (string) Str::uuid(),
            'role' => $message['role'] ?? 'assistant',
            'text' => (string) ($message['text'] ?? ''),
            'activity' => array_values($message['activity'] ?? []),
            'files' => array_values(array_unique($message['files'] ?? [])),
            'context' => $message['context'] ?? null,
            'failed' => (bool) ($message['failed'] ?? false),
            'at' => now()->toIso8601String(),
        ];

        $thread['messages'][] = $message;

        if ($message['role'] === 'user' && ($thread['title'] ?? '') === 'New conversation') {
            $thread['title'] = Str::limit(trim(preg_replace('/\s+/', ' ', $message['text'])), 60);
        }

        $this->write($thread);

        return $message;
    }

    /** Replace a message in place (used when a streamed reply completes) */
    public function replace(string $id, string $messageId, array $changes): void
    {
        $thread = $this->find($id);

        if (!$thread) {
            return;
        }

        foreach ($thread['messages'] as &$message) {
            if ($message['id'] === $messageId) {
                $message = array_merge($message, $changes);
            }
        }
        unset($message);

        $this->write($thread);
    }

    protected function write(array $thread): void
    {
        $thread['updated_at'] = now()->toIso8601String();

        File::put($this->path($thread['id']), json_encode($thread, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
