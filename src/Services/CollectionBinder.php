<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\SiteRepository;
use Designer\Studio\Support\DataBag;
use Designer\Studio\Support\NestedBlade;

/**
 * Resolves a section instance's `bindings` — values its page file takes
 * from somewhere else rather than stating them:
 *
 *   collections.<name>   the collection's rows       :items="$posts"
 *   site.<path>          a key of the site document  :primary="$site->menu_primary"
 *   php:<expression>     any other PHP expression    :count="count($posts)"
 *   blade:<text>         text with Blade echoes      title="Hi {{ $site->name }}"
 *
 * Applied in every render path right before the variables reach Blade, so
 * a collection edited in the Content panel shows up on the canvas and in
 * the draft preview. The last two are the page author's own code: they are
 * evaluated here only to render the canvas, never edited.
 */
class CollectionBinder
{
    public function __construct(
        protected CollectionRepository $collections,
        protected SiteRepository $site,
    ) {}

    /**
     * @param  array<int, string>|null  $given  keys whose values are live edits
     *                                          from the canvas — a site-bound
     *                                          field keeps the value typed
     */
    public function apply(array $variables, array $bindings, ?array $given = null): array
    {
        foreach ($bindings as $key => $source) {
            if (!is_string($source)) {
                continue;
            }

            if (($name = self::collectionName($source)) !== null) {
                $doc = $this->collections->find($name);

                // A binding to a deleted collection renders as an empty list
                // rather than the stale rows stored on the instance.
                $variables[$key] = $doc ? RenderContext::rows($doc) : [];

                continue;
            }

            if (($path = self::sitePath($source)) !== null) {
                if ($given === null || !in_array($key, $given, true)) {
                    $variables[$key] = data_get($this->site->data(), $path);
                }

                continue;
            }

            if (str_starts_with($source, 'php:') || str_starts_with($source, 'blade:')) {
                $variables[$key] = $this->evaluate($source);
            }
        }

        return $variables;
    }

    /** The value a binding currently resolves to (used when unbinding). */
    public function resolve(string $source): mixed
    {
        return $this->apply(['value' => null], ['value' => $source])['value'];
    }

    /** "collections.guides" → "guides"; anything else → null */
    public static function collectionName(mixed $source): ?string
    {
        if (!is_string($source) || !str_starts_with($source, 'collections.')) {
            return null;
        }

        $name = substr($source, strlen('collections.'));

        return preg_match('/^[a-z0-9-]+$/', $name) ? $name : null;
    }

    /** "site.menu_primary" → "menu_primary"; anything else → null */
    public static function sitePath(mixed $source): ?string
    {
        if (!is_string($source) || !str_starts_with($source, 'site.')) {
            return null;
        }

        $path = substr($source, strlen('site.'));

        return preg_match('/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)*$/', $path) ? $path : null;
    }

    /** Whether a binding is code Studio shows but does not edit. */
    public static function isCode(mixed $source): bool
    {
        return is_string($source) && (str_starts_with($source, 'php:') || str_starts_with($source, 'blade:'));
    }

    /**
     * Evaluate a page author's expression with the same variables the site
     * gives it. A failure (a helper Studio's canvas can't provide) renders
     * as nothing rather than breaking the section.
     */
    protected function evaluate(string $source): mixed
    {
        $context = app(RenderContext::class)->globals();

        try {
            if (str_starts_with($source, 'blade:')) {
                return NestedBlade::render(substr($source, 6), $context, deleteCachedView: true);
            }

            $captured = null;
            $context['__studioCapture'] = function ($value) use (&$captured) {
                $captured = $value;
            };

            NestedBlade::render('<?php $__studioCapture(' . substr($source, 4) . '); ?>', $context, deleteCachedView: true);

            return $captured instanceof DataBag ? $captured->all() : $captured;
        } catch (\Throwable) {
            return null;
        }
    }
}
