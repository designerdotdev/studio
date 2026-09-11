<?php

namespace Designer\Studio\Services\Site;

/**
 * Writes new data into an existing JSON file without disturbing how it was
 * formatted.
 *
 * Template authors lay their data out by hand — a menu as a column of
 * one-line `{ "text": "Home", "url": "/" }` objects, say — and a site's
 * data files are theirs. So an edit is applied to the file's text, not by
 * re-encoding the whole document: a changed value is replaced where it
 * stands, and only a structural change (an item added or removed, a key
 * renamed) re-encodes the one object or array it happened in, in the same
 * style its siblings were written in.
 */
final class JsonDocument
{
    private int $at = 0;

    private function __construct(private string $text) {}

    /** The file's new text: `$original` with `$data` written into it. */
    public static function update(?string $original, mixed $data): string
    {
        if ($original === null || trim($original) === '') {
            return self::encode($data, '') . "\n";
        }

        try {
            $document = new self($original);
            $document->space();
            $root = $document->value();
        } catch (\Throwable) {
            return self::encode($data, '') . "\n";
        }

        return substr($original, 0, $root['start'])
            . $document->patch($root, $data)
            . substr($original, $root['end']);
    }

    /** Pretty JSON, four-space indented, continuation lines prefixed. */
    public static function encode(mixed $data, string $indent, bool $inlineItems = false): string
    {
        if ($inlineItems && is_array($data) && array_is_list($data) && $data !== []) {
            $lines = array_map(fn ($item) => $indent . '    ' . self::inline($item), $data);

            return "[\n" . implode(",\n", $lines) . "\n" . $indent . ']';
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str_replace("\n", "\n" . $indent, (string) $json);
    }

    /** One line, spaced the way hand-written JSON usually is: `{ "a": 1, "b": 2 }`. */
    private static function inline(mixed $value): string
    {
        if (is_array($value) && !array_is_list($value)) {
            $pairs = [];

            foreach ($value as $key => $item) {
                $pairs[] = json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ': ' . self::inline($item);
            }

            return '{ ' . implode(', ', $pairs) . ' }';
        }

        if (is_array($value)) {
            return '[' . implode(', ', array_map([self::class, 'inline'], $value)) . ']';
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /* ------------------------------------------------------------ */
    /*  Patching                                                     */
    /* ------------------------------------------------------------ */

    private function patch(array $node, mixed $data): string
    {
        $original = substr($this->text, $node['start'], $node['end'] - $node['start']);

        if ($node['type'] === 'scalar') {
            return !is_array($data) && $node['value'] === $data ? $original : self::encode($data, $this->indentAt($node['start']));
        }

        if (!is_array($data)) {
            return self::encode($data, $this->indentAt($node['start']));
        }

        if ($node['type'] === 'object') {
            $keys = array_map(fn ($entry) => $entry[0], $node['children']);

            if ($data === [] && $keys === []) {
                return $original;
            }

            if (!array_is_list($data) || $data === []) {
                if (array_map('strval', array_keys($data)) === $keys) {
                    return $this->patchChildren($node, array_values($data));
                }
            }
        }

        if ($node['type'] === 'array' && array_is_list($data) && count($data) === count($node['children'])) {
            return $this->patchChildren($node, $data);
        }

        return self::encode($data, $this->indentAt($node['start']), $this->itemsInline($node));
    }

    /** Rewrite each child value in place, keeping every byte between them. */
    private function patchChildren(array $node, array $values): string
    {
        $out = '';
        $cursor = $node['start'];

        foreach ($node['children'] as $i => $child) {
            $value = $node['type'] === 'object' ? $child[1] : $child;
            $out .= substr($this->text, $cursor, $value['start'] - $cursor) . $this->patch($value, $values[$i]);
            $cursor = $value['end'];
        }

        return $out . substr($this->text, $cursor, $node['end'] - $cursor);
    }

    /** Whether an array's items were each written on a single line. */
    private function itemsInline(array $node): bool
    {
        if ($node['type'] !== 'array' || $node['children'] === []) {
            return false;
        }

        foreach ($node['children'] as $child) {
            if ($child['type'] === 'scalar' || str_contains(substr($this->text, $child['start'], $child['end'] - $child['start']), "\n")) {
                return false;
            }
        }

        return str_contains(substr($this->text, $node['start'], $node['end'] - $node['start']), "\n");
    }

    /** The leading whitespace of the line a position sits on. */
    private function indentAt(int $position): string
    {
        $lineStart = strrpos(substr($this->text, 0, $position), "\n");
        $line = substr($this->text, $lineStart === false ? 0 : $lineStart + 1);

        return preg_match('/^[ \t]*/', $line, $m) ? $m[0] : '';
    }

    /* ------------------------------------------------------------ */
    /*  Parsing (positions kept)                                     */
    /* ------------------------------------------------------------ */

    private function value(): array
    {
        $start = $this->at;
        $char = $this->text[$this->at] ?? '';

        if ($char === '{') {
            $this->at++;
            $children = [];
            $this->space();

            if (($this->text[$this->at] ?? '') === '}') {
                $this->at++;

                return ['type' => 'object', 'start' => $start, 'end' => $this->at, 'children' => []];
            }

            while (true) {
                $this->space();
                $key = $this->value();

                if ($key['type'] !== 'scalar' || !is_string($key['value'])) {
                    throw new \UnexpectedValueException('Object key');
                }

                $this->space();
                $this->expect(':');
                $this->space();
                $children[] = [$key['value'], $this->value()];
                $this->space();

                if ($this->take() === '}') {
                    return ['type' => 'object', 'start' => $start, 'end' => $this->at, 'children' => $children];
                }
            }
        }

        if ($char === '[') {
            $this->at++;
            $children = [];
            $this->space();

            if (($this->text[$this->at] ?? '') === ']') {
                $this->at++;

                return ['type' => 'array', 'start' => $start, 'end' => $this->at, 'children' => []];
            }

            while (true) {
                $this->space();
                $children[] = $this->value();
                $this->space();

                if ($this->take() === ']') {
                    return ['type' => 'array', 'start' => $start, 'end' => $this->at, 'children' => $children];
                }
            }
        }

        if (!preg_match('/\G(?:"(?:[^"\\\\]|\\\\.)*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null)/', $this->text, $m, 0, $this->at)) {
            throw new \UnexpectedValueException('Value');
        }

        $this->at += strlen($m[0]);

        return ['type' => 'scalar', 'start' => $start, 'end' => $this->at, 'value' => json_decode($m[0], true)];
    }

    private function space(): void
    {
        while (isset($this->text[$this->at]) && ctype_space($this->text[$this->at])) {
            $this->at++;
        }
    }

    private function take(): string
    {
        $char = $this->text[$this->at] ?? throw new \UnexpectedValueException('End');
        $this->at++;

        if ($char !== ',' && $char !== '}' && $char !== ']') {
            throw new \UnexpectedValueException('Separator');
        }

        return $char;
    }

    private function expect(string $char): void
    {
        if (($this->text[$this->at] ?? '') !== $char) {
            throw new \UnexpectedValueException("Expected {$char}");
        }

        $this->at++;
    }
}
