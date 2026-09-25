<?php

namespace Designer\Studio\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A read-only view over section data that answers to BOTH access styles.
 *
 * Studio's own sections were authored against arrays (`$item['title']`),
 * while the site-templates repos were authored against objects
 * (`$item->title`). Both are valid Blade, and both now render through the
 * same engine, so the value handed to a section has to satisfy either.
 *
 * Wrapping happens at render time only — stored data, the editor panel, and
 * the JSON on disk all stay plain arrays.
 */
class DataBag implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    public function __construct(protected array $items = []) {}

    /**
     * Wrap arrays (at any depth) so property and array access both work.
     * Scalars pass through untouched.
     */
    public static function wrap(mixed $value): mixed
    {
        return is_array($value) ? new self($value) : $value;
    }

    /** The underlying array, unwrapped. */
    public function all(): array
    {
        return $this->items;
    }

    /* ---- object access ------------------------------------------- */

    public function __get(string $key): mixed
    {
        return self::wrap($this->items[$key] ?? null);
    }

    public function __isset(string $key): bool
    {
        return isset($this->items[$key]);
    }

    /* ---- array access -------------------------------------------- */

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return self::wrap($this->items[$offset] ?? null);
    }

    /** Read-only: sections render data, they never mutate it. */
    public function offsetSet(mixed $offset, mixed $value): void {}

    public function offsetUnset(mixed $offset): void {}

    /* ---- iteration / counting / encoding -------------------------- */

    public function getIterator(): Traversable
    {
        $wrapped = [];

        foreach ($this->items as $key => $value) {
            $wrapped[$key] = self::wrap($value);
        }

        return new ArrayIterator($wrapped);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    /**
     * `{{ $bag }}` should never print "Object"; an empty bag reads as empty
     * so `@if($maybeMissing)` style checks behave like they do on arrays.
     */
    public function __toString(): string
    {
        return $this->items === [] ? '' : json_encode($this->items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
