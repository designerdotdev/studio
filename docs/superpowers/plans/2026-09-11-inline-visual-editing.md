# Inline Visual Editing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Studio canvas directly editable — hover a heading to see which field it is, click it and type into the page — with no annotation added to any section.

**Architecture:** A canvas-only pre-pass scans a section's Blade source for echoes of its declared `.yml` fields and weaves in HTML comment sentinels (`<!--sf:headingStart@41-->…<!--/sf-->`) plus `data-sf-attr` / `data-sf-when` markers. Comments are layout- and CSS-neutral, so the instrumented render is byte-identical to the plain one once stripped. The rendered DOM then carries its own field map: the iframe walks the sentinels into live `Range`s, which drive three-tier selection (section → repeater item → field), the hover chrome, and inline `contenteditable`. Writes commit through the same `EditorPanel` methods the inspector already uses.

**Tech Stack:** PHP 8.2+ / Laravel 11+, Livewire 3, Alpine, vanilla JS in `resources/js/studio.js` (no framework in the iframe), Tailwind 4 in `resources/css/studio.css`, Vite.

**Spec:** `docs/superpowers/specs/2026-09-11-inline-visual-editing-design.md` — read it before Task 1. Sections referenced below as §N are its sections.

## Global Constraints

- **Sentinels must never leave the canvas.** `SectionRenderer` instrumentation defaults to **off**; exactly two callers turn it on (`StudioController::iframe()`, `RenderController`). The draft preview, thumbnails, block previews and the runtime must be provably unaffected.
- **Never annotate an `<x-…>` tag.** Injecting `data-sf-attr`/`data-sf-when` into a component tag would become a component prop and change behaviour.
- **A scanner failure costs inline editing, never the render.** Every instrumentation call is wrapped and falls back to the uninstrumented source.
- **The scanner never evaluates anything.** Pure text in, text out. An expression it does not recognise produces no reference — never a wrong one.
- **Measured baseline that must hold** (62 sections across the installed site + `monarch` + `pilot` clones): **365/369 declared fields mapped (98.9%)**, **57/57 renderable sections byte-identical after stripping**, **213 sentinels rendered**. The 4 unmapped are `split.align`, `split.visual` (selects used only in comparisons) and `main.showFooter` ×2 (a toggle whose `@if` wraps `<x-footer>`).
- **Commit style:** end every commit message with the two attribution lines used in the spec commit (`Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>` and `Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX`).
- **After editing `resources/css` or `resources/js`, run `npm run build`** (or keep `npm run dev` watching), or the editor loads stale assets.
- **No test runner exists in this package.** The TDD cycle in this plan runs against `php artisan studio:inline:verify`, built in Task 1. Run it from the host app root: `<host-app>`.

---

### Task 1: `EchoScanner` — locate every echo of a declared field

**Files:**
- Create: `src/Services/Inline/EchoRef.php`
- Create: `src/Services/Inline/EchoScanner.php`
- Create: `src/Console/Commands/InlineVerify.php`
- Modify: `src/StudioServiceProvider.php` (the `$this->commands([…])` array, ~line 90)

**Interfaces:**
- Consumes: nothing — this is the first task.
- Produces:
  - `EchoRef` with readonly `string $key`, `string $path`, `string $context` (`'text'|'attr'|'when'`), `int $offset`, `?int $end`, `int $line`, `?string $attribute`.
  - `EchoScanner::scan(string $source, array $fields): array` returning `list<EchoRef>`.
  - `php artisan studio:inline:verify` printing a coverage table and exiting non-zero on a coverage regression.

- [ ] **Step 1: Write the failing test**

Create `src/Console/Commands/InlineVerify.php`. This command is the test harness for Tasks 1–3; Task 2 extends it. It walks every section in the installed site plus every cloned template and checks that each declared field resolves to at least one `EchoRef`.

```php
<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Inline\EchoScanner;
use Designer\Studio\Support\SitePaths;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * The regression gate for inline editing.
 *
 * This package has no test runner, so the scanner's two load-bearing
 * claims are checked here against every real section on the machine: the
 * installed site and every template clone in storage/studio/templates.
 */
class InlineVerify extends Command
{
    protected $signature = 'studio:inline:verify
                            {--section= : Only sections whose file name contains this}';

    protected $description = 'Check the inline-editing scanner: every declared field maps, and sentinels are inert (dev only)';

    /** The measured baseline these numbers must not fall below. */
    protected const EXPECT_MAPPED = 365;
    protected const EXPECT_FIELDS = 369;

    public function handle(EchoScanner $scanner): int
    {
        $sections = $this->sections();

        if ($sections === []) {
            $this->warn('No sections found — install a site first (php artisan studio:templates:import).');

            return self::FAILURE;
        }

        $fieldCount = 0;
        $mappedCount = 0;
        $unmapped = [];

        foreach ($sections as $base => $fields) {
            $source = (string) file_get_contents($base . '.blade.php');
            $refs = $scanner->scan($source, $fields);

            $hit = [];
            foreach ($refs as $ref) {
                $hit[$ref->key] = true;
            }

            $missing = array_diff(array_keys($fields), array_keys($hit));
            $fieldCount += count($fields);
            $mappedCount += count($fields) - count($missing);

            foreach ($missing as $key) {
                $unmapped[] = basename($base) . '.' . $key . '  (' . ($fields[$key]['type'] ?? 'text') . ')';
            }

            $this->line(sprintf(
                '  %3d%%  %2d/%-2d  %s',
                count($fields) ? round((count($fields) - count($missing)) / count($fields) * 100) : 100,
                count($fields) - count($missing),
                count($fields),
                basename($base)
            ));
        }

        $this->newLine();
        $this->info(sprintf('Coverage: %d/%d fields mapped across %d sections', $mappedCount, $fieldCount, count($sections)));

        foreach ($unmapped as $line) {
            $this->line('  unmapped: ' . $line);
        }

        if ($this->option('section')) {
            return self::SUCCESS;
        }

        if ($mappedCount < self::EXPECT_MAPPED) {
            $this->error(sprintf('Coverage regressed: %d < %d expected.', $mappedCount, self::EXPECT_MAPPED));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Every `<name>.blade.php` + `<name>.yml` pair that declares fields,
     * keyed by its path without extension.
     *
     * @return array<string, array<string, array>>
     */
    protected function sections(): array
    {
        $roots = [SitePaths::resources() . '/views/components'];

        foreach (glob(storage_path('studio/templates/*/files/resources/views/components')) ?: [] as $clone) {
            $roots[] = $clone;
        }

        $found = [];
        $filter = (string) $this->option('section');

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($walk as $file) {
                if ($file->isDir() || !str_ends_with($file->getFilename(), '.yml')) {
                    continue;
                }

                $base = substr($file->getPathname(), 0, -4);

                if (!is_file($base . '.blade.php')) {
                    continue;
                }

                if ($filter !== '' && !str_contains($base, $filter)) {
                    continue;
                }

                try {
                    $meta = Yaml::parseFile($base . '.yml');
                } catch (\Throwable) {
                    continue;
                }

                $fields = is_array($meta['fields'] ?? null) ? array_filter($meta['fields'], 'is_array') : [];

                if ($fields !== []) {
                    $found[$base] = $fields;
                }
            }
        }

        return $found;
    }
}
```

Register it in `src/StudioServiceProvider.php`: add `use Designer\Studio\Console\Commands\InlineVerify;` beside the other command imports, and `InlineVerify::class,` into the `$this->commands([…])` array (keep the list alphabetical — it goes after `DevReset::class`).

- [ ] **Step 2: Run it to verify it fails**

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: FAIL — `Class "Designer\Studio\Services\Inline\EchoScanner" does not exist`.

- [ ] **Step 3: Write `EchoRef`**

```php
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
        /** `text` | `attr` | `when` */
        public readonly string $context,
        public readonly int $offset,
        public readonly ?int $end,
        /** 1-based line in the section source, for the provenance chip. */
        public readonly int $line,
        /** Attribute name — `attr` context only. */
        public readonly ?string $attribute = null,
    ) {}
}
```

- [ ] **Step 4: Write `EchoScanner`**

```php
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
```

- [ ] **Step 5: Run the command to verify it passes**

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: PASS, ending with `Coverage: 365/369 fields mapped across 62 sections` and exactly these four unmapped lines (order may vary):

```
  unmapped: main.showFooter  (toggle)
  unmapped: main.showFooter  (toggle)
  unmapped: split.align  (select)
  unmapped: split.visual  (select)
```

If the count is lower, the scanner has a gap — do **not** lower `EXPECT_MAPPED`. Use `--section=hero` to isolate.

- [ ] **Step 6: Commit**

```bash
cd <host-app>/packages/designer/studio
git add src/Services/Inline src/Console/Commands/InlineVerify.php src/StudioServiceProvider.php
git commit -m "Add the inline-editing echo scanner

Locates every echo of a declared yml field in a section's Blade source,
telling element content apart from attribute values, and resolves
repeater loops to an indexed path. studio:inline:verify gates coverage
at the measured 365/369 baseline.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 2: `Instrumenter` — weave sentinels, prove they are inert

**Files:**
- Create: `src/Services/Inline/Instrumenter.php`
- Modify: `src/Console/Commands/InlineVerify.php` (add the inertness pass)

**Interfaces:**
- Consumes: `EchoScanner::scan()`, `EchoRef` (Task 1).
- Produces:
  - `Instrumenter::weave(string $source, array $fields): string`
  - `Instrumenter::apply(string $source, array $refs): string`
  - `Instrumenter::strip(string $html): string` — removes sentinels from rendered HTML; used by the verify command and by nothing in production.

- [ ] **Step 1: Write the failing test**

Add the inertness invariant to `InlineVerify`. Insert these three members into the class (after `handle()`), and wire the call as shown in Step 2.

```php
    /**
     * The invariant: stripping the sentinels out of an instrumented render
     * must reproduce the plain render byte for byte. If this ever fails, a
     * sentinel is changing the page rather than just describing it.
     *
     * @param array<string, array<string, array>> $sections
     */
    protected function inertness(array $sections, Instrumenter $instrumenter): bool
    {
        $identical = 0;
        $diverged = [];
        $unrenderable = 0;
        $sentinels = 0;

        foreach ($sections as $base => $fields) {
            $source = (string) file_get_contents($base . '.blade.php');

            // The defaults a section sees when nothing has been edited, the
            // way ComponentData::resolveVariables resolves them.
            $variables = [];
            foreach ($fields as $key => $config) {
                $variables[$key] = ($config['type'] ?? 'text') === 'repeater' ? [] : ($config['default'] ?? '');
            }

            $plain = $this->render($source, $variables);

            if ($plain === null) {
                // Layout files need $site/$slot globals a bare render has no
                // way to supply; they are not canvas sections.
                $unrenderable++;

                continue;
            }

            $marked = $this->render($instrumenter->weave($source, $fields), $variables);

            if ($marked === null) {
                $diverged[] = basename($base) . ' (instrumented render threw)';

                continue;
            }

            $sentinels += substr_count($marked, '<!--sf:');

            if ($instrumenter->strip($marked) === $plain) {
                $identical++;

                continue;
            }

            $offset = 0;
            $stripped = $instrumenter->strip($marked);
            $limit = min(strlen($plain), strlen($stripped));

            while ($offset < $limit && $plain[$offset] === $stripped[$offset]) {
                $offset++;
            }

            $diverged[] = sprintf(
                "%s diverges at byte %d\n      plain: %s\n      strip: %s",
                basename($base),
                $offset,
                json_encode(substr($plain, max(0, $offset - 40), 90)),
                json_encode(substr($stripped, max(0, $offset - 40), 90))
            );
        }

        $this->info(sprintf(
            'Inertness: %d identical, %d diverged, %d unrenderable, %d sentinels rendered',
            $identical,
            count($diverged),
            $unrenderable,
            $sentinels
        ));

        foreach ($diverged as $line) {
            $this->error('  ' . $line);
        }

        return $diverged === [];
    }

    protected function render(string $source, array $variables): ?string
    {
        try {
            return \Designer\Studio\Support\NestedBlade::render($source, $variables);
        } catch (\Throwable) {
            return null;
        }
    }
```

Add `use Designer\Studio\Services\Inline\Instrumenter;` to the imports.

- [ ] **Step 2: Wire it into `handle()` and run to verify it fails**

Change the signature to `public function handle(EchoScanner $scanner, Instrumenter $instrumenter): int`, and replace the final `return self::SUCCESS;` of `handle()` with:

```php
        $this->newLine();

        if (!$this->inertness($sections, $instrumenter)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
```

Run:

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: FAIL — `Class "Designer\Studio\Services\Inline\Instrumenter" does not exist`.

- [ ] **Step 3: Write `Instrumenter`**

```php
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
```

- [ ] **Step 4: Run the command to verify it passes**

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: PASS, with both lines present:

```
Coverage: 365/369 fields mapped across 62 sections
Inertness: 57 identical, 0 diverged, 5 unrenderable, 213 sentinels rendered
```

Any `diverged` count above 0 is a hard stop: read the printed byte offset and the two 90-character windows to see exactly which sentinel changed the output.

- [ ] **Step 5: Commit**

```bash
cd <host-app>/packages/designer/studio
git add src/Services/Inline/Instrumenter.php src/Console/Commands/InlineVerify.php
git commit -m "Weave canvas sentinels into section source

Comment sentinels around text echoes, data-sf-attr on tags owning an
attribute echo, data-sf-when on a toggle's element. studio:inline:verify
now proves the invariant that matters: stripping the sentinels out of an
instrumented render reproduces the plain render byte for byte across all
57 renderable sections.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 3: Render the canvas instrumented — and only the canvas

**Files:**
- Modify: `src/Services/SectionRenderer.php` (`render()`, `renderHtml()`)
- Modify: `src/Http/Controllers/StudioController.php` (`iframe()` — the `$sections[] = [...]` array inside `$push`)
- Modify: `src/Http/Controllers/RenderController.php` (`__invoke()`)
- Modify: `resources/views/iframe.blade.php` (the `@php $rendered = …` block, ~line 726)

**Interfaces:**
- Consumes: `Instrumenter::weave()` (Task 2).
- Produces:
  - `SectionRenderer::render(ComponentData $component, array $variables, array $bindings = [], bool $instrument = false): string`
  - `SectionRenderer::renderHtml(string $html, array $variables, string $label = 'section', array $fields = [], bool $instrument = false): string`
  - Canvas HTML containing `<!--sf:…-->`; every other render path containing none.
  - `$sections[]` entries in `StudioController::iframe()` gain `'fields' => $component->fields`.

- [ ] **Step 1: Write the failing test**

The gate here is an HTTP-level check that sentinels appear in the canvas and nowhere else. Add this to the bottom of `InlineVerify::handle()`, just before the final `return self::SUCCESS;`:

```php
        $this->newLine();

        if (!$this->leakCheck()) {
            return self::FAILURE;
        }
```

And add the method to the class:

```php
    /**
     * Sentinels belong to the canvas and nowhere else. A leak into the draft
     * preview would mean one is a keystroke away from a published page file.
     */
    protected function leakCheck(): bool
    {
        $renderer = app(\Designer\Studio\Services\SectionRenderer::class);
        $components = app(\Designer\Studio\Services\Storage\ComponentRepository::class);
        $component = collect($components->all())->first(fn ($c) => $c->fields !== []);

        if (!$component) {
            $this->warn('Leak check skipped: no section with fields in the library.');

            return true;
        }

        $variables = $component->resolveVariables();
        $plain = $renderer->render($component, $variables);
        $canvas = $renderer->render($component, $variables, [], instrument: true);

        $ok = true;

        if (str_contains($plain, '<!--sf:') || str_contains($plain, 'data-sf-')) {
            $this->error('LEAK: the default (uninstrumented) render contains sentinels.');
            $ok = false;
        }

        if (!str_contains($canvas, '<!--sf:')) {
            $this->error('The canvas render contains no sentinels — instrumentation is not reaching it.');
            $ok = false;
        }

        if ($ok) {
            $this->info('Leak check: sentinels appear only when instrumentation is asked for.');
        }

        return $ok;
    }
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: FAIL — `Unknown named parameter $instrument`.

- [ ] **Step 3: Add instrumentation to `SectionRenderer`**

In `src/Services/SectionRenderer.php`, change the two render methods and add one helper. Note `render()` needs no `$fields` argument — the contract is already on `$component`.

```php
    public function render(ComponentData $component, array $variables, array $bindings = [], bool $instrument = false): string
    {
        try {
            return NestedBlade::render(
                $this->source($component->html, $component->fields, $instrument),
                $this->context($this->binder->apply($variables, $bindings))
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($component->name, $e->getMessage());
        }
    }

    /** Render raw Blade with the same context (previews, ad-hoc markup). */
    public function renderHtml(string $html, array $variables, string $label = 'section', array $fields = [], bool $instrument = false): string
    {
        try {
            return NestedBlade::render($this->source($html, $fields, $instrument), $this->context($variables));
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($label, $e->getMessage());
        }
    }

    /**
     * The source to compile. Canvas renders get inline-editing sentinels
     * woven in; every other path gets the section exactly as written.
     *
     * A scanner that trips over an unusual template costs that section its
     * inline editing, never its render — so the failure is reported and the
     * original source is used.
     */
    protected function source(string $html, array $fields, bool $instrument): string
    {
        if (!$instrument || $fields === []) {
            return $html;
        }

        try {
            return app(\Designer\Studio\Services\Inline\Instrumenter::class)->weave($html, $fields);
        } catch (\Throwable $e) {
            report($e);

            return $html;
        }
    }
```

- [ ] **Step 4: Turn it on for the two canvas callers**

In `src/Http/Controllers/StudioController.php`, inside `iframe()`'s `$push` closure, add `fields` to the `$sections[]` entry immediately after `'html' => $component->html,`:

```php
                    'html' => $component->html,
                    // The field contract travels with the section so the
                    // canvas can be rendered with inline-editing sentinels.
                    'fields' => $component->fields,
```

In `resources/views/iframe.blade.php`, replace the `$rendered = …` assignment inside the `@php` block:

```blade
                $rendered = app(\Designer\Studio\Services\SectionRenderer::class)
                    ->renderHtml(
                        $section['html'],
                        $componentVariables[$section['id']] ?? [],
                        $section['ref'],
                        $section['fields'] ?? [],
                        instrument: true,
                    );
```

In `src/Http/Controllers/RenderController.php`, add the named argument to the `$this->renderer->render(...)` call:

```php
            $html[$section['id']] = $this->renderer->render(
                $component,
                app(\Designer\Studio\Services\CollectionBinder::class)->apply(
                    $component->resolveVariables($section['variables'] ?? []),
                    $section['bindings'] ?? [],
                    $given
                ),
                instrument: true,
            );
```

- [ ] **Step 5: Run the command to verify it passes**

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: PASS, with all three lines — coverage, inertness, and `Leak check: sentinels appear only when instrumentation is asked for.`

- [ ] **Step 6: Verify against the running host**

```bash
cd <host-app> && php artisan serve &
sleep 3
# The canvas: expect a non-zero count
curl -s http://127.0.0.1:8000/studio/page/index/iframe | grep -c 'sf:'
# The draft preview and the live page: both must print 0
curl -s http://127.0.0.1:8000/studio/preview | grep -c 'sf:\|data-sf-'
curl -s http://127.0.0.1:8000/ | grep -c 'sf:\|data-sf-'
```

Expected: the first is > 0; the second and third are both `0`.

- [ ] **Step 7: Commit**

```bash
cd <host-app>/packages/designer/studio
git add src/Services/SectionRenderer.php src/Http/Controllers/StudioController.php src/Http/Controllers/RenderController.php resources/views/iframe.blade.php src/Console/Commands/InlineVerify.php
git commit -m "Render the canvas with inline-editing sentinels

SectionRenderer gains an instrument flag, default off; the canvas
document and the live re-render endpoint are the only two callers that
turn it on, so the draft preview, thumbnails and the runtime are
provably unaffected. A scanner failure falls back to the plain source.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 4: `StudioFields` — turn sentinels into a live field map

**Files:**
- Modify: `resources/js/studio.js` (add `StudioFields` before `StudioPreview`; call it from `StudioPreview.init()` and `StudioPreview.paint()`)
- Modify: `resources/views/iframe.blade.php` (add `paths` + `contracts` to the `window.__studioPreview` payload)
- Modify: `src/Http/Controllers/StudioController.php` (`iframe()` — pass `$componentPaths` and `$componentContracts` to the view)

**Interfaces:**
- Consumes: canvas HTML containing sentinels (Task 3).
- Produces on `window.Studio.fields`:
  - `index(wrapper)` — (re)build the map for one `[data-section]` wrapper
  - `indexAll()` — every wrapper in the document
  - `entriesFor(sectionId)` → `list<Entry>`
  - `at(sectionId, x, y)` → the deepest `Entry` whose rects contain the point, else `null`
  - `itemAt(sectionId, x, y)` → `{id, key, index, el}` or `null`
  - `box(entry)` → `{left, top, width, height}` union of the entry's rects, or `null`
  - `rects(entry)` → every rect the entry occupies (text wraps over several)
  - `refFor(sectionId)` → the section's component ref, or `null`
  - `sourceFor(sectionId)` → `'sections/hero'`, or `null`
  - `contractFor(sectionId, key)` → `{label, type}` as declared in the yml, or `null`
  - `debug()` → a console table, for the manual gate
  - `Entry` = `{path, key, index, subKey, line, kind, range, el}` where `kind` is `'text' | 'attr' | 'when'`, `index`/`subKey` are `null` for a non-repeater field, and `el` is set for `attr`/`when` (`range` for `text`).

- [ ] **Step 1: Write the failing test**

The gate is a console assertion in the canvas document. Open the editor, select the canvas iframe context in DevTools, and run `Studio.fields.debug()`. Right now that throws, which is the failing state.

- [ ] **Step 2: Run it to verify it fails**

```bash
cd <host-app> && php artisan serve
```

Open `http://127.0.0.1:8000/studio`, DevTools → Console → set the context dropdown to the `studio-canvas-frame` iframe, then run:

```js
Studio.fields.debug()
```

Expected: FAIL — `TypeError: Cannot read properties of undefined (reading 'debug')`.

- [ ] **Step 3: Pass the source paths and the field contract into the canvas**

The canvas needs two things the sentinels do not carry: the source file behind
each ref (to turn `@41` into a real path) and each field's declared label and
type (so a chip shows the author's own wording, not a guess derived from the
key, and so Enter behaves correctly in a `textarea`).

In `src/Http/Controllers/StudioController.php`, inside `iframe()`, add this just before the `return view('studio::iframe', [...])`:

```php
        // ref → source path, and ref → {key: {label, type}}. The sentinels
        // carry only a path and a line, so the labels and types the chips and
        // the editor need travel once, per ref, instead of per echo.
        $componentPaths = [];
        $componentContracts = [];

        foreach ($sections as $section) {
            if (isset($componentPaths[$section['ref']])) {
                continue;
            }

            if (!$component = $this->components->find($section['ref'])) {
                continue;
            }

            $componentPaths[$section['ref']] = $component->path;
            $componentContracts[$section['ref']] = collect($component->fields)
                ->map(fn ($config, $key) => [
                    'label' => $config['label'] ?? \Illuminate\Support\Str::headline($key),
                    'type' => $config['type'] ?? 'text',
                ])
                ->all();
        }
```

Add both to the view data array:

```php
            'componentPaths' => $componentPaths,
            'componentContracts' => $componentContracts,
```

In `resources/views/iframe.blade.php`, find the `window.__studioPreview = {...}` assignment and add two keys:

```blade
        paths: @js($componentPaths),
        contracts: @js($componentContracts),
```

- [ ] **Step 4: Write `StudioFields`**

Add this to `resources/js/studio.js`, immediately above `const StudioPreview = {`:

```js
/**
 * The canvas field map.
 *
 * Section markup arrives carrying comment sentinels around every echo of a
 * declared field (`<!--sf:headingStart@41-->…<!--/sf-->`), plus
 * `data-sf-attr` / `data-sf-when` on tags. This walks them into live Ranges
 * and elements, so the DOM itself becomes the index: a field's box comes
 * from `Range.getBoundingClientRect()`, which fits the text exactly even
 * when three fields share one <h1>.
 *
 * Anything with no entry is, by definition, set in code.
 */
const StudioFields = {
    maps: {},       // sectionId → { entries: [...], items: [...] }
    paths: {},      // ref → 'sections/hero'
    contracts: {},  // ref → { key: { label, type } }

    init(paths, contracts) {
        this.paths = paths || {};
        this.contracts = contracts || {};
        this.indexAll();
    },

    indexAll() {
        this.maps = {};
        document.querySelectorAll('[data-section]').forEach((wrapper) => this.index(wrapper));
    },

    /** (Re)build the map for one section. Called after every paint. */
    index(wrapper) {
        const sectionId = wrapper.dataset.section;
        const content = wrapper.querySelector('[data-section-content]');

        if (!sectionId || !content) return;

        const entries = [];
        const open = [];
        const walker = document.createTreeWalker(content, NodeFilter.SHOW_COMMENT);

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const value = node.nodeValue || '';

            if (value.startsWith('sf:')) {
                const at = value.lastIndexOf('@');
                open.push({ raw: value.slice(3, at), line: Number(value.slice(at + 1)) || 0, start: node });
                continue;
            }

            if (value === '/sf' && open.length) {
                const { raw, line, start } = open.pop();
                const range = document.createRange();

                try {
                    range.setStartAfter(start);
                    range.setEndBefore(node);
                } catch (e) {
                    continue;
                }

                entries.push({ ...this.parsePath(raw), line, kind: 'text', range, el: null });
            }
        }

        // Attribute and toggle markers live on the tag itself
        content.querySelectorAll('[data-sf-attr]').forEach((el) => {
            el.getAttribute('data-sf-attr').split(';').forEach((pair) => {
                const [attribute, rest] = pair.split(':');
                if (!rest) return;
                const at = rest.lastIndexOf('@');
                entries.push({
                    ...this.parsePath(rest.slice(0, at)),
                    line: Number(rest.slice(at + 1)) || 0,
                    kind: 'attr',
                    attribute,
                    range: null,
                    el,
                });
            });
        });

        content.querySelectorAll('[data-sf-when]').forEach((el) => {
            const raw = el.getAttribute('data-sf-when');
            const at = raw.lastIndexOf('@');
            entries.push({
                ...this.parsePath(raw.slice(0, at)),
                line: Number(raw.slice(at + 1)) || 0,
                kind: 'when',
                range: null,
                el,
            });
        });

        this.maps[sectionId] = { entries, items: this.groupItems(entries) };
    },

    /** `people.0.name` → {path, key: 'people', index: 0, subKey: 'name'} */
    parsePath(raw) {
        const parts = raw.split('.');

        if (parts.length >= 3 && /^\d+$/.test(parts[1])) {
            return { path: raw, key: parts[0], index: Number(parts[1]), subKey: parts.slice(2).join('.') };
        }

        return { path: raw, key: parts[0], index: null, subKey: null };
    },

    /**
     * Repeater items are derived, not instrumented: entries sharing a
     * `key.index` prefix are grouped and their nearest common ancestor
     * element becomes the item's box.
     */
    groupItems(entries) {
        const groups = {};

        entries.forEach((entry) => {
            if (entry.index === null) return;
            const id = entry.key + '.' + entry.index;
            (groups[id] = groups[id] || []).push(entry);
        });

        return Object.entries(groups)
            .map(([id, members]) => ({
                id,
                key: members[0].key,
                index: members[0].index,
                el: this.commonAncestor(members),
            }))
            .filter((item) => item.el);
    },

    commonAncestor(entries) {
        const elementOf = (entry) => {
            const node = entry.range ? entry.range.commonAncestorContainer : entry.el;
            return node && node.nodeType === 1 ? node : node?.parentElement || null;
        };

        let el = elementOf(entries[0]);

        for (const entry of entries.slice(1)) {
            const other = elementOf(entry);
            while (el && other && !el.contains(other)) el = el.parentElement;
        }

        return el;
    },

    entriesFor(sectionId) {
        return this.maps[sectionId]?.entries || [];
    },

    /** Every rect a field occupies — text wraps, so there may be several. */
    rects(entry) {
        if (entry.kind === 'text' && entry.range) {
            return Array.from(entry.range.getClientRects());
        }

        return entry.el ? [entry.el.getBoundingClientRect()] : [];
    },

    /** The union box, for drawing a halo around a whole wrapped heading. */
    box(entry) {
        const rects = this.rects(entry);

        if (!rects.length) return null;

        const left = Math.min(...rects.map((r) => r.left));
        const top = Math.min(...rects.map((r) => r.top));
        const right = Math.max(...rects.map((r) => r.right));
        const bottom = Math.max(...rects.map((r) => r.bottom));

        return { left, top, width: right - left, height: bottom - top };
    },

    /**
     * The field under a point. Text entries win over attribute ones (an
     * image's alt text and its src share an element), and the smallest
     * matching box wins so a nested field beats its container.
     */
    at(sectionId, x, y) {
        let best = null;
        let bestArea = Infinity;

        for (const entry of this.entriesFor(sectionId)) {
            if (entry.kind === 'when') continue;

            for (const rect of this.rects(entry)) {
                if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) continue;

                const area = rect.width * rect.height;

                if (area < bestArea) {
                    best = entry;
                    bestArea = area;
                }
            }
        }

        return best;
    },

    itemAt(sectionId, x, y) {
        let best = null;
        let bestArea = Infinity;

        for (const item of this.maps[sectionId]?.items || []) {
            const rect = item.el.getBoundingClientRect();

            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) continue;

            const area = rect.width * rect.height;

            if (area < bestArea) {
                best = item;
                bestArea = area;
            }
        }

        return best;
    },

    refFor(sectionId) {
        return document.querySelector(`[data-section="${sectionId}"]`)?.dataset.ref || null;
    },

    /** The source file behind a section wrapper, for the provenance chip. */
    sourceFor(sectionId) {
        const ref = this.refFor(sectionId);

        return ref && this.paths[ref] ? this.paths[ref] : null;
    },

    /** The declared `{label, type}` for a field, or null when undeclared. */
    contractFor(sectionId, key) {
        const ref = this.refFor(sectionId);

        return (ref && this.contracts[ref]?.[key]) || null;
    },

    debug() {
        const rows = [];

        Object.entries(this.maps).forEach(([sectionId, map]) => {
            map.entries.forEach((entry) => rows.push({
                section: sectionId,
                path: entry.path,
                kind: entry.kind + (entry.attribute ? ':' + entry.attribute : ''),
                line: entry.line,
                text: entry.kind === 'text' ? (entry.range?.toString() || '').slice(0, 40) : '',
            }));
            map.items.forEach((item) => rows.push({ section: sectionId, path: item.id, kind: 'item', line: '', text: '' }));
        });

        console.table(rows);

        return rows.length;
    },
};
```

- [ ] **Step 5: Call it from `StudioPreview`**

In `StudioPreview.init()`, widen the destructured signature. Find `init({ variables, bindings, refs, blocks, renderUrl, csrf }) {` and change it to:

```js
    init({ variables, bindings, refs, blocks, renderUrl, csrf, paths, contracts }) {
```

Then, immediately after the existing `this.setupContextMenu();` line, add:

```js
        StudioFields.init(paths, contracts);
```

In `StudioPreview.paint()`, re-index the section after the markup is swapped. Add this immediately after the `window.Alpine?.initTree(el);` block:

```js
        // The sentinels came with the new markup — rebuild this section's map
        StudioFields.index(el.closest('[data-section]'));
```

Finally, expose it on the global in the `window.Studio = {` object, beside `preview: StudioPreview,`:

```js
    fields: StudioFields,
```

- [ ] **Step 6: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

Reload `http://127.0.0.1:8000/studio`, switch the DevTools console context to the canvas iframe, and run:

```js
Studio.fields.debug()
```

Expected: PASS — a console table with one row per field on the page and a returned count > 0. Spot-check on the Monarch home page that the hero contributes rows for `headingStart`, `headingHighlight`, `headingEnd` (kind `text`), `image` and `imageAlt` (kind `attr:src` / `attr:alt`), and `showRating` / `showStat` (kind `when`), with plausible line numbers.

Then confirm the map survives a re-render: type in any inspector text field and re-run `Studio.fields.debug()` — the count must be the same.

- [ ] **Step 7: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/iframe.blade.php src/Http/Controllers/StudioController.php dist
git commit -m "Build the canvas field map from the sentinels

StudioFields walks the rendered comments into live Ranges, derives
repeater items from their common ancestors, and answers which field sits
under a point. Rebuilt per section after every paint.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 5: Three-tier selection, halos and chips

**Files:**
- Modify: `resources/js/studio.js` (`StudioPreview`: `selection`, `hover`, `select`, Esc handling)
- Modify: `resources/views/iframe.blade.php` (overlay CSS + the halo/chip elements)

**Interfaces:**
- Consumes: `Studio.fields.at()`, `itemAt()`, `box()`, `entriesFor()`, `sourceFor()` (Task 4).
- Produces:
  - `StudioPreview.selection` = `{tier, sectionId, path, index, key}`; `tier` is `'section' | 'item' | 'field'`.
  - `StudioPreview.selectField(entry)`, `selectItem(item)`, `walkUp()`.
  - postMessage `studio:field-selected` with `{sectionId, key, index, subKey, path, line, source, label}`.

- [ ] **Step 1: Write the failing test**

Manual gate, run in the canvas iframe console after the build:

```js
Studio.preview.selection
```

Expected now: `undefined` — the property does not exist yet.

- [ ] **Step 2: Add the overlay chrome**

In `resources/views/iframe.blade.php`, add to the `@push('iframe-head')` style block, after the existing `.studio-chip` rules:

```css
            /* ---- Field and item tiers ---- */
            .studio-fhalo {
                position: fixed;
                z-index: 2147483003;
                pointer-events: none;
                border-radius: 3px;
                box-shadow: inset 0 0 0 1.5px #4c7dfa;
                opacity: 0;
                transition: opacity 100ms ease;
            }

            .studio-fhalo.is-item {
                box-shadow: inset 0 0 0 1.5px #e08c2e;
                border-radius: 5px;
            }

            .studio-fhalo.is-code {
                box-shadow: inset 0 0 0 1.5px rgba(148, 148, 158, 0.55);
            }

            .studio-fhalo.is-on {
                opacity: 1;
            }

            .studio-fhalo.is-selected {
                box-shadow: inset 0 0 0 2px #4c7dfa;
            }

            .studio-fchip {
                position: fixed;
                z-index: 2147483004;
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 2px 7px 3px;
                border-radius: 4px 4px 0 0;
                background: #4c7dfa;
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 10.5px;
                font-weight: 600;
                line-height: 1.45;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transition: opacity 100ms ease;
            }

            .studio-fchip.is-item { background: #e08c2e; }
            .studio-fchip.is-code { background: rgba(88, 88, 98, 0.92); }
            .studio-fchip.is-on { opacity: 1; }

            .studio-fchip-src {
                font-weight: 500;
                opacity: 0.75;
                font-variant-numeric: tabular-nums;
            }

            /* Preview mode owns the canvas — no field chrome at all */
            html.studio-preview .studio-fhalo,
            html.studio-preview .studio-fchip {
                display: none !important;
            }
```

Add the two overlay elements just before the closing of the body content (immediately before the final `</x-studio::layouts.iframe>` line is fine — they are `position: fixed`):

```blade
    {{-- Field/item tier overlay, positioned from JS --}}
    <div class="studio-fhalo" id="studio-fhalo"></div>
    <div class="studio-fchip" id="studio-fchip"></div>
```

- [ ] **Step 3: Add the tier logic to `StudioPreview`**

Add these properties beside the existing `selectedId: null,`:

```js
    // Three tiers: the section (today's behaviour), a repeater item, and a
    // single field. Esc walks up one tier at a time.
    selection: { tier: 'section', sectionId: null, path: null, key: null, index: null },
    hovered: null,
```

Add these methods to `StudioPreview`, after `clearSelection()`:

```js
    /* --- field + item tiers --------------------------------------- */

    /** The tier under a point: a field beats an item beats the section. */
    tierAt(sectionId, x, y) {
        const entry = StudioFields.at(sectionId, x, y);

        if (entry) return { tier: 'field', entry };

        const item = StudioFields.itemAt(sectionId, x, y);

        if (item) return { tier: 'item', item };

        return { tier: 'section' };
    },

    /** Paint the hover halo + chip for whatever is under the pointer. */
    hoverAt(event) {
        if (this.mode === 'preview') return this.clearHover();

        const wrapper = event.target.closest?.('[data-section]');

        if (!wrapper) return this.clearHover();

        const sectionId = wrapper.dataset.section;
        const hit = this.tierAt(sectionId, event.clientX, event.clientY);

        if (hit.tier === 'section') {
            // Inside the rendered markup but on nothing Studio owns
            const inContent = !!event.target.closest?.('[data-section-content]');

            return inContent ? this.paintHalo(null, 'code', event) : this.clearHover();
        }

        this.paintHalo(hit, hit.tier, event);
    },

    paintHalo(hit, kind, event) {
        const halo = document.getElementById('studio-fhalo');
        const chip = document.getElementById('studio-fchip');

        if (!halo || !chip) return;

        let box = null;
        let label = 'Set in code';
        let source = '';

        if (kind === 'field') {
            const sectionId = this.sectionIdAt(event);
            box = StudioFields.box(hit.entry);
            label = this.labelFor(hit.entry, sectionId);
            const path = StudioFields.sourceFor(sectionId);
            source = path ? path.split('/').pop() + ':' + hit.entry.line : '';
        } else if (kind === 'item') {
            const rect = hit.item.el.getBoundingClientRect();
            box = { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
            label = this.itemLabel(hit.item);
        } else {
            const rect = event.target.getBoundingClientRect?.();
            if (rect) box = { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
        }

        if (!box || box.width === 0) return this.clearHover();

        halo.className = 'studio-fhalo is-on' + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : '');
        halo.style.left = box.left + 'px';
        halo.style.top = box.top + 'px';
        halo.style.width = box.width + 'px';
        halo.style.height = box.height + 'px';

        chip.className = 'studio-fchip is-on' + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : '');
        chip.innerHTML = '';
        chip.appendChild(document.createTextNode(label));

        if (source && document.documentElement.classList.contains('studio-devmode')) {
            const span = document.createElement('span');
            span.className = 'studio-fchip-src';
            span.textContent = source;
            chip.appendChild(span);
        }

        chip.style.left = box.left + 'px';
        chip.style.top = Math.max(0, box.top - 18) + 'px';

        this.hovered = { kind, hit };
    },

    clearHover() {
        document.getElementById('studio-fhalo')?.classList.remove('is-on');
        document.getElementById('studio-fchip')?.classList.remove('is-on');
        this.hovered = null;
    },

    sectionIdAt(event) {
        return event.target.closest?.('[data-section]')?.dataset.section || null;
    },

    /**
     * The chip's wording. The author's own yml label wins — it is what the
     * inspector shows, so the canvas and the panel name the same thing the
     * same way. A repeater sub-field and an undeclared key fall back to a
     * humanised key.
     */
    labelFor(entry, sectionId) {
        if (entry.index === null) {
            const contract = StudioFields.contractFor(sectionId, entry.key);

            if (contract?.label) return contract.label;
        }

        const key = entry.subKey || entry.key;
        const words = key.replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/[_-]+/g, ' ');

        return words.charAt(0).toUpperCase() + words.slice(1);
    },

    itemLabel(item) {
        const singular = item.key.replace(/ies$/, 'y').replace(/s$/, '');

        return singular.charAt(0).toUpperCase() + singular.slice(1);
    },

    selectField(entry, sectionId) {
        this.selection = {
            tier: 'field',
            sectionId,
            path: entry.path,
            key: entry.key,
            index: entry.index,
        };

        this.post('studio:field-selected', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            path: entry.path,
            line: entry.line,
            source: StudioFields.sourceFor(sectionId),
            label: this.labelFor(entry, sectionId),
        });
    },

    selectItem(item, sectionId) {
        this.selection = { tier: 'item', sectionId, path: item.id, key: item.key, index: item.index };
    },

    /** Esc: field → item (when the field is in one) → section → nothing. */
    walkUp() {
        const { tier, sectionId, key, index } = this.selection;

        if (tier === 'field' && index !== null) {
            const item = (StudioFields.maps[sectionId]?.items || []).find((i) => i.key === key && i.index === index);

            if (item) {
                this.selectItem(item, sectionId);

                return true;
            }
        }

        if (tier === 'field' || tier === 'item') {
            this.selection = { tier: 'section', sectionId, path: null, key: null, index: null };
            this.clearHover();

            return true;
        }

        return false;   // already at section tier — the editor deselects
    },
```

- [ ] **Step 4: Wire the pointer and keyboard**

In `StudioPreview.init()`, after the existing `document.addEventListener('submit', …)` line, add:

```js
        document.addEventListener('mousemove', (event) => this.hoverAt(event), { passive: true });
        document.addEventListener('mouseleave', () => this.clearHover());
```

In `StudioPreview.select(sectionId, event)`, insert the tier resolution immediately after the `if (this.mode === 'preview') return;` guard:

```js
        // A click resolves to the deepest tier under the pointer; only a
        // click on section chrome selects the section itself.
        if (event) {
            const hit = this.tierAt(sectionId, event.clientX, event.clientY);

            if (hit.tier === 'field') {
                this.applySelection(sectionId, false);
                this.selectField(hit.entry, sectionId);
                this.post('studio:section-selected', { sectionId });

                return;
            }

            if (hit.tier === 'item') {
                this.applySelection(sectionId, false);
                this.selectItem(hit.item, sectionId);
                this.post('studio:section-selected', { sectionId });

                return;
            }
        }

        this.selection = { tier: 'section', sectionId, path: null, key: null, index: null };
```

In the iframe's `keydown` handler in `init()`, handle Escape before it is forwarded. Replace the existing block:

```js
            // While the context menu is open, Escape only closes it
            if (event.key === 'Escape' && this.menu) {
                event.preventDefault();
                this.closeMenu();
                return;
            }
```

with:

```js
            // While the context menu is open, Escape only closes it
            if (event.key === 'Escape' && this.menu) {
                event.preventDefault();
                this.closeMenu();
                return;
            }

            // Then Escape walks up the selection tiers before the editor
            // ever sees it as "deselect the section".
            if (event.key === 'Escape' && this.walkUp()) {
                event.preventDefault();
                return;
            }
```

- [ ] **Step 5: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

Reload `/studio` in Edit mode on the Monarch home page and check each of these:

1. Hover the big headline — a tight blue halo hugs only `We design and build`, not the whole `<h1>`, and the chip reads the yml label `Headline — before the highlight`. Move onto the lime highlighted words — the halo moves to just those, chip `Headline — highlighted words`.
2. Hover the hero photograph — halo around the `<img>`, chip `Image`.
3. Hover a team/logo repeater card — an **orange** halo around the whole card with a singularised chip.
4. Hover the decorative star SVGs in the rating pill — a **grey** halo, chip `Set in code`.
5. Hover the padding between sections — the existing blue section outline and name chip, unchanged.
6. Click the headline, then press Esc repeatedly: field → section → deselected. In a repeater card, click a sub-field then Esc: field → item → section → deselected.
7. Switch to Preview mode: no halo, no chip, links navigate as before.

Also confirm `Studio.preview.selection` in the console reports the expected tier after each click.

- [ ] **Step 6: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/iframe.blade.php dist
git commit -m "Add field and repeater-item selection tiers to the canvas

Hovering resolves to the deepest tier under the pointer — field, item,
or section — and paints a halo and chip for it. Markup with no field
behind it gets the grey 'Set in code' treatment rather than pretending
to be editable. Esc walks up one tier at a time.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 6: The oversized type cursor

**Files:**
- Modify: `resources/views/iframe.blade.php` (cursor CSS + element)
- Modify: `resources/js/studio.js` (`StudioPreview.cursor`)

**Interfaces:**
- Consumes: `StudioPreview.hovered` (Task 5).
- Produces: `StudioPreview.cursor.show(kind)`, `.hide()`, `.track(event)` where `kind` is `'text' | 'image' | 'url' | 'toggle' | 'select' | 'color' | 'item' | 'code'`.

- [ ] **Step 1: Write the failing test**

Manual gate in the canvas console:

```js
Studio.preview.cursor
```

Expected now: `undefined`.

- [ ] **Step 2: Add the cursor element and styles**

In `resources/views/iframe.blade.php`, add to the head style block:

```css
            /* ---- Oversized type cursor ---- */
            .studio-cursor {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 2147483006;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                margin: 14px 0 0 14px;
                border-radius: 8px 8px 8px 2px;
                background: #4c7dfa;
                color: #fff;
                box-shadow: 0 4px 12px -2px rgba(12, 12, 20, 0.4);
                pointer-events: none;
                opacity: 0;
                transform: translate3d(-100px, -100px, 0) scale(0.8);
                transition: opacity 90ms ease, transform 90ms ease, background-color 120ms ease;
                will-change: transform;
            }

            .studio-cursor.is-on {
                opacity: 1;
            }

            .studio-cursor.is-item { background: #e08c2e; border-radius: 8px 8px 2px 8px; }
            .studio-cursor.is-code { background: rgba(70, 70, 80, 0.92); }

            .studio-cursor svg { width: 14px; height: 14px; }
            .studio-cursor span { font-size: 13px; font-weight: 700; line-height: 1; }

            html.studio-preview .studio-cursor { display: none !important; }
```

Add the element beside the halo and chip:

```blade
    <div class="studio-cursor" id="studio-cursor"></div>
```

- [ ] **Step 3: Write the cursor controller**

Add this to `StudioPreview`, after `clearHover()`:

```js
    /**
     * The oversized type cursor: one badge that follows the pointer and
     * names what is under it. The native cursor is kept — an I-beam over
     * text is correct while editing — so this reads as a type indicator
     * rather than a cursor replacement.
     */
    cursor: {
        el: null,
        kind: null,
        x: 0,
        y: 0,
        queued: false,

        glyphs: {
            text: '<span>T</span>',
            image: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h9A2.5 2.5 0 0 1 17 5.5v9a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 3 14.5v-9Zm3 1.25a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm8.5 7.75-3.6-4.5-2.6 3.1-1.4-1.6L5 15h9.5Z"/></svg>',
            url: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4h6a1 1 0 0 1 0 2H8.9l6.8 6.8a1 1 0 0 1-1.4 1.4L7.5 7.4v3.6a1 1 0 1 1-2 0V5a1 1 0 0 1 1-1Z"/></svg>',
            toggle: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 5.5h6a4.5 4.5 0 1 1 0 9H7a4.5 4.5 0 1 1 0-9Zm6 7a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg>',
            select: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.2 7.7a1 1 0 0 1 1.4 0L10 11.1l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 9.1a1 1 0 0 1 0-1.4Z"/></svg>',
            color: '<svg viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="6"/></svg>',
            item: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 4.5h13v3h-13v-3Zm0 4.75h13v3h-13v-3Zm0 4.75h13v3h-13v-3Z"/></svg>',
            code: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M7.6 5.2a1 1 0 0 1 .2 1.4L5.25 10l2.55 3.4a1 1 0 1 1-1.6 1.2l-3-4a1 1 0 0 1 0-1.2l3-4a1 1 0 0 1 1.4-.2Zm4.8 0a1 1 0 0 1 1.4.2l3 4a1 1 0 0 1 0 1.2l-3 4a1 1 0 1 1-1.6-1.2L14.75 10 12.2 6.6a1 1 0 0 1 .2-1.4Z"/></svg>',
        },

        mount() {
            this.el = document.getElementById('studio-cursor');
        },

        show(kind) {
            if (!this.el) return;

            if (kind !== this.kind) {
                this.kind = kind;
                this.el.innerHTML = this.glyphs[kind] || this.glyphs.text;
                this.el.className = 'studio-cursor is-on'
                    + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : '');
            }

            this.el.classList.add('is-on');
        },

        hide() {
            this.kind = null;
            this.el?.classList.remove('is-on');
        },

        track(event) {
            this.x = event.clientX;
            this.y = event.clientY;

            if (this.queued || !this.el) return;

            this.queued = true;
            requestAnimationFrame(() => {
                this.queued = false;
                this.el.style.transform = `translate3d(${this.x}px, ${this.y}px, 0) scale(1)`;
            });
        },
    },

    /** Which cursor glyph a hovered field deserves. */
    cursorKind(hit, kind) {
        if (kind === 'item') return 'item';
        if (kind === 'code') return 'code';

        const entry = hit.entry;

        if (entry.kind === 'when') return 'toggle';

        if (entry.kind === 'attr') {
            if (entry.attribute === 'src' || entry.attribute === 'srcset') return 'image';
            if (entry.attribute === 'href') return 'url';

            return 'text';
        }

        return 'text';
    },
```

- [ ] **Step 4: Drive it from hover**

In `StudioPreview.init()`, after the `StudioFields.init(paths);` line add:

```js
        this.cursor.mount();
```

Change the `mousemove` listener added in Task 5 to also track the cursor:

```js
        document.addEventListener('mousemove', (event) => {
            this.cursor.track(event);
            this.hoverAt(event);
        }, { passive: true });

        document.addEventListener('mouseleave', () => {
            this.clearHover();
            this.cursor.hide();
        });
```

At the end of `paintHalo()`, just after `this.hovered = { kind, hit };`, add:

```js
        this.cursor.show(this.cursorKind(hit || {}, kind));
```

And in `clearHover()`, add `this.cursor.hide();` as the last line.

- [ ] **Step 5: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

Reload `/studio` and move the pointer across the hero:

- over headline text → blue badge with a **T**
- over the photograph → blue badge with a picture glyph
- over a button (`href` is a mapped `url` field) → blue badge with an arrow glyph
- over a repeater card's padding → **orange** badge with a rows glyph
- over the decorative stars → **grey** badge with `</>`
- the badge trails the pointer smoothly with no visible jank while scrolling
- Preview mode → no badge at all

- [ ] **Step 6: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/iframe.blade.php dist
git commit -m "Add the oversized type cursor to the canvas

One badge follows the pointer and names what is under it — text, image,
link, toggle, repeater item, or a grey </> over markup Studio does not
own. The native cursor is kept so text editing still feels like text.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 7: Type on the canvas

**Files:**
- Modify: `resources/js/studio.js` (`StudioPreview.beginEdit`, `commitEdit`, `cancelEdit`; `paint()` suppression; the `studio:field-committed` post)
- Modify: `resources/js/studio.js` (`StudioEditor.listenToIframe()` — relay to Livewire)
- Modify: `src/Livewire/EditorPanel.php` (`setFieldFromCanvas`)
- Modify: `resources/views/iframe.blade.php` (editing CSS)

**Interfaces:**
- Consumes: `StudioPreview.selectField()` (Task 5), `StudioFields` entries (Task 4).
- Produces:
  - postMessage `studio:field-committed` `{sectionId, key, index, subKey, value}`
  - Livewire event `studio:set-field` with the same payload
  - `EditorPanel::setFieldFromCanvas(string $sectionId, string $key, $value, ?int $index = null, ?string $subKey = null): void`

- [ ] **Step 1: Write the failing test**

Manual gate: open `/studio` in Edit mode, click the hero headline, and try to type. Expected now: nothing happens — the click selects the field but the text is not editable.

- [ ] **Step 2: Add the editing styles**

In `resources/views/iframe.blade.php`, add to the head style block:

```css
            /* ---- Inline text editing ---- */
            [data-sf-edit],
            [contenteditable="true"],
            [contenteditable="plaintext-only"] {
                outline: none;
                background: rgba(76, 125, 250, 0.1);
                box-shadow: 0 0 0 1.5px #4c7dfa;
                border-radius: 2px;
            }

            /* The halo and chip would only fight the caret while typing */
            html.studio-editing .studio-fhalo,
            html.studio-editing .studio-fchip,
            html.studio-editing .studio-cursor {
                opacity: 0 !important;
            }
```

- [ ] **Step 3: Add the editing methods to `StudioPreview`**

Add these after `selectField()`:

```js
    /* --- inline text editing -------------------------------------- */

    // The section whose markup must not be repainted: its DOM is the truth
    // while the caret is in it.
    editing: null,

    /**
     * Make a text field editable in place.
     *
     * When the sentinel range is the whole content of its parent (the common
     * case, `<p>{{ $body }}</p>`) the parent becomes editable directly.
     * Otherwise the range is wrapped in a transient span — safe, because it
     * exists only while the caret is in it and is unwrapped on exit.
     */
    beginEdit(entry, sectionId, multiline) {
        if (this.editing) this.commitEdit();

        if (entry.kind !== 'text' || !entry.range) return false;

        const parent = entry.range.commonAncestorContainer.nodeType === 1
            ? entry.range.commonAncestorContainer
            : entry.range.commonAncestorContainer.parentElement;

        if (!parent) return false;

        let host = parent;
        let wrapper = null;

        // Does the range already cover everything the parent contains?
        const whole = document.createRange();
        whole.selectNodeContents(parent);

        const sameStart = whole.compareBoundaryPoints(Range.START_TO_START, entry.range) === 0;
        const sameEnd = whole.compareBoundaryPoints(Range.END_TO_END, entry.range) === 0;

        if (!sameStart || !sameEnd) {
            wrapper = document.createElement('span');
            wrapper.setAttribute('data-sf-edit', '');

            try {
                entry.range.surroundContents(wrapper);
            } catch (e) {
                return false;   // the range crosses an element boundary
            }

            host = wrapper;
        }

        host.setAttribute('contenteditable', this.plaintextMode());
        host.focus();

        // Put the caret where the user clicked rather than selecting all
        const selection = window.getSelection();
        selection.removeAllRanges();
        const caret = document.createRange();
        caret.selectNodeContents(host);
        selection.addRange(caret);

        this.editing = {
            sectionId,
            entry,
            host,
            wrapper,
            multiline,
            original: host.innerText,
        };

        document.documentElement.classList.add('studio-editing');

        host.addEventListener('keydown', this.editKeydown);
        host.addEventListener('paste', this.editPaste);
        host.addEventListener('blur', this.editBlur);

        return true;
    },

    /** `plaintext-only` where supported; plain contenteditable plus a paste
     *  handler everywhere else. */
    plaintextMode() {
        if (this._plaintext === undefined) {
            const probe = document.createElement('div');
            probe.setAttribute('contenteditable', 'plaintext-only');
            this._plaintext = probe.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true';
        }

        return this._plaintext;
    },

    editKeydown(event) {
        const state = StudioPreview.editing;

        if (!state) return;

        if (event.key === 'Enter' && !state.multiline) {
            event.preventDefault();
            StudioPreview.commitEdit();

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            StudioPreview.cancelEdit();
        }
    },

    editPaste(event) {
        // Only needed on the fallback path — plaintext-only handles itself
        if (StudioPreview.plaintextMode() === 'plaintext-only') return;

        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    },

    editBlur() {
        StudioPreview.commitEdit();
    },

    /** Read the value back out of the DOM and hand it to the editor. */
    commitEdit() {
        const state = this.editing;

        if (!state) return;

        this.editing = null;

        const value = this.readValue(state.host);

        this.teardownEdit(state);

        if (value === state.original) return;

        const { entry, sectionId } = state;

        // Keep the client's copy current so a later re-render is correct
        if (entry.index === null) {
            this.variables[sectionId] = this.variables[sectionId] || {};
            this.variables[sectionId][entry.key] = value;
        }

        this.post('studio:field-committed', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            value,
        });
    },

    cancelEdit() {
        const state = this.editing;

        if (!state) return;

        this.editing = null;
        state.host.innerText = state.original;
        this.teardownEdit(state);
    },

    teardownEdit(state) {
        state.host.removeEventListener('keydown', this.editKeydown);
        state.host.removeEventListener('paste', this.editPaste);
        state.host.removeEventListener('blur', this.editBlur);
        state.host.removeAttribute('contenteditable');

        if (state.wrapper && state.wrapper.parentNode) {
            const parent = state.wrapper.parentNode;
            while (state.wrapper.firstChild) parent.insertBefore(state.wrapper.firstChild, state.wrapper);
            parent.removeChild(state.wrapper);
            parent.normalize();
        }

        document.documentElement.classList.remove('studio-editing');
        window.getSelection()?.removeAllRanges();

        // The DOM moved under the map — rebuild it for this section
        StudioFields.index(document.querySelector(`[data-section="${state.sectionId}"]`));
    },

    /**
     * contenteditable produces <br>/<div> for line breaks and leaves
     * non-breaking spaces behind where it padded the caret; normalise both
     * so the saved value is the text the user believes they typed.
     */
    readValue(host) {
        return host.innerText
            .replace(/\u00a0/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    },
```

- [ ] **Step 4: Start editing on click, and suppress the repaint**

In `StudioPreview.select()`, in the `hit.tier === 'field'` branch added in Task 5, begin editing for text fields. Replace that branch's body with:

```js
            if (hit.tier === 'field') {
                this.applySelection(sectionId, false);
                this.selectField(hit.entry, sectionId);
                this.post('studio:section-selected', { sectionId });

                // Text fields become editable straight away; every other type
                // selects and lets the inspector own the input.
                if (hit.entry.kind === 'text') {
                    this.beginEdit(hit.entry, sectionId, this.isMultiline(hit.entry, sectionId));
                }

                return;
            }
```

Add the helper beside `readValue()`:

```js
    /**
     * A textarea field takes Enter as a newline; a text field commits on it.
     * This reads the declared type rather than sniffing the current value,
     * so an empty textarea still behaves like one.
     */
    isMultiline(entry, sectionId) {
        if (entry.index !== null) return false;   // repeater sub-fields are single-line in v1

        return StudioFields.contractFor(sectionId, entry.key)?.type === 'textarea';
    },
```

In `StudioPreview.paint()`, add the suppression guard as the very first lines of the method:

```js
    paint(sectionId, markup) {
        // The caret is in this section — its DOM already shows the truth, and
        // replacing innerHTML would destroy the selection mid-keystroke.
        if (this.editing && this.editing.sectionId === sectionId) return;
```

- [ ] **Step 5: Relay the commit to Livewire**

In `StudioEditor.listenToIframe()`, add a case to the switch, after `case 'studio:element-selected':`:

```js
                case 'studio:field-committed':
                    window.Livewire?.dispatch('studio:set-field', {
                        sectionId: data.sectionId,
                        key: data.key,
                        value: data.value,
                        index: data.index,
                        subKey: data.subKey,
                    });
                    break;
```

- [ ] **Step 6: Persist it in `EditorPanel`**

Add this method to `src/Livewire/EditorPanel.php`, immediately after `setVariable()`:

```php
    /**
     * A value typed directly on the canvas.
     *
     * The canvas is already showing the new text, so unlike
     * {@see setVariable()} this deliberately does not echo the value back
     * to the iframe — repainting would destroy the caret. Everything else
     * (global blocks, layout sections, conflict guarding, site-bound
     * fields) is the panel's own save path, so behaviour is identical.
     */
    #[On('studio:set-field')]
    public function setFieldFromCanvas(string $sectionId, string $key, $value, ?int $index = null, ?string $subKey = null): void
    {
        if (!isset($this->variables[$sectionId])) {
            return;
        }

        if ($index !== null && $subKey !== null) {
            $this->updateRepeaterSubField($sectionId, $key, $index, $subKey, (string) $value);

            return;
        }

        $this->variables[$sectionId][$key] = $value;
        $this->saveVariables($sectionId);
    }
```

- [ ] **Step 7: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

On the Monarch home page in Edit mode:

1. Click the hero headline's first phrase and type. The text changes under the caret, the halo and cursor fade out, and **the caret does not jump**. Click away — the inspector's `Headline — before the highlight` input now shows the new text, and the save dot reads Saved.
2. Reload the page — the new text is still there (it persisted to the page doc).
3. Edit the body paragraph, press Enter — a newline is inserted, not a commit.
4. Edit the headline, press Enter — it commits and exits.
5. Edit any field, press Escape — the original text returns and nothing is saved.
6. Edit a field inside a repeater card (a team member's name) — it saves to that row; check the Content/inspector repeater shows the change.
7. Put the same global block on the page twice, edit it on the canvas, and confirm both copies show the new value after blur.
8. Confirm typing produces **no** requests to `/studio/api/render` in the Network tab, and exactly one Livewire request on blur.

- [ ] **Step 8: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/iframe.blade.php src/Livewire/EditorPanel.php dist
git commit -m "Type into the canvas

Clicking a text field makes it editable in place — the parent directly
where the field owns all of it, a transient span where it shares an
element with siblings. paint() stands down for the section holding the
caret, and the value commits through EditorPanel's own save path on
blur: one round trip per edit, none per keystroke.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 8: Canvas and inspector in step

**Files:**
- Modify: `resources/js/studio.js` (`StudioEditor`: relay `studio:field-selected`)
- Modify: `resources/views/livewire/editor-panel.blade.php` (focus/highlight handler + `data-field-key` on each row)
- Modify: `resources/css/studio.css` (the flash style)

**Interfaces:**
- Consumes: `studio:field-selected` postMessage (Task 5).
- Produces: window event `studio:field-focus` with `{sectionId, key, index, subKey, label}`; each inspector field row carries `data-field-key="<key>"`.

- [ ] **Step 1: Write the failing test**

Manual gate: in Edit mode click a field far down a long section (the hero's `Stat caption`). Expected now: the inspector opens the section but does not scroll to or highlight that input.

- [ ] **Step 2: Relay the selection to the panel**

In `StudioEditor.listenToIframe()`, add a case after `studio:field-committed`:

```js
                case 'studio:field-selected':
                    window.dispatchEvent(new CustomEvent('studio:field-focus', { detail: data }));
                    break;
```

- [ ] **Step 3: Add the flash style**

Append to `resources/css/studio.css`, with the other `s-*` component classes:

```css
/* An inspector field the canvas just selected — a brief, quiet flash so the
   eye finds it without the panel jumping around. */
@keyframes s-field-flash {
    from { background-color: color-mix(in oklab, var(--color-accent) 22%, transparent); }
    to   { background-color: transparent; }
}

.s-field-flash {
    animation: s-field-flash 900ms ease-out;
    border-radius: 6px;
}
```

- [ ] **Step 4: Handle it in the inspector**

In `resources/views/livewire/editor-panel.blade.php`, locate the field loop:

```bash
grep -n "livewire.fields\.\|@foreach" resources/views/livewire/editor-panel.blade.php
```

The inspector renders one wrapper element per field around an
`@include('studio::livewire.fields.' . $type, …)`. Add `data-field-key="{{ $key }}"`
to that wrapper — it is the anchor the focus handler scrolls to, so it must sit
on the element that contains both the label and the input:

```blade
        <div class="space-y-1.5" data-field-key="{{ $key }}">
```

If the loop variable is not named `$key`, use whatever name it binds the field
key to; the attribute value must be the raw key (`headingStart`), not the label.

Then add the listener to the component's `x-data` object, beside the existing `preview(sectionId, key, value)` method:

```js
        /**
         * The canvas selected a field — bring the matching input into view
         * and flash it, so clicking text on the page and reading its
         * settings are the same gesture.
         */
        focusField(detail) {
            this.$nextTick(() => {
                const row = this.$root.querySelector(`[data-field-key="${detail.key}"]`);

                if (!row) return;

                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.classList.remove('s-field-flash');
                void row.offsetWidth;              // restart the animation
                row.classList.add('s-field-flash');
            });
        },
```

Add the binding to the same root element that already carries `x-data`:

```blade
    x-on:studio:field-focus.window="focusField($event.detail)"
```

- [ ] **Step 5: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

Reload `/studio`. Click the hero's `6 weeks` stat figure on the canvas: the inspector scrolls down to `Stat figure` and flashes it. Click the headline: it scrolls back up and flashes `Headline — before the highlight`. Type in the panel input and confirm the canvas still live-renders as before (the existing `preview()` path is untouched).

- [ ] **Step 6: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/livewire/editor-panel.blade.php resources/css/studio.css dist
git commit -m "Scroll the inspector to the field the canvas selected

Clicking text on the page and reading its settings become one gesture:
the matching input scrolls into view and flashes.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 9: Bidirectional code provenance

**Files:**
- Modify: `resources/js/studio.js` (`StudioPreview`: ⌥-click; `StudioEditor`: the `studio:open-code-at` case)
- Modify: `resources/views/home.blade.php` (`$store.code.openFileAt()`, Monaco cursor listener)

**Interfaces:**
- Consumes: `StudioFields.sourceFor()`, entry `line` (Task 4); `$store.code.openFile(path)` and `studioCodeBuffers.editor` (existing).
- Produces:
  - postMessage `studio:open-code-at` `{path, line}` where `path` is app-relative.
  - `$store.code.openFileAt(path, line)` — opens the file and reveals the line.
  - postMessage `studio:highlight-field` `{ref, line}` (editor → iframe) and `StudioPreview.highlightLine(ref, line)`.

- [ ] **Step 1: Write the failing test**

Manual gate: with dev mode on, ⌥-click the hero headline on the canvas. Expected now: nothing happens (a normal field selection).

- [ ] **Step 2: Post the jump from the canvas**

In `StudioPreview.select()`, at the very top of the `hit.tier === 'field'` branch (before `applySelection`), add:

```js
                // ⌥-click jumps to the line of Blade that rendered this text
                if (event.altKey && document.documentElement.classList.contains('studio-devmode')) {
                    const source = StudioFields.sourceFor(sectionId);

                    if (source) {
                        event.preventDefault();
                        this.post('studio:open-code-at', {
                            path: 'resources/designer/views/components/' + source + '.blade.php',
                            line: hit.entry.line,
                        });

                        return;
                    }
                }
```

- [ ] **Step 3: Relay it in `StudioEditor`**

Add a case to `listenToIframe()`'s switch:

```js
                case 'studio:open-code-at':
                    window.Alpine?.store('studio')?.setMode('code');
                    window.Alpine?.store('code')?.openFileAt(data.path, data.line);
                    break;
```

- [ ] **Step 4: Add `openFileAt` to the code store**

In `resources/views/home.blade.php`, add this method to the `code` store, immediately after `openFile(path)`:

```js
                /**
                 * Open a file and put the caret on one line — the landing
                 * half of ⌥-clicking a field on the canvas.
                 */
                async openFileAt(path, line) {
                    await this.openFile(path);

                    const editor = studioCodeBuffers.editor;

                    if (!editor || !line) return;

                    editor.revealLineInCenter(line);
                    editor.setPosition({ lineNumber: line, column: 1 });
                    editor.setSelection({ startLineNumber: line, startColumn: 1, endLineNumber: line + 1, endColumn: 1 });
                    editor.focus();
                },
```

- [ ] **Step 5: Add the reverse direction**

Still in `home.blade.php`, inside the `mount()` method, after `studioCodeBuffers.editor = wrapper.editor;`, attach the cursor listener:

```js
                            // Code → canvas: moving the caret inside a section
                            // file haloes the text that line renders.
                            studioCodeBuffers.editor.onDidChangeCursorPosition((event) => {
                                const path = this.active || '';
                                const match = path.match(/^resources\/designer\/views\/components\/(.+)\.blade\.php$/);

                                if (!match) return;

                                window.dispatchEvent(new CustomEvent('studio:to-iframe', {
                                    detail: {
                                        type: 'studio:highlight-field',
                                        source: match[1],
                                        line: event.position.lineNumber,
                                    },
                                }));
                            });
```

- [ ] **Step 6: Handle the highlight in the canvas**

Add a case to `StudioPreview.init()`'s message switch:

```js
                case 'studio:highlight-field':
                    this.highlightLine(data.source, data.line);
                    break;
```

And the method, after `selectField()`:

```js
    /**
     * The caret moved onto a line of a section file — halo whatever that
     * line renders, so the code and the page point at each other.
     */
    highlightLine(source, line) {
        if (this.mode === 'preview') return;

        for (const wrapper of document.querySelectorAll('[data-section]')) {
            const sectionId = wrapper.dataset.section;

            if (StudioFields.sourceFor(sectionId) !== source) continue;

            const entry = StudioFields.entriesFor(sectionId).find((candidate) => candidate.line === line);

            if (!entry) continue;

            const box = StudioFields.box(entry);

            if (!box) continue;

            const halo = document.getElementById('studio-fhalo');
            const chip = document.getElementById('studio-fchip');

            halo.className = 'studio-fhalo is-on';
            halo.style.left = box.left + 'px';
            halo.style.top = box.top + 'px';
            halo.style.width = box.width + 'px';
            halo.style.height = box.height + 'px';

            chip.className = 'studio-fchip is-on';
            chip.textContent = this.labelFor(entry, sectionId);
            chip.style.left = box.left + 'px';
            chip.style.top = Math.max(0, box.top - 18) + 'px';

            wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });

            return;
        }
    },
```

- [ ] **Step 7: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

With dev mode on (hamburger → Dev mode):

1. Hover the headline — the chip shows `Headline — before the highlight` **and** `hero.blade.php:41` (or whatever the real line is).
2. ⌥-click it — Studio switches to Code mode, opens `resources/designer/views/components/sections/hero.blade.php`, and the caret lands on that exact line with it selected. Confirm the line really does contain `{{ $headingStart }}`.
3. Turn on the Code-mode split so the preview is beside the editor. Click around lines in the Blade file: each time the caret lands on a line holding a field echo, the canvas scrolls to and haloes the rendered text.
4. Turn dev mode off — the chip shows no line number and ⌥-click selects normally.

- [ ] **Step 8: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/home.blade.php dist
git commit -m "Point the canvas and the code at each other

Every field carries the line of Blade that rendered it, so the chip
shows hero.blade.php:41 and option-click opens Code mode right there.
The reverse works too: move the caret onto an echo and the canvas
haloes the text it produces.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 10: Give the Assistant the field path

**Files:**
- Modify: `resources/js/studio.js` (`StudioPreview.setElementSelect` handler — enrich the payload)
- Modify: `resources/views/livewire/assistant-panel.blade.php` (`send()` — widen the element whitelist)
- Modify: `src/Services/Assistant/SystemPrompt.php` (`selectionContext()` at ~line 110, and the `@param` shape docblock at ~line 30)

**Interfaces:**
- Consumes: `StudioFields.at()`, `sourceFor()` (Task 4).
- Produces: `studio:element-selected` gains `field`, `itemIndex`, `subKey`, `source` (e.g. `sections/hero.blade.php:43`).

- [ ] **Step 1: Write the failing test**

Manual gate: with dev mode on, open the Assistant panel, click the crosshair, and click the hero headline. Expected now: the context chip names the element and its tag, but the turn has no idea which field it is.

- [ ] **Step 2: Enrich the crosshair payload**

In `StudioPreview`, find `this.elementSelectHandler = (event) => {` inside `setElementSelect()`. Just before the `this.post('studio:element-selected', …)` call inside it, resolve the field, then add the four keys to the posted payload:

```js
                const wrapper = event.target.closest('[data-section]');
                const sectionId = wrapper?.dataset.section || null;
                const entry = sectionId ? StudioFields.at(sectionId, event.clientX, event.clientY) : null;
                const source = sectionId ? StudioFields.sourceFor(sectionId) : null;
```

and in the posted object:

```js
                    field: entry ? entry.key : null,
                    itemIndex: entry ? entry.index : null,
                    subKey: entry ? entry.subKey : null,
                    source: entry && source ? source + '.blade.php:' + entry.line : null,
```

- [ ] **Step 3: Let the new keys reach the turn**

`assistant-panel.blade.php`'s `send()` copies the element context key by key, so
anything not named there is dropped before it reaches the server. Find the line:

```js
            if (this.element) context.element = { path: this.element.path, tag: this.element.tag, text: this.element.text };
```

and widen it:

```js
            if (this.element) context.element = {
                path: this.element.path,
                tag: this.element.tag,
                text: this.element.text,
                field: this.element.field,
                itemIndex: this.element.itemIndex,
                subKey: this.element.subKey,
                source: this.element.source,
            };
```

Update the shape comment on the `element:` property near the top of the same
`x-data` block to match:

```js
        element: null,          // {sectionId, ref, path, tag, text, field, itemIndex, subKey, source}
```

- [ ] **Step 4: State it in the system prompt**

In `src/Services/Assistant/SystemPrompt.php` the selection is described by
`selectionContext(array $context): array` (~line 110), which already handles
`$context['element']`. Add the method below and call it from there — append its
line to `$lines` inside the existing `if ($element) {` block, right after the
existing `- Element inside it:` line. Also extend the `@param` shape docblock at
~line 30 from `element: {path, tag, text}` to
`element: {path, tag, text, field, itemIndex, subKey, source}`.

```php
    /**
     * The exact field the user pointed at with the crosshair.
     *
     * Without this an edit turn has to guess which prop produced the text
     * under the cursor; with it the turn can go straight to the right key
     * in the right file.
     */
    protected function selectedField(array $selection): string
    {
        if (empty($selection['field'])) {
            return '';
        }

        $path = $selection['field'];

        if (($selection['itemIndex'] ?? null) !== null && !empty($selection['subKey'])) {
            $path .= '[' . $selection['itemIndex'] . '].' . $selection['subKey'];
        }

        $line = "The user pointed at the field `{$path}`";

        if (!empty($selection['source'])) {
            $line .= ", rendered by resources/designer/views/components/{$selection['source']}";
        }

        return $line . ".\n";
    }
```

- [ ] **Step 5: Build and verify it passes**

```bash
cd <host-app>/packages/designer/studio && npm run build
```

With dev mode on: open the Assistant, click the crosshair, click the lime highlighted words in the hero. The context chip appears. Send `what field is this?` and confirm the reply names `headingHighlight` and the source file. Then click a team member's name in a repeater and confirm the reply names the field with its row index.

- [ ] **Step 6: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/livewire/assistant-panel.blade.php src/Services/Assistant/SystemPrompt.php dist
git commit -m "Tell the Assistant which field the crosshair hit

The sentinels already know, so the crosshair now reports the field key,
the repeater row, and the source line instead of leaving the turn to
infer a prop from nearby text.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

## Final verification

Run all three gates together before calling v1 done.

- [ ] **Regression gate**

```bash
cd <host-app> && php artisan studio:inline:verify
```

Expected: `Coverage: 365/369`, `Inertness: 57 identical, 0 diverged`, and the leak check passing.

- [ ] **No sentinel escapes**

```bash
cd <host-app>
curl -s http://127.0.0.1:8000/studio/preview | grep -c 'sf:\|data-sf-'   # 0
curl -s http://127.0.0.1:8000/ | grep -c 'sf:\|data-sf-'                 # 0
grep -rn 'data-sf-\|<!--sf:' resources/designer/ | wc -l                 # 0
```

- [ ] **Studio can be removed and the site still works**

Per the CLAUDE.md convention: add `"dont-discover": ["designer/studio"]` under `extra.laravel` in the host's `composer.json`, run `php artisan package:discover`, request every page (all must serve, `/studio` must 404), then revert and re-discover.

- [ ] **Publish still round-trips**

Publish from the editor after a canvas edit, then run `php artisan studio:sync` and confirm the editor shows the same content — the writer must not see any difference between a value typed on the canvas and one typed in the panel.
