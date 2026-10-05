<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\DataTransferObjects\ComponentData;
use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\SectionRenderer;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Support\DevMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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
 *
 * In dev mode a section may arrive with its `source` — the Blade and YAML
 * the inspector's code panel is holding, not yet saved — and is rendered
 * from that instead of from the library. Such a section either renders or
 * reports why it could not (`errors`); it never paints an error box over
 * the last good render.
 */
class RenderController extends Controller
{
    /** Sections one request may render — a page never has this many. */
    protected const MAX_SECTIONS = 40;

    /** The largest unsaved source a section may be previewed from. */
    protected const MAX_SOURCE = 512 * 1024;

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
            'sections.*.source' => ['sometimes', 'array'],
            'sections.*.source.html' => ['nullable', 'string', 'max:' . self::MAX_SOURCE],
            'sections.*.source.yaml' => ['nullable', 'string', 'max:' . self::MAX_SOURCE],
        ]);

        $html = [];
        $errors = [];

        foreach ($data['sections'] as $section) {
            $component = $this->components->find($section['ref']);

            if (!$component) {
                continue;
            }

            if (isset($section['source']) && DevMode::enabled()) {
                try {
                    $html[$section['id']] = $this->draft($component, $section);
                } catch (ParseException $e) {
                    $errors[$section['id']] = ['file' => 'yaml', 'message' => $e->getMessage(), 'line' => $e->getParsedLine() > 0 ? $e->getParsedLine() : null];
                } catch (\Throwable $e) {
                    $errors[$section['id']] = ['file' => 'html', 'message' => $this->reason($e), 'line' => null];
                }

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
                ),
                instrument: true,
            );
        }

        return response()->json(['html' => $html] + ($errors ? ['errors' => $errors] : []));
    }

    /**
     * One section rendered from unsaved source: the fields come from the
     * YAML as written, the values from the instance over those fields'
     * defaults — what the section will be once the files are saved.
     */
    protected function draft(ComponentData $component, array $section): string
    {
        $meta = Yaml::parse((string) ($section['source']['yaml'] ?? ''));
        $fields = app(DesignSyncService::class)->fields(is_array($meta['fields'] ?? null) ? $meta['fields'] : []);

        $draft = ComponentData::fromArray([
            'id' => $component->id,
            'name' => $component->name,
            'html' => (string) ($section['source']['html'] ?? ''),
            'fields' => $fields,
        ]);

        return $this->renderer->renderDraft(
            $draft->html,
            $draft->fields,
            app(\Designer\Studio\Services\CollectionBinder::class)->apply(
                $draft->resolveVariables($section['variables'] ?? []),
                $section['bindings'] ?? [],
                array_keys($section['variables'] ?? [])
            ),
        );
    }

    /** A render failure in a sentence: Blade wraps the cause in a path to a compiled view nobody asked about. */
    protected function reason(\Throwable $e): string
    {
        $message = ($e->getPrevious() ?? $e)->getMessage();

        return trim((string) preg_replace('/\s*\(View: [^)]*\)/', '', $message)) ?: 'The section could not be rendered.';
    }
}
