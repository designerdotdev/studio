<?php

namespace Designer\Studio\Services\Templates;

/**
 * Reads a template's page as a list of sections.
 *
 * A page in a site-templates repo is a layout tag wrapping an ordered run of
 * component tags:
 *
 *     <x-layouts.main title="Pricing" description="…">
 *         <x-sections.hero heading="Plans" :items="$plans"/>
 *         <x-sections.faq :faqs="$faqs"/>
 *     </x-layouts.main>
 *
 * which is the same shape as a Studio page: a component reference plus the
 * values that override its defaults. This class turns one into the other.
 *
 * Attribute values arrive in two forms. A plain `heading="Plans"` is a
 * literal string. A bound `:items="$plans"` names something the page never
 * declares itself — a collection file or a key of the site document — and is
 * returned unresolved for the importer to look up.
 */
class SectionTagParser
{
    /**
     * Split a page file into its wrapper and the sections inside it.
     *
     * @return array{layout: ?array, sections: array<int, array>, body: string}
     */
    public function parsePage(string $source): array
    {
        $source = $this->stripComments($source);

        $layout = null;
        $body = $source;

        // The outermost tag is the page's layout; everything nested in it is
        // the page itself.
        if (preg_match('/<x-(layouts?\.[A-Za-z0-9._-]+)\b([^>]*?)>(.*)<\/x-\1>/s', $source, $match)) {
            $layout = [
                'ref' => $match[1],
                'attributes' => $this->parseAttributes($match[2]),
            ];
            $body = $match[3];
        }

        return [
            'layout' => $layout,
            'sections' => $this->parseTags($body),
            'body' => trim($body),
        ];
    }

    /**
     * Every top-level component tag in a fragment, in document order.
     *
     * Nested tags are left inside their parent: a section that composes
     * smaller components is still one section.
     *
     * @return array<int, array{ref: string, attributes: array, slot: string}>
     */
    public function parseTags(string $fragment): array
    {
        $tags = [];
        $offset = 0;
        $length = strlen($fragment);

        while ($offset < $length) {
            if (!preg_match('/<x-(?!slot\b)([A-Za-z0-9._:-]+)/', $fragment, $open, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $ref = $open[1][0];
            $tagStart = $open[0][1];
            $attrStart = $tagStart + strlen($open[0][0]);
            $attrEnd = $this->findTagEnd($fragment, $attrStart);

            if ($attrEnd === null) {
                break;
            }

            $attributes = $this->parseAttributes(substr($fragment, $attrStart, $attrEnd - $attrStart));
            $selfClosing = str_ends_with(rtrim(substr($fragment, $attrStart, $attrEnd - $attrStart)), '/');

            if ($selfClosing) {
                $slot = '';
                $offset = $attrEnd + 1;
            } else {
                $close = $this->findClosingTag($fragment, $ref, $attrEnd + 1);

                if ($close === null) {
                    $offset = $attrEnd + 1;

                    continue;
                }

                $slot = substr($fragment, $attrEnd + 1, $close - ($attrEnd + 1));
                $offset = $close + strlen("</x-{$ref}>");
            }

            $tags[] = [
                'ref' => $ref,
                'attributes' => $attributes,
                'slot' => trim($slot),
            ];
        }

        return $tags;
    }

    /**
     * Attribute list of a single tag.
     *
     * Literal values come back as `['value' => string]`; bound ones as
     * `['bind' => expression]` for the caller to resolve. Scanned left to
     * right rather than pattern-matched, because a value like
     * `body="I design and build"` would otherwise look like a run of
     * valueless attributes.
     *
     * @return array<string, array{value?: string, bind?: string}>
     */
    public function parseAttributes(string $raw): array
    {
        $raw = rtrim(trim($raw), '/');
        $length = strlen($raw);
        $attributes = [];
        $i = 0;

        while ($i < $length) {
            if (ctype_space($raw[$i])) {
                $i++;

                continue;
            }

            $bound = $raw[$i] === ':';

            if ($bound) {
                $i++;
            }

            if (!preg_match('/\G[A-Za-z_@][A-Za-z0-9_.:-]*/', $raw, $nameMatch, 0, $i)) {
                $i++; // Not an attribute name — step over it and keep going.

                continue;
            }

            $name = $nameMatch[0];
            $i += strlen($name);

            $cursor = $i;
            while ($cursor < $length && ctype_space($raw[$cursor])) {
                $cursor++;
            }

            // No `=` follows, so this is a bare flag: `<x-nav sticky/>`.
            if ($cursor >= $length || $raw[$cursor] !== '=') {
                $attributes[$name] = ['value' => '1'];

                continue;
            }

            $cursor++;
            while ($cursor < $length && ctype_space($raw[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $length || ($raw[$cursor] !== '"' && $raw[$cursor] !== "'")) {
                continue;
            }

            $quote = $raw[$cursor];
            $valueEnd = strpos($raw, $quote, $cursor + 1);

            if ($valueEnd === false) {
                break;
            }

            $value = substr($raw, $cursor + 1, $valueEnd - $cursor - 1);
            $i = $valueEnd + 1;

            $attributes[$name] = $bound
                ? ['bind' => trim($value)]
                : ['value' => html_entity_decode($value, ENT_QUOTES | ENT_HTML5)];
        }

        return $attributes;
    }

    /**
     * The `>` that ends an opening tag, skipping any that sit inside a
     * quoted attribute value.
     */
    protected function findTagEnd(string $source, int $from): ?int
    {
        $length = strlen($source);
        $quote = null;

        for ($i = $from; $i < $length; $i++) {
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

            if ($char === '>') {
                return $i;
            }
        }

        return null;
    }

    /** Position of the closing tag that matches an opener, nesting aware. */
    protected function findClosingTag(string $source, string $ref, int $from): ?int
    {
        $open = '<x-' . $ref;
        $close = '</x-' . $ref . '>';
        $depth = 1;
        $cursor = $from;

        while (true) {
            $nextClose = strpos($source, $close, $cursor);

            if ($nextClose === false) {
                return null;
            }

            $nextOpen = strpos($source, $open, $cursor);

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $cursor = $nextOpen + strlen($open);

                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $nextClose;
            }

            $cursor = $nextClose + strlen($close);
        }
    }

    /** Blade and HTML comments never carry section data. */
    protected function stripComments(string $source): string
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;

        return preg_replace('/<!--.*?-->/s', '', $source) ?? $source;
    }
}
