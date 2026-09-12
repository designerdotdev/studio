<?php

namespace Designer\Studio\Services;

use Designer\Studio\DataTransferObjects\ComponentData;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Support\DataBag;
use Designer\Studio\Support\NestedBlade;

/**
 * The one place a section turns into HTML.
 *
 * Every Studio render path comes through here — the canvas, live edits, the
 * draft preview, and the library thumbnails — and it compiles the section's
 * own Blade source (resources/designer/views/components/…) with Laravel's
 * compiler, so a section may use anything Blade accepts: `@props`, `$loop`,
 * nested conditionals, and nested `<x-…>` components.
 *
 * The variables a section sees match what the site's runtime gives it:
 *
 *  - its own values, with arrays wrapped as {@see DataBag}s so both
 *    `$item['title']` and `$item->title` resolve;
 *  - `$site` and every collection by its file name (`$posts`), shared the
 *    way the runtime shares them — see {@see RenderContext}.
 */
class SectionRenderer
{
    public function __construct(
        protected ComponentRepository $components,
        protected CollectionBinder $binder,
        protected RenderContext $globals,
    ) {}

    /**
     * Render a library section by name with its stored instance variables.
     * Returns null when the component no longer exists.
     */
    public function renderRef(string $ref, array $variables = [], array $bindings = []): ?string
    {
        $component = $this->components->find($ref);

        if (!$component) {
            return null;
        }

        return $this->render($component, $component->resolveVariables($variables), $bindings);
    }

    /**
     * Render a component with an already-resolved variable set. A failure is
     * reported and shown in place so one broken section never blanks a page.
     *
     * `$bindings` is the instance's `{field: "collections.<name>"}` map —
     * bound repeaters take the collection's rows instead of stored values.
     */
    public function render(ComponentData $component, array $variables, array $bindings = [], bool $instrument = false): string
    {
        try {
            return NestedBlade::render(
                $this->source($component->html, $component->fields, $instrument),
                $this->context($this->binder->apply($variables, $bindings))
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($component->name, $e->getMessage());
        }
    }

    /** Render raw Blade with the same context (previews, ad-hoc markup). */
    public function renderHtml(string $html, array $variables, string $label = 'section', array $fields = [], bool $instrument = false): string
    {
        try {
            return NestedBlade::render($this->source($html, $fields, $instrument), $this->context($variables));
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($label, $e->getMessage());
        }
    }

    /**
     * The source to compile. Canvas renders get inline-editing sentinels
     * woven in; every other path gets the section exactly as written.
     *
     * A scanner that trips over an unusual template costs that section its
     * inline editing, never its render — so the failure is reported and the
     * original source is used.
     */
    protected function source(string $html, array $fields, bool $instrument): string
    {
        if (!$instrument || $fields === []) {
            return $html;
        }

        try {
            return app(\Designer\Studio\Services\Inline\Instrumenter::class)->weave($html, $fields);
        } catch (\Throwable $e) {
            report($e);

            return $html;
        }
    }

    /**
     * The variable set a section actually sees: its own values (arrays
     * wrapped for dual access) over the site-wide globals.
     */
    public function context(array $variables): array
    {
        $context = $this->globals->globals();

        foreach ($variables as $key => $value) {
            $context[$key] = DataBag::wrap($value);
        }

        return $context;
    }

    protected function failed(string $ref, string $message): string
    {
        return '<div style="padding:48px 24px;text-align:center;font-family:ui-sans-serif,system-ui,sans-serif;color:#991b1b;background:#fef2f2;border:1px dashed #fecaca;">'
            . 'Section &ldquo;' . e($ref) . '&rdquo; failed to render: ' . e($message)
            . '</div>';
    }
}
