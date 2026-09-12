<?php

namespace Designer\Studio\Services\Inline;

/**
 * Weaves canvas sentinels into a section's Blade source.
 *
 * Comments are used rather than wrapper elements so the instrumented render
 * is identical to the plain one once stripped: a comment costs nothing in
 * layout, cascade, or selector matching, so `box-decoration-clone`, flex
 * children, `:first-child` and `+` all behave exactly as they do live.
 */
final class Instrumenter
{
    public function __construct(private readonly EchoScanner $scanner) {}

    public function weave(string $source, array $fields): string
    {
        return $this->apply($source, $this->scanner->scan($source, $fields));
    }

    /** @param list<EchoRef> $refs */
    public function apply(string $source, array $refs): string
    {
        /** @var list<array{0: int, 1: string, 2: bool}> [offset, text, isClosing] */
        $edits = [];
        $attributes = [];   // tagNameEnd => ['src:image@57', …]
        $toggles = [];      // tagNameEnd => 'showRating@31'

        foreach ($refs as $ref) {
            if ($ref->context === 'text') {
                $edits[] = [$ref->offset, '<!--sf:' . $ref->path . '@' . $ref->line . '-->', false];
                $edits[] = [(int) $ref->end, '<!--/sf-->', true];

                continue;
            }

            if ($ref->context === 'attr') {
                $attributes[$ref->offset][] = $ref->attribute . ':' . $ref->key . '@' . $ref->line;

                continue;
            }

            $toggles[$ref->offset] = $ref->key . '@' . $ref->line;
        }

        foreach ($attributes as $offset => $pairs) {
            $edits[] = [$offset, ' data-sf-attr="' . e(implode(';', $pairs)) . '"', false];
        }

        foreach ($toggles as $offset => $value) {
            $edits[] = [$offset, ' data-sf-when="' . e($value) . '"', false];
        }

        // Descending offset keeps every remaining offset valid. At the same
        // offset an insertion lands *before* one already made there, so the
        // opener is applied first to end up after the closer — which is what
        // two adjacent echoes need: …<!--/sf--><!--sf:next@12-->…
        usort($edits, fn (array $a, array $b) => $b[0] <=> $a[0] ?: ($a[2] <=> $b[2]));

        foreach ($edits as [$offset, $text]) {
            $source = substr($source, 0, $offset) . $text . substr($source, $offset);
        }

        return $source;
    }

    /**
     * Remove every sentinel from rendered HTML.
     *
     * Only the verify command uses this — production never strips, because
     * production never instruments.
     */
    public function strip(string $html): string
    {
        $html = (string) preg_replace('/<!--sf:[^>]*?-->|<!--\/sf-->/', '', $html);

        return (string) preg_replace('/ data-sf-(?:attr|when)="[^"]*"/', '', $html);
    }
}
