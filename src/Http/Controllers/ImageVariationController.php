<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\Assistant\ImageVariations;
use Designer\Studio\Support\DevMode;
use Designer\Studio\Support\TemplateLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Variations of an image field's picture, drawn by Codex on the machine
 * serving the app — so, like the Assistant, every route 404s unless dev
 * mode is on.
 */
class ImageVariationController extends Controller
{
    public function __construct(
        protected ImageVariations $variations,
    ) {
        abort_unless(DevMode::enabled(), 404);
    }

    /** Start a job; the SSE stream does the work */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|string|max:400',
            'prompt' => 'nullable|string|max:2000',
            'count' => 'nullable|integer|min:1|max:' . ImageVariations::MAX,
        ]);

        try {
            $job = $this->variations->start($data['image'], (string) ($data['prompt'] ?? ''), (int) ($data['count'] ?? 3));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'id' => $job['id'], 'count' => $job['count']]);
    }

    /** Server-sent events for one job: activity, image, done, error */
    public function stream(string $id): StreamedResponse
    {
        abort_unless($this->variations->find($id), 404);

        return response()->stream(function () use ($id) {
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $this->variations->run($id, function (string $event, array $payload): void {
                // A ping is a comment: it keeps the connection warm and lets PHP see a closed one
                echo $event === 'ping'
                    ? ": ping\n\n"
                    : "event: {$event}\ndata: " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function stop(string $id): JsonResponse
    {
        $this->variations->stop($id);

        return response()->json(['success' => true]);
    }

    /** One generated image, for the picker */
    public function image(string $id, string $file)
    {
        abort_unless($path = $this->variations->path($id, $file), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Move the chosen image into the media library and return its URL */
    public function keep(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['file' => 'required|string|max:120']);

        $url = $this->variations->keep($id, $data['file']);

        if (!$url) {
            return response()->json(['success' => false, 'message' => 'That image is no longer available.'], 404);
        }

        app(TemplateLink::class)->touch();

        return response()->json(['success' => true, 'url' => $url]);
    }
}
