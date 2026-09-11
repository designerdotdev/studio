<?php

namespace Designer\Studio\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\Factory;

/**
 * `Blade::render()` for markup rendered while another view is rendering.
 *
 * Studio renders sections, lifted layout chrome, and bound expressions from
 * inside its own views (the canvas iframe, the draft preview), and it
 * catches their failures so one broken section shows an error in place.
 * But Laravel's View::render() answers any exception with flushState(),
 * wiping the component, section and push stacks of the view that is still
 * rendering around it — the outer view then dies on its next `<x-…>` with
 * "Undefined array key". So the render state is taken before the nested
 * render and put back when it fails; the exception is still thrown for the
 * caller to handle.
 */
class NestedBlade
{
    public static function render(string $html, array $data = [], bool $deleteCachedView = false): string
    {
        $factory = app('view');
        $state = self::state($factory);

        try {
            return Blade::render($html, $data, $deleteCachedView);
        } catch (\Throwable $e) {
            self::restore($factory, $state);

            throw $e;
        }
    }

    /** The factory's render bookkeeping: its array and scalar properties. */
    protected static function state(Factory $factory): array
    {
        return (fn () => array_filter(get_object_vars($this), fn ($value) => is_array($value) || is_scalar($value)))
            ->call($factory);
    }

    protected static function restore(Factory $factory, array $state): void
    {
        (function () use ($state) {
            foreach ($state as $property => $value) {
                $this->{$property} = $value;
            }
        })->call($factory);
    }
}
