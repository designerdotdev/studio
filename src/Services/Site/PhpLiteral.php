<?php

namespace Designer\Studio\Services\Site;

/**
 * Reads and writes the PHP literals that appear in bound component
 * attributes: `:items="[(object) ['title' => 'Plan']]"`, `:count="3"`,
 * `:show="true"`.
 *
 * Studio stores a section's values as data and has to put them back into a
 * page file as Blade, so the two directions must be exact inverses. Parsing
 * never evaluates anything: the expression is tokenised by PHP itself and
 * walked by a tiny grammar that accepts literals only — strings, numbers,
 * booleans, null, arrays, `(object)` casts, and string concatenation. Any
 * other expression is reported as "not a literal" and left to the caller.
 *
 * Output mirrors JSON-decoded data, which is what a site-template's sections
 * are written against: associative arrays become `(object) [...]` so a
 * section reads `$item->title` exactly as it does from a collection file.
 */
final class PhpLiteral
{
    /** @var array<int, array{0: int|string, 1: string}> */
    private array $tokens = [];

    private int $at = 0;

    /**
     * @return array{0: bool, 1: mixed} [is a literal, value]
     */
    public static function parse(string $expression): array
    {
        $expression = trim($expression);

        if ($expression === '') {
            return [false, null];
        }

        $parser = new self();

        try {
            $parser->tokens = self::tokenise($expression);
            $value = $parser->concat();

            if ($parser->peek() !== null) {
                return [false, null];
            }

            return [true, $value];
        } catch (\Throwable) {
            return [false, null];
        }
    }

    /**
     * PHP source for a value, plus the quote character the attribute that
     * carries it must use.
     *
     * Strings are single-quoted unless one of them contains a double quote;
     * then every string is double-quoted with the apostrophe escaped as
     * `\x27`, so the whole expression can sit inside a single-quoted
     * attribute. Blade's attribute grammar allows no escaping of the
     * delimiter itself, which is what forces the choice.
     *
     * @return array{0: string, 1: string} [code, attribute quote]
     */
    public static function export(mixed $value, string $indent = ''): array
    {
        $double = self::containsDoubleQuote($value);

        return [self::write($value, $indent, $double), $double ? "'" : '"'];
    }

    /* ------------------------------------------------------------ */
    /*  Writing                                                      */
    /* ------------------------------------------------------------ */

    private static function write(mixed $value, string $indent, bool $double): string
    {
        return match (true) {
            is_string($value) => self::string($value, $double),
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_int($value) => (string) $value,
            is_float($value) => var_export($value, true),
            is_array($value) => self::array($value, $indent, $double),
            is_object($value) => self::array((array) $value, $indent, $double, true),
            default => self::string((string) $value, $double),
        };
    }

    private static function array(array $value, string $indent, bool $double, bool $forceObject = false): string
    {
        $list = array_is_list($value) && !$forceObject;

        if ($value === []) {
            return $forceObject ? '(object) []' : '[]';
        }

        $inner = $indent . '    ';
        $lines = [];

        foreach ($value as $key => $item) {
            $code = self::write($item, $inner, $double);
            $lines[] = $inner . ($list ? $code : self::key($key, $double) . ' => ' . $code) . ',';
        }

        return ($list ? '' : '(object) ') . "[\n" . implode("\n", $lines) . "\n" . $indent . ']';
    }

    private static function key(int|string $key, bool $double): string
    {
        return is_int($key) ? (string) $key : self::string($key, $double);
    }

    private static function string(string $value, bool $double): string
    {
        if (!$double) {
            return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }

        return '"' . str_replace(['\\', '"', '$', "'"], ['\\\\', '\\"', '\\$', '\\x27'], $value) . '"';
    }

    private static function containsDoubleQuote(mixed $value): bool
    {
        if (is_string($value)) {
            return str_contains($value, '"');
        }

        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $key => $item) {
                if ((is_string($key) && str_contains($key, '"')) || self::containsDoubleQuote($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /* ------------------------------------------------------------ */
    /*  Reading                                                      */
    /* ------------------------------------------------------------ */

    /** @return array<int, array{0: int|string, 1: string}> */
    private static function tokenise(string $expression): array
    {
        $tokens = [];

        foreach (token_get_all('<?php ' . $expression . ';') as $token) {
            $token = is_array($token) ? [$token[0], $token[1]] : [$token, $token];

            if (in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $tokens[] = $token;
        }

        // The `;` appended above closes the statement; it is not part of
        // the expression.
        if (end($tokens) === [';', ';']) {
            array_pop($tokens);
        }

        return $tokens;
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->at] ?? null;
    }

    private function take(): array
    {
        $token = $this->peek() ?? throw new \UnexpectedValueException('Unexpected end');
        $this->at++;

        return $token;
    }

    private function expect(string $symbol): void
    {
        if ($this->take()[0] !== $symbol) {
            throw new \UnexpectedValueException("Expected {$symbol}");
        }
    }

    /** value ('.' value)* — concatenation only ever yields a string */
    private function concat(): mixed
    {
        $value = $this->unary();

        while (($this->peek()[0] ?? null) === '.') {
            $this->take();
            $next = $this->unary();

            if (!is_scalar($value) || !is_scalar($next)) {
                throw new \UnexpectedValueException('Only scalars concatenate');
            }

            $value = (string) $value . (string) $next;
        }

        return $value;
    }

    private function unary(): mixed
    {
        $token = $this->peek() ?? throw new \UnexpectedValueException('Unexpected end');

        if ($token[0] === '-' || $token[0] === '+') {
            $this->take();
            $number = $this->primary();

            if (!is_int($number) && !is_float($number)) {
                throw new \UnexpectedValueException('Sign on a non-number');
            }

            return $token[0] === '-' ? -$number : $number;
        }

        if ($token[0] === T_OBJECT_CAST) {
            $this->take();
            $value = $this->primary();

            if (!is_array($value)) {
                throw new \UnexpectedValueException('(object) needs an array');
            }

            return $value;
        }

        return $this->primary();
    }

    private function primary(): mixed
    {
        [$type, $text] = $this->take();

        switch ($type) {
            case T_CONSTANT_ENCAPSED_STRING:
                return self::decodeString($text);

            case T_LNUMBER:
                return intval($text, 0);

            case T_DNUMBER:
                return (float) $text;

            case T_STRING:
                return match (strtolower($text)) {
                    'true' => true,
                    'false' => false,
                    'null' => null,
                    default => throw new \UnexpectedValueException("Not a literal: {$text}"),
                };

            case '[':
                return $this->items(']');

            case T_ARRAY:
                $this->expect('(');

                return $this->items(')');
        }

        throw new \UnexpectedValueException('Not a literal');
    }

    private function items(string $close): array
    {
        $items = [];

        while (true) {
            if (($this->peek()[0] ?? null) === $close) {
                $this->take();

                return $items;
            }

            $first = $this->concat();

            if (($this->peek()[0] ?? null) === T_DOUBLE_ARROW) {
                $this->take();

                if (!is_int($first) && !is_string($first)) {
                    throw new \UnexpectedValueException('Invalid key');
                }

                $items[$first] = $this->concat();
            } else {
                $items[] = $first;
            }

            $next = $this->take()[0];

            if ($next === $close) {
                return $items;
            }

            if ($next !== ',') {
                throw new \UnexpectedValueException('Expected , or ' . $close);
            }
        }
    }

    /** The value of a PHP string literal token, escapes resolved. */
    private static function decodeString(string $token): string
    {
        $quote = $token[0];
        $body = substr($token, 1, -1);

        if ($quote === "'") {
            return preg_replace_callback("/\\\\([\\\\'])/", fn ($m) => $m[1], $body) ?? $body;
        }

        return preg_replace_callback(
            '/\\\\(?:([nrtvef\\\\$"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u\{([0-9A-Fa-f]+)\})/',
            function (array $m): string {
                if ($m[1] !== '') {
                    return ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'][$m[1]];
                }

                if (($m[2] ?? '') !== '') {
                    return chr(octdec($m[2]) & 0xFF);
                }

                if (($m[3] ?? '') !== '') {
                    return chr(hexdec($m[3]));
                }

                return mb_chr(hexdec($m[4]), 'UTF-8');
            },
            $body
        ) ?? $body;
    }
}
