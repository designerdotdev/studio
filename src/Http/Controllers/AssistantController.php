<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\Assistant\Engines;
use Designer\Studio\Services\Assistant\Threads;
use Designer\Studio\Services\Assistant\TurnRunner;
use Designer\Studio\Support\DevMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Assistant's turn endpoints. Every route 404s unless dev mode is on:
 * the assistant runs a CLI on the machine serving the app, which only
 * makes sense for a developer's own environment.
 */
class AssistantController extends Controller
{
    public function __construct(
        protected Engines $engines,
        protected Threads $threads,
        protected TurnRunner $runner,
    ) {
        abort_unless(DevMode::enabled(), 404);

        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    /** Engines and whether each is installed */
    public function engines(): JsonResponse
    {
        return response()->json([
            'engines' => $this->engines->available(),
            'default' => $this->engines->default(),
        ]);
    }

    /** Start a turn; the SSE stream does the work */
    public function turn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'thread' => 'required|string|size:36',
            'engine' => 'nullable|string|in:claude,codex',
            'prompt' => 'required|string|max:20000',
            'context' => 'nullable|array',
            'context.page' => 'nullable|string|max:120',
            'context.section' => 'nullable|array',
            'context.element' => 'nullable|array',
        ]);

        try {
            $turn = $this->runner->start([
                'thread' => $data['thread'],
                'engine' => $data['engine'] ?? null,
                'prompt' => $data['prompt'],
                'context' => $data['context'] ?? [],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'turn' => $turn['id']]);
    }

    /** Server-sent events for one turn */
    public function stream(string $turn): StreamedResponse
    {
        abort_unless($this->runner->find($turn), 404);

        return response()->stream(function () use ($turn) {
            // Streaming must not be buffered by PHP, the server, or gzip
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $send = function (string $event, array $payload): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
            };

            $send('open', ['turn' => $turn]);

            $this->runner->run($turn, $send);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function stop(string $turn): JsonResponse
    {
        $this->runner->stop($turn);

        return response()->json(['success' => true]);
    }
}
