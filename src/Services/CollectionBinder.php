<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\CollectionRepository;

/**
 * Resolves a section instance's `bindings` — a map of field key to
 * `collections.<name>` — by replacing the bound variable with the
 * collection's current rows. Applied in every render path right before
 * the variables reach Blade, so a collection edited in the Content panel
 * shows up on the canvas, the live page, the draft preview, and exports.
 */
class CollectionBinder
{
    public function __construct(
        protected CollectionRepository $collections
    ) {}

    public function apply(array $variables, array $bindings): array
    {
        foreach ($bindings as $key => $source) {
            $name = self::collectionName($source);

            if ($name === null) {
                continue;
            }

            $doc = $this->collections->find($name);

            if (!$doc) {
                // A binding to a deleted collection renders as an empty list
                // rather than the stale rows stored on the instance.
                $variables[$key] = [];

                continue;
            }

            $variables[$key] = array_values($doc['rows']);
        }

        return $variables;
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
}
