<?php

namespace Designer\Studio\Services\Site;

/**
 * A lossless reader for the component tags in a Blade file.
 *
 * Studio edits page files in place, so it has to know exactly where every
 * `<x-…>` tag starts and ends and how each attribute was written — an edit
 * to one heading must leave every other byte of the file alone. This class
 * finds top-level component tags (tags nested inside another component's
 * slot belong to that component), keeps the raw text of every attribute and
 * the whitespace between them, and recognises a tag that has been commented
 * out with `{{-- … --}}`, which is how a hidden section is written.
 *
 * Comments, `@verbatim` blocks, and `<script>` bodies are stepped over, the
 * same regions where a tag is not markup Studio should manage.
 */
final class BladeTags
{
    /** Name characters Blade accepts after `<x-`. */
    protected const NAME = '[\w\-:.]+';

    /**
     * Every top-level component tag between two offsets, in order. A Blade
     * comment whose only content is one component tag comes back as a
     * `hidden` entry carrying that tag.
     *
     * @return list<array{type: string, start: int, end: int, tag: array}>
     */
    public static function scan(string $source, int $from = 0, ?int $to = null): array
    {
        $to ??= strlen($source);
        $found = [];
        $at = $from;

        while ($at < $to) {
            $next = self::nextInteresting($source, $at, $to);

            if ($next === null) {
                break;
            }

            [$kind, $position] = $next;

            if ($kind === 'blade-comment') {
                $close = strpos($source, '--}}', $position + 4);
                $end = $close === false ? $to : min($to, $close + 4);
                $inner = substr($source, $position + 4, max(0, $end - 4 - ($position + 4)));

                if (($tag = self::soleTag($inner)) !== null) {
                    $found[] = ['type' => 'hidden', 'start' => $position, 'end' => $end, 'tag' => $tag];
                }

                $at = $end;

                continue;
            }

            if ($kind === 'skip') {
                $at = $position;

                continue;
            }

            $tag = self::tagAt($source, $position, $to);

            if ($tag === null) {
                $at = $position + 3;

                continue;
            }

            $found[] = ['type' => 'tag', 'start' => $tag['start'], 'end' => $tag['end'], 'tag' => $tag];
            $at = $tag['end'];
        }

        return $found;
    }

    /**
     * The component tag that opens at `$start`, fully parsed, or null when
     * the text there is not a well-formed tag.
     */
    public static function tagAt(string $source, int $start, ?int $limit = null): ?array
    {
        $limit ??= strlen($source);

        if (!preg_match('/\G<x-(' . self::NAME . ')/', $source, $match, 0, $start)) {
            return null;
        }

        $ref = $match[1];

        if ($ref === 'slot' || str_starts_with($ref, 'slot:')) {
            return null;
        }

        $headEnd = $start + strlen($match[0]);
        $close = self::openTagEnd($source, $headEnd, $limit);

        if ($close === null) {
            return null;
        }

        $inside = substr($source, $headEnd, $close - $headEnd);
        $selfClosing = str_ends_with(rtrim($inside), '/');
        $attributeText = $selfClosing ? substr(rtrim($inside), 0, -1) : $inside;
        $tail = substr($inside, strlen($attributeText)) . '>';

        [$items, $trailing] = self::attributes($attributeText);

        $tag = [
            'ref' => $ref,
            'start' => $start,
            'head' => $match[0],
            'items' => $items,
            'trailing' => $trailing,
            'tail' => $tail,
            'selfClosing' => $selfClosing,
            'slot' => null,
            'closing' => '',
        ];

        if ($selfClosing) {
            $tag['end'] = $close + 1;
        } else {
            $closing = self::closingTag($source, $ref, $close + 1, $limit);

            if ($closing === null) {
                return null;
            }

            $tag['slot'] = substr($source, $close + 1, $closing - ($close + 1));
            $tag['closing'] = '</x-' . $ref . '>';
            $tag['end'] = $closing + strlen($tag['closing']);
        }

        $tag['raw'] = substr($source, $start, $tag['end'] - $start);

        return $tag;
    }

    /** Rebuild a tag from its (possibly edited) parts. */
    public static function render(array $tag): string
    {
        $text = $tag['head'];

        foreach ($tag['items'] as $item) {
            $text .= $item['space'] . $item['raw'];
        }

        $text .= $tag['trailing'] . $tag['tail'];

        if (!$tag['selfClosing']) {
            $text .= ($tag['slot'] ?? '') . $tag['closing'];
        }

        return $text;
    }

    /** Length of a tag's opening part (`<x-… …>`), i.e. where its slot begins. */
    public static function openLength(array $tag): int
    {
        $length = strlen($tag['head']) + strlen($tag['trailing']) + strlen($tag['tail']);

        foreach ($tag['items'] as $item) {
            $length += strlen($item['space']) + strlen($item['raw']);
        }

        return $length;
    }

    /**
     * The one component tag a fragment consists of (comments and whitespace
     * aside), or null when it holds anything else.
     */
    public static function soleTag(string $fragment): ?array
    {
        $trimmed = trim($fragment);

        if (!str_starts_with($trimmed, '<x-')) {
            return null;
        }

        $tag = self::tagAt($trimmed, 0);

        return $tag !== null && trim(substr($trimmed, $tag['end'])) === '' ? $tag : null;
    }

    /**
     * Whether a stretch of markup is only whitespace and comments — the
     * text a composed page may carry between its sections.
     */
    public static function isInert(string $text): bool
    {
        $text = preg_replace('/\{\{--.*?--\}\}/s', '', $text) ?? $text;
        $text = preg_replace('/<!--.*?-->/s', '', $text) ?? $text;

        return trim($text) === '';
    }

    /**
     * Split a gap into its structural part and the whitespace/comment run at
     * its end. The run travels with the tag that follows it (a comment
     * usually describes the next section); the structure stays put.
     *
     * @return array{0: string, 1: string} [anchor, prelude]
     */
    public static function splitGap(string $gap): array
    {
        $pieces = preg_split('/(\{\{--.*?--\}\}|<!--.*?-->|\s+)/s', $gap, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $prelude = '';

        while ($pieces !== []) {
            $last = end($pieces);

            if (trim($last) !== '' && !preg_match('/^(\{\{--.*--\}\}|<!--.*-->)$/s', $last)) {
                break;
            }

            $prelude = array_pop($pieces) . $prelude;
        }

        return [implode('', $pieces), $prelude];
    }

    /* ------------------------------------------------------------ */
    /*  Scanning                                                     */
    /* ------------------------------------------------------------ */

    /**
     * The next thing worth stopping at: a component tag, a Blade comment,
     * or a region to jump over. Returns [kind, offset]; for 'skip' the
     * offset is where scanning resumes.
     */
    protected static function nextInteresting(string $source, int $at, int $to): ?array
    {
        if (!preg_match('/<x-|\{\{--|<!--|@verbatim\b|<script\b/i', $source, $match, PREG_OFFSET_CAPTURE, $at)) {
            return null;
        }

        $position = $match[0][1];

        if ($position >= $to) {
            return null;
        }

        $token = strtolower($match[0][0]);

        return match ($token) {
            '<x-' => ['tag', $position],
            '{{--' => ['blade-comment', $position],
            '<!--' => ['skip', self::after($source, '-->', $position + 4, $to)],
            '@verbatim' => ['skip', self::after($source, '@endverbatim', $position + 9, $to)],
            default => ['skip', self::after($source, '</script>', $position + 7, $to, true)],
        };
    }

    protected static function after(string $source, string $needle, int $from, int $to, bool $insensitive = false): int
    {
        $found = $insensitive ? stripos($source, $needle, $from) : strpos($source, $needle, $from);

        return $found === false ? $to : min($to, $found + strlen($needle));
    }

    /** The `>` closing an opening tag, ignoring any inside quotes. */
    protected static function openTagEnd(string $source, int $from, int $limit): ?int
    {
        $quote = null;
        $depth = 0;

        for ($i = $from; $i < $limit; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;

                continue;
            }

            // @class(…) and {{ … }} can hold a `>` of their own
            if ($char === '(' || $char === '{') {
                $depth++;

                continue;
            }

            if (($char === ')' || $char === '}') && $depth > 0) {
                $depth--;

                continue;
            }

            if ($char === '>' && $depth === 0) {
                return $i;
            }

            if ($char === '<' && $depth === 0) {
                return null; // a new tag opened before this one closed
            }
        }

        return null;
    }

    /** Offset of the `</x-ref>` matching an opener, nesting aware. */
    protected static function closingTag(string $source, string $ref, int $from, int $limit): ?int
    {
        $close = '</x-' . $ref . '>';
        $at = $from;

        while ($at < $limit) {
            if (!preg_match('/<x-' . preg_quote($ref, '/') . '(?=[\s\/>])|' . preg_quote($close, '/') . '|\{\{--/', $source, $match, PREG_OFFSET_CAPTURE, $at)) {
                return null;
            }

            $position = $match[0][1];

            if ($position >= $limit) {
                return null;
            }

            if ($match[0][0] === '{{--') {
                $end = strpos($source, '--}}', $position + 4);
                $at = $end === false ? $limit : $end + 4;

                continue;
            }

            // Nested tags of the same name are skipped whole below, so the
            // first closing tag reached is always ours.
            if ($match[0][0] === $close) {
                return $position;
            }

            $nested = self::tagAt($source, $position, $limit);

            if ($nested === null) {
                $at = $position + 3;

                continue;
            }

            // A nested tag of the same name is skipped whole, so its own
            // closing tag is never mistaken for ours.
            $at = $nested['end'];
        }

        return null;
    }

    /* ------------------------------------------------------------ */
    /*  Attributes                                                   */
    /* ------------------------------------------------------------ */

    /**
     * Tokenise an attribute string into items that keep their raw text and
     * the whitespace in front of them, plus any trailing whitespace.
     *
     * @return array{0: list<array>, 1: string}
     */
    protected static function attributes(string $text): array
    {
        $items = [];
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            $spaceStart = $i;

            while ($i < $length && ctype_space($text[$i])) {
                $i++;
            }

            $space = substr($text, $spaceStart, $i - $spaceStart);

            if ($i >= $length) {
                return [$items, $space];
            }

            $start = $i;
            $item = self::attribute($text, $i);
            $item['space'] = $space;
            $item['raw'] = substr($text, $start, $i - $start);
            $items[] = $item;
        }

        return [$items, ''];
    }

    /** One attribute starting at `$i`; advances `$i` past it. */
    protected static function attribute(string $text, int &$i): array
    {
        $length = strlen($text);
        $opaque = ['name' => null, 'bound' => false, 'value' => null, 'quote' => '', 'opaque' => true];

        // @class(...) / @style(...)
        if (preg_match('/\G@(class|style)\s*\(/', $text, $m, 0, $i)) {
            $i += strlen($m[0]);
            $depth = 1;

            while ($i < $length && $depth > 0) {
                $char = $text[$i++];
                $depth += $char === '(' ? 1 : ($char === ')' ? -1 : 0);
            }

            return $opaque;
        }

        // {{ $attributes }} and friends
        if (substr($text, $i, 2) === '{{') {
            $end = strpos($text, '}}', $i + 2);
            $i = $end === false ? $length : $end + 2;

            return $opaque;
        }

        // :$name — shorthand for :name="$name"
        if (preg_match('/\G:\$(\w+)/', $text, $m, 0, $i)) {
            $i += strlen($m[0]);

            return ['name' => $m[1], 'bound' => true, 'value' => '$' . $m[1], 'quote' => '', 'opaque' => false, 'short' => true];
        }

        $escaped = substr($text, $i, 2) === '::';
        $bound = !$escaped && $text[$i] === ':';

        if (!preg_match('/\G(::|:)?([\w\-:.@%]+)/', $text, $m, 0, $i)) {
            // Something Blade would not read as an attribute — keep it
            // verbatim and move on.
            $i++;

            return $opaque;
        }

        $name = $m[2];
        $i += strlen($m[0]);

        $cursor = $i;
        while ($cursor < $length && ctype_space($text[$cursor])) {
            $cursor++;
        }

        if ($cursor >= $length || $text[$cursor] !== '=') {
            return ['name' => $name, 'bound' => $bound, 'value' => null, 'quote' => '', 'opaque' => $escaped];
        }

        $cursor++;
        while ($cursor < $length && ctype_space($text[$cursor])) {
            $cursor++;
        }

        $quote = $cursor < $length ? $text[$cursor] : '';

        if ($quote === '"' || $quote === "'") {
            $end = strpos($text, $quote, $cursor + 1);
            $end = $end === false ? $length : $end;
            $value = substr($text, $cursor + 1, $end - $cursor - 1);
            $i = min($length, $end + 1);
        } else {
            preg_match('/\G[^\s>]*/', $text, $v, 0, $cursor);
            $value = $v[0];
            $quote = '';
            $i = $cursor + strlen($value);
        }

        return ['name' => $name, 'bound' => $bound, 'value' => $value, 'quote' => $quote, 'opaque' => $escaped];
    }
}
