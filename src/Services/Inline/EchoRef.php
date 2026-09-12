<?php

namespace Designer\Studio\Services\Inline;

/**
 * One echo of a declared field, located in a section's Blade source.
 *
 * `offset` means different things per context, because that is where the
 * Instrumenter writes: for `text` it is where the opening sentinel goes
 * (and `end` where the closing one goes); for `attr` and `when` it is the
 * offset just past the tag's name, where an attribute can be added.
 */
final class EchoRef
{
    public function __construct(
        /** The declared field key, e.g. `headingStart` or `people`. */
        public readonly string $key,
        /** Sentinel path — a repeater echo carries a live `{{ $loop->index }}`. */
        public readonly string $path,
        /** `text` | `attr` | `when` | `undeclared` */
        public readonly string $context,
        public readonly int $offset,
        public readonly ?int $end,
        /** 1-based line in the section source, for the provenance chip. */
        public readonly int $line,
        /** Attribute name — `attr` context only. */
        public readonly ?string $attribute = null,
    ) {}
}
