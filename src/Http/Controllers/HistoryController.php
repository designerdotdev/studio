<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\History;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Undo and redo (Services/History): what each would do, and doing it. The
 * answer tells the editor how much of itself to refresh.
 */
class HistoryController extends Controller
{
    public function __construct(
        protected History $history,
        protected PageRepository $pages,
    ) {
        if (config('studio.draft_mode', true)) {
            app(StudioStorage::class)->useDraft();
        }
    }

    public function state(): JsonResponse
    {
        return response()->json($this->history->state());
    }

    public function undo(Request $request): JsonResponse
    {
        return $this->step($request, 'undo');
    }

    public function redo(Request $request): JsonResponse
    {
        return $this->step($request, 'redo');
    }

    protected function step(Request $request, string $direction): JsonResponse
    {
        $slug = (string) $request->input('page', '');
        $before = $this->identity($slug);
        $pages = $this->pages->all()->pluck('slug')->sort()->values()->all();

        $done = $this->history->{$direction}();

        if ($done === null) {
            return response()->json(['success' => false] + $this->history->state());
        }

        $paths = $done['paths'];

        return response()->json([
            'success' => true,
            'label' => $done['label'],
            // The page being edited is gone, or the things the chrome was
            // built from (the page list, this page's name and layout) changed:
            // the editor loads again rather than patching itself
            'reload' => $this->identity($slug) !== $before
                || $this->pages->all()->pluck('slug')->sort()->values()->all() !== $pages,
            'exists' => $this->pages->find($slug) !== null,
            // Which parts of the editor hold something that changed
            'files' => array_values(array_map(
                fn ($path) => \Designer\Studio\Support\SitePaths::relative(\Designer\Studio\Support\SitePaths::resources(substr($path, 5))),
                array_filter($paths, fn ($path) => str_starts_with($path, 'site/'))
            )),
            'content' => (bool) array_filter($paths, fn ($path) => str_contains($path, '/collections/')),
        ] + $this->history->state());
    }

    /** What the editor's chrome shows of a page: when it changes, a patch will not do. */
    protected function identity(string $slug): ?array
    {
        $page = $slug !== '' ? $this->pages->find($slug) : null;

        return $page ? [$page->title, $page->slug, $page->layout_ref] : null;
    }
}
