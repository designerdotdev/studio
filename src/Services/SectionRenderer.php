<?php

namespace Designer\Studio\Services;

use Designer\Studio\DataTransferObjects\ComponentData;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\SiteRepository;
use Designer\Studio\Support\DataBag;
use Illuminate\Support\Facades\Blade;

/**
 * The one place a section turns into HTML.
 *
 * Studio used to render sections three ways — real Blade on the server, a
 * hand-written JavaScript subset for live canvas edits, and a static
 * evaluator for Blade exports — which meant a section could only use the
 * syntax all three understood. Every path now comes through here, so a
 * section may use anything Laravel's own compiler accepts: `@props`,
 * `$loop`, comparisons, nested conditionals, `@php`, and nested
 * `<x-…>` components.
 *
 * Two things are added to the variables on the way in:
 *
 *  - arrays become {@see DataBag}s, so `$item['title']` and `$item->title`
 *    both resolve (Studio's own sections use the first, the site-templates
 *    repos use the second);
 *  - `$site` is injected from the site document, which is how an imported
 *    template's sections reach shared content like the company name.
 */
class SectionRenderer
{
    public function __construct(
        protected ComponentRepository $components,
        protected SiteRepository $site,
        protected CollectionBinder $binder,
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
    public function render(ComponentData $component, array $variables, array $bindings = []): string
    {
        try {
            return Blade::render($component->html, $this->context($this->binder->apply($variables, $bindings)));
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($component->name, $e->getMessage());
        }
    }

    /** Render raw Blade with the same context (previews, ad-hoc markup). */
    public function renderHtml(string $html, array $variables, string $label = 'section'): string
    {
        try {
            return Blade::render($html, $this->context($variables));
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($label, $e->getMessage());
        }
    }

    /**
     * The variable set a section actually sees: its own fields (arrays
     * wrapped for dual access) plus `$site`, unless the section declares a
     * field of its own by that name.
     */
    public function context(array $variables): array
    {
        $context = [];

        foreach ($variables as $key => $value) {
            $context[$key] = DataBag::wrap($value);
        }

        if (!array_key_exists('site', $context)) {
            $context['site'] = new DataBag($this->site->data());
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
