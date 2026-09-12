<?php

namespace Designer\Studio\Services\Inline;

/**
 * Finds every echo of a declared field in a section's Blade source.
 *
 * One left-to-right pass with a three-state HTML machine, so an echo inside
 * an attribute value is told apart from one in element content. Nothing is
 * ever evaluated, and the matching is deliberately conservative: an
 * expression this does not recognise yields no reference at all rather than
 * a wrong one — the field simply stays panel-only.
 */
final class EchoScanner
{
    /** Regions where an echo is not editable markup. */
    private const SKIP = [
        ['{{--', '--}}'],
        ['<!--', '-->'],
        ['@verbatim', '@endverbatim'],
        ['@php', '@endphp'],
    ];

    /**
     * @param array<string, array> $fields the section's yml field contract
     * @return list<EchoRef>
     */
    public function scan(string $source, array $fields): array
    {
        if ($fields === []) {
            return [];
        }

        $refs = [];
        $skips = $this->skipRegions($source);
        $length = strlen($source);
        $at = 0;

        $state = 'TEXT';            // TEXT | TAG | ATTR
        $quote = null;
        $attribute = null;
        $tagNameEnd = null;         // where an attribute may be inserted
        $tagIsComponent = false;    // <x-…> is never annotated

        /** @var list<array{alias: string, field: ?string}> innermost last */
        $loops = [];

        while ($at < $length) {
            if (($jump = $this->skipTo($skips, $at)) !== null) {
                $at = $jump;
                $state = 'TEXT';

                continue;
            }

            $char = $source[$at];

            if ($state === 'TEXT' && $char === '@') {
                if (preg_match('/\G@(?:foreach|forelse)\s*\(\s*\$(\w+)(?:\[[^\]]*\])?\s+as\s+(?:\$\w+\s*=>\s*)?\$(\w+)\s*\)/A', $source, $m, 0, $at)) {
                    $loops[] = ['alias' => $m[2], 'field' => isset($fields[$m[1]]) ? $m[1] : null];
                    $at += strlen($m[0]);

                    continue;
                }

                if (preg_match('/\G@end(?:foreach|forelse)/A', $source, $m, 0, $at)) {
                    array_pop($loops);
                    $at += strlen($m[0]);

                    continue;
                }

                // A toggle governs the element its @if opens — but only when
                // that element follows immediately, so the marker can never
                // drift onto unrelated markup further down the file.
                if (preg_match('/\G@if\s*\(\s*\$(\w+)\s*\)\s*<([a-zA-Z][\w:.-]*)/A', $source, $m, 0, $at)) {
                    if (($fields[$m[1]]['type'] ?? null) === 'toggle' && !str_starts_with($m[2], 'x-')) {
                        $refs[] = new EchoRef(
                            key: $m[1],
                            path: $m[1],
                            context: 'when',
                            offset: $at + strlen($m[0]),
                            end: null,
                            line: $this->lineAt($source, $at),
                        );
                    }

                    // Only the directive is consumed; the tag is scanned normally.
                    $at += strlen($m[0]) - strlen($m[2]) - 1;

                    continue;
                }

                if (substr($source, $at, 3) === '@{{') {
                    $at += 3;

                    continue;
                }
            }

            $raw = substr($source, $at, 3) === '{!!';
            $escaped = !$raw && substr($source, $at, 2) === '{{';

            if ($raw || $escaped) {
                [$open, $close] = $raw ? ['{!!', '!!}'] : ['{{', '}}'];
                $closeAt = strpos($source, $close, $at + strlen($open));

                if ($closeAt === false) {
                    $at += strlen($open);

                    continue;
                }

                $expression = substr($source, $at + strlen($open), $closeAt - $at - strlen($open));
                $echoEnd = $closeAt + strlen($close);
                [$key, $path] = $this->resolve($expression, $fields, $loops);

                if ($key !== null && $state === 'TEXT') {
                    $refs[] = new EchoRef($key, $path, 'text', $at, $echoEnd, $this->lineAt($source, $at));
                } elseif ($key !== null && $state === 'ATTR' && $attribute !== null && $tagNameEnd !== null && !$tagIsComponent) {
                    $refs[] = new EchoRef($key, $path, 'attr', $tagNameEnd, null, $this->lineAt($source, $at), $attribute);
                }

                $at = $echoEnd;

                continue;
            }

            if ($state === 'TEXT') {
                if ($char === '<' && preg_match('/\G<([a-zA-Z][\w:.-]*)/A', $source, $m, 0, $at)) {
                    $state = 'TAG';
                    $tagNameEnd = $at + strlen($m[0]);
                    $tagIsComponent = str_starts_with($m[1], 'x-');
                    $at = $tagNameEnd;

                    continue;
                }

                if (substr($source, $at, 2) === '</') {
                    $state = 'TAG';
                    $tagNameEnd = null;
                    $tagIsComponent = true;
                    $at += 2;

                    continue;
                }

                $at++;

                continue;
            }

            if ($state === 'TAG') {
                if ($char === '>') {
                    $state = 'TEXT';
                    $at++;

                    continue;
                }

                if ($char === '"' || $char === "'") {
                    $before = substr($source, max(0, $at - 100), min(100, $at));
                    $attribute = preg_match('/([\w:@.\-]+)\s*=\s*$/', $before, $m) ? $m[1] : null;
                    $state = 'ATTR';
                    $quote = $char;
                    $at++;

                    continue;
                }

                $at++;

                continue;
            }

            // ATTR
            if ($char === $quote) {
                $state = 'TAG';
                $attribute = null;
            }

            $at++;
        }

        return $refs;
    }

    /**
     * Which field an expression echoes, if any.
     *
     * Only the innermost loop is consulted: inside a nested loop the
     * `$loop->index` a sentinel would emit belongs to that inner loop, so a
     * repeater echo there is left unmapped rather than mislabelled.
     *
     * @return array{0: ?string, 1: ?string} [field key, sentinel path]
     */
    private function resolve(string $expression, array $fields, array $loops): array
    {
        $expression = trim($expression);

        // {{ $heading }} and {{ $heading ?? 'fallback' }}
        if (preg_match('/^\$(\w+)(?:\s*\?\?.*)?$/s', $expression, $m) && isset($fields[$m[1]])) {
            return [$m[1], $m[1]];
        }

        $loop = end($loops);

        if (!$loop || $loop['field'] === null) {
            return [null, null];
        }

        // {{ $item['title'] }} / {{ $item->title }} inside @foreach ($people as $item)
        $alias = preg_quote($loop['alias'], '/');

        if (preg_match('/^\$' . $alias . '(?:\[[\'"](\w+)[\'"]\]|->(\w+))(?:\s*\?\?.*)?$/s', $expression, $m)) {
            $sub = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');

            return [$loop['field'], $loop['field'] . '.{{ $loop->index }}.' . $sub];
        }

        return [null, null];
    }

    /** @return list<array{0: int, 1: int}> */
    private function skipRegions(string $source): array
    {
        $regions = [];

        foreach (self::SKIP as [$open, $close]) {
            $at = 0;

            while (($start = strpos($source, $open, $at)) !== false) {
                $closeAt = strpos($source, $close, $start + strlen($open));
                // An unterminated region skips only its opener — never to EOF,
                // which would silently blind the scanner to the whole file.
                $end = $closeAt === false ? $start + strlen($open) : $closeAt + strlen($close);
                $regions[] = [$start, $end];
                $at = $end;
            }
        }

        foreach (['script', 'style'] as $tag) {
            $at = 0;

            while (($start = stripos($source, '<' . $tag, $at)) !== false) {
                $closeAt = stripos($source, '</' . $tag, $start);
                $end = $closeAt === false ? $start + strlen($tag) + 1 : $closeAt;
                $regions[] = [$start, $end];
                $at = $end + 1;
            }
        }

        return $regions;
    }

    /** @param list<array{0: int, 1: int}> $regions */
    private function skipTo(array $regions, int $offset): ?int
    {
        foreach ($regions as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return $end;
            }
        }

        return null;
    }

    private function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }
}
