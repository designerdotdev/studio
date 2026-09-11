<?php

namespace Designer\Studio\Services\Site;

use Illuminate\Support\Str;

/**
 * Translates between a component tag's attributes and a Studio section
 * instance's `variables` + `bindings`.
 *
 * Reading (attribute → Studio):
 *
 *   heading="Plans"                 variables.heading = 'Plans'
 *   sticky                          variables.sticky = true
 *   :items="$posts"                 bindings.items = 'collections.posts'
 *   :primary="$site->menu_primary"  bindings.primary = 'site.menu_primary'
 *   :items="[(object) [...]]"       variables.items = [[...]]   (a literal)
 *   :count="$posts->count()"        bindings.count = 'php:$posts->count()'
 *   title="Hi {{ $site->name }}"    bindings.title = 'blade:Hi {{ $site->name }}'
 *
 * The last two are expressions Studio does not edit: they are shown as "set
 * in code", evaluated for the canvas, and written back untouched. Writing is
 * the exact inverse, so a value Studio never changed reads back identically.
 */
final class AttributeCodec
{
    /**
     * @param  array<string, string>  $collections  native variable name => collection doc name
     */
    public function __construct(
        protected array $collections = [],
    ) {}

    /**
     * What one attribute means.
     *
     * @return array{kind: string, key?: string, value?: mixed, binding?: string}
     */
    public function read(array $item, array $fields = []): array
    {
        if (!empty($item['opaque']) || $item['name'] === null) {
            return ['kind' => 'opaque'];
        }

        $key = $this->key($item['name'], $fields);

        if (!$item['bound']) {
            if ($item['value'] === null) {
                return ['kind' => 'value', 'key' => $key, 'value' => true];
            }

            if (self::hasEcho($item['value'])) {
                return ['kind' => 'binding', 'key' => $key, 'binding' => 'blade:' . $item['value']];
            }

            return ['kind' => 'value', 'key' => $key, 'value' => $item['value']];
        }

        $expression = trim((string) $item['value']);

        if ($expression === '') {
            return ['kind' => 'opaque'];
        }

        if (preg_match('/^\$site((?:\s*\??->\s*[A-Za-z_]\w*)+)$/', $expression, $m)) {
            $path = preg_replace('/\s*\??->\s*/', '.', $m[1]);

            return ['kind' => 'binding', 'key' => $key, 'binding' => 'site' . $path];
        }

        if (preg_match('/^\$([A-Za-z_]\w*)$/', $expression, $m) && isset($this->collections[$m[1]])) {
            return ['kind' => 'binding', 'key' => $key, 'binding' => 'collections.' . $this->collections[$m[1]]];
        }

        [$literal, $value] = PhpLiteral::parse($expression);

        if ($literal) {
            return ['kind' => 'value', 'key' => $key, 'value' => $value];
        }

        return ['kind' => 'binding', 'key' => $key, 'binding' => 'php:' . $expression];
    }

    /**
     * Blade maps a kebab-case attribute onto a camelCase prop, and the
     * template's yml names the prop. Match that so the inspector finds it.
     */
    public function key(string $name, array $fields = []): string
    {
        if (str_contains($name, '-') && !isset($fields[$name]) && isset($fields[Str::camel($name)])) {
            return Str::camel($name);
        }

        return $name;
    }

    /* ------------------------------------------------------------ */
    /*  Writing                                                      */
    /* ------------------------------------------------------------ */

    /** Attribute text for a plain value of a field of the given type. */
    public function writeValue(string $name, mixed $value, string $type = 'text', string $indent = ''): string
    {
        if ($type === 'toggle' && !is_array($value)) {
            // The site-templates convention: toggles travel as "1" / "0"
            return $name . '="' . (self::truthy($value) ? '1' : '0') . '"';
        }

        if (is_string($value)) {
            if (!self::hasEcho($value)) {
                if (!str_contains($value, '"')) {
                    return $name . '="' . $value . '"';
                }

                if (!str_contains($value, "'")) {
                    return $name . "='" . $value . "'";
                }
            }

            [$code, $quote] = PhpLiteral::export($value, $indent);

            return ':' . $name . '=' . $quote . $code . $quote;
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        [$code, $quote] = PhpLiteral::export($value, $indent);

        return ':' . $name . '=' . $quote . $code . $quote;
    }

    /** Attribute text for a binding. */
    public function writeBinding(string $name, string $binding): string
    {
        if (str_starts_with($binding, 'collections.')) {
            $doc = substr($binding, strlen('collections.'));
            $source = array_search($doc, $this->collections, true);

            return ':' . $name . '="$' . ($source !== false ? $source : Str::camel($doc)) . '"';
        }

        if (str_starts_with($binding, 'site.')) {
            return ':' . $name . '="$site->' . str_replace('.', '->', substr($binding, 5)) . '"';
        }

        if (str_starts_with($binding, 'blade:')) {
            $raw = substr($binding, 6);

            return $name . '=' . (str_contains($raw, '"') ? "'" . $raw . "'" : '"' . $raw . '"');
        }

        $expression = str_starts_with($binding, 'php:') ? substr($binding, 4) : $binding;

        return ':' . $name . '=' . (str_contains($expression, '"') ? "'" . $expression . "'" : '"' . $expression . '"');
    }

    /* ------------------------------------------------------------ */
    /*  Comparison                                                   */
    /* ------------------------------------------------------------ */

    /**
     * Whether two values mean the same thing to a field of this type — so
     * a toggle stored as `true` matches the "1" a template wrote, and "3"
     * matches 3.
     */
    public static function same(mixed $a, mixed $b, string $type = 'text'): bool
    {
        if ($type === 'toggle' && !is_array($a) && !is_array($b)) {
            return self::truthy($a) === self::truthy($b);
        }

        return self::canonical($a) === self::canonical($b);
    }

    public static function truthy(mixed $value): bool
    {
        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
        }

        return (bool) $value;
    }

    protected static function canonical(mixed $value): string
    {
        return json_encode(self::normalise($value));
    }

    protected static function normalise(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[$key] = self::normalise($item);
            }

            if (!array_is_list($out)) {
                ksort($out);
            }

            return $out;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $value;
    }

    public static function hasEcho(string $value): bool
    {
        return str_contains($value, '{{') || str_contains($value, '{!!');
    }
}
