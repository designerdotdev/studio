<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\SectionRenderer;
use Designer\Studio\Services\Storage\ComponentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Live canvas re-rendering.
 *
 * Editing a field posts the section's current variables here and swaps the
 * returned markup into the canvas. Going through the server means the
 * preview is compiled by the same Blade engine that will serve the real
 * page, so what the canvas shows is what ships.
 *
 * Requests are batched: one global block placed several times on a page
 * re-renders all of its copies in a single round trip.
 */
class RenderController extends Controller
{
    /** Sections one request may render — a page never has this many. */
    protected const MAX_SECTIONS = 40;

    public function __construct(
        protected ComponentRepository $components,
        protected SectionRenderer $renderer,
    ) {
        // The canvas edits the draft site — bound collections must be read
        // from the same workspace the editor writes to.
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:' . self::MAX_SECTIONS],
            'sections.*.id' => ['required', 'string', 'max:64'],
            'sections.*.ref' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'sections.*.variables' => ['sometimes', 'array'],
            'sections.*.bindings' => ['sometimes', 'array'],
            'sections.*.bindings.*' => ['string', 'max:2000'],
        ]);

        $html = [];

        foreach ($data['sections'] as $section) {
            $component = $this->components->find($section['ref']);

            if (!$component) {
                continue;
            }

            // The posted values are what the inspector holds right now — a
            // field bound to site data shows what was just typed, not the
            // copy saved a moment ago.
            $given = array_keys($section['variables'] ?? []);

            $html[$section['id']] = $this->renderer->render(
                $component,
                app(\Designer\Studio\Services\CollectionBinder::class)->apply(
                    $component->resolveVariables($section['variables'] ?? []),
                    $section['bindings'] ?? [],
                    $given
                )
            );
        }

        return response()->json(['html' => $html]);
    }
}
