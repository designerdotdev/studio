<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Site\PhpLiteral;
use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Support\DevMode;
use Designer\Studio\Support\SitePaths;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Dev mode — read and write one section's source from the editor: its
 * component (`resources/designer/views/components/<path>.blade.php`) and
 * field contract (the `.yml` beside it). Only available when the dev-mode
 * gate passes (local environment by default).
 */
class DevModeController extends Controller
{
    public function __construct(
        protected DesignSyncService $designSync
    ) {}

    public function show(string $name)
    {
        abort_unless(DevMode::enabled(), 404);

        $files = $this->designSync->sourceFiles($name);

        if (!$files) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        // The modal's two tabs keep their original keys: `html` is the Blade
        return response()->json([
            'success' => true,
            'name' => $name,
            'html' => (string) file_get_contents($files['blade']),
            'yaml' => (string) file_get_contents($files['yaml']),
            'paths' => [
                'html' => SitePaths::relative($files['blade']),
                'yaml' => SitePaths::relative($files['yaml']),
            ],
        ]);
    }

    public function update(Request $request, string $name)
    {
        abort_unless(DevMode::enabled(), 404);

        $validated = $request->validate([
            'html' => 'required|string',
            'yaml' => 'required|string',
        ]);

        // TrimStrings would eat each file's trailing newline — take the
        // contents off the raw body (validation above still guards shape)
        $raw = str_contains((string) $request->header('Content-Type'), 'json') ? json_decode($request->getContent(), true) : null;

        foreach (['html', 'yaml'] as $key) {
            if (is_array($raw) && is_string($raw[$key] ?? null)) {
                $validated[$key] = $raw[$key];
            }
        }

        try {
            Yaml::parse($validated['yaml']);
        } catch (ParseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid YAML: ' . $e->getMessage(),
            ], 422);
        }

        $files = $this->designSync->sourceFiles($name);

        if (!$files) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        File::put($files['yaml'], $validated['yaml']);
        File::put($files['blade'], $validated['html']);

        // Pull the edit into the library and the documents (a changed field
        // contract changes how the site's pages read)
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }

        app(SiteMirror::class)->sync();

        return response()->json([
            'success' => true,
            'paths' => [
                'html' => SitePaths::relative($files['blade']),
                'yaml' => SitePaths::relative($files['yaml']),
            ],
        ]);
    }

    /**
     * Promote a canvas-detected undeclared echo (`{{ $eyebrow }}` with no
     * yml entry) into a real field: appends the `.yml` field and its
     * `@props` default, so the section's contract catches up with code a
     * developer already wrote. Every input here writes source files, so
     * it is validated hard before anything touches disk.
     */
    public function promoteField(Request $request, string $name)
    {
        abort_unless(DevMode::enabled(), 404);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
        ]);

        $key = $validated['key'];

        $files = $this->designSync->sourceFiles($name);

        if (!$files) {
            return response()->json(['success' => false, 'message' => 'Section source files not found.'], 404);
        }

        $yaml = (string) file_get_contents($files['yaml']);

        try {
            $meta = Yaml::parse($yaml);
        } catch (ParseException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid YAML: ' . $e->getMessage()], 422);
        }

        $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : [];

        if (array_key_exists($key, $fields)) {
            return response()->json(['success' => false, 'message' => "\"{$key}\" is already a declared field."], 422);
        }

        $label = $this->humanizeKey($key);
        $newYaml = $this->appendYamlField($yaml, $key, $label);

        // Never persist an append we cannot prove is still valid YAML —
        // a bug in the splice above must fail loudly, not corrupt the file.
        try {
            $reparsed = Yaml::parse($newYaml);
        } catch (ParseException $e) {
            return response()->json(['success' => false, 'message' => 'Could not add that field: the result was not valid YAML.'], 500);
        }

        if (!is_array($reparsed['fields'] ?? null) || !array_key_exists($key, $reparsed['fields'])) {
            return response()->json(['success' => false, 'message' => 'Could not add that field.'], 500);
        }

        $blade = (string) file_get_contents($files['blade']);
        $newBlade = $this->appendPropDefault($blade, $key);

        // Same guarantee as the YAML side: nothing is written unless the
        // rewritten @props array is provably still valid PHP.
        if (!$this->propsArrayIsValid($blade, $newBlade, $key)) {
            return response()->json(['success' => false, 'message' => 'Could not add that field: the result was not valid PHP.'], 500);
        }

        File::put($files['yaml'], $newYaml);
        File::put($files['blade'], $newBlade);

        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }

        app(SiteMirror::class)->sync();

        return response()->json([
            'success' => true,
            'synced' => true,
            'key' => $key,
            'label' => $label,
        ]);
    }

    /**
     * Append a new text field to a section's `fields:` map, matching the
     * indentation already used by its sibling entries (falling back to the
     * project's own 4/8-space convention when the map is empty or absent)
     * rather than re-serialising the whole document through Yaml::dump(),
     * which would blow away comments and any hand-formatting.
     */
    protected function appendYamlField(string $yaml, string $key, string $label): string
    {
        $lines = explode("\n", $yaml);
        $fieldsLine = null;
        $emptyFlow = false;

        foreach ($lines as $i => $line) {
            if (preg_match('/^fields:\s*(\{\})?\s*$/', $line, $m)) {
                $fieldsLine = $i;
                $emptyFlow = ($m[1] ?? '') === '{}';

                break;
            }
        }

        $entryIndent = '    ';
        $propIndent = '        ';
        $labelYaml = trim((string) Yaml::dump($label));

        if ($fieldsLine === null) {
            $suffix = $yaml === '' || str_ends_with($yaml, "\n") ? '' : "\n";

            return $yaml . $suffix . "fields:\n"
                . $entryIndent . $key . ":\n"
                . $propIndent . "type: text\n"
                . $propIndent . "label: " . $labelYaml . "\n"
                . $propIndent . "default: \"\"\n";
        }

        if ($emptyFlow) {
            $lines[$fieldsLine] = 'fields:';
        }

        $lastContentLine = $fieldsLine;
        $sawEntryIndent = null;
        $sawPropIndent = null;

        for ($i = $fieldsLine + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];

            if (trim($line) === '') {
                continue;
            }

            if (!preg_match('/^(\s+)/', $line, $m)) {
                break;
            }

            $indent = $m[1];

            if ($sawEntryIndent === null) {
                $sawEntryIndent = $indent;
            } elseif ($sawPropIndent === null && strlen($indent) > strlen($sawEntryIndent)) {
                $sawPropIndent = $indent;
            }

            $lastContentLine = $i;
        }

        if ($sawEntryIndent !== null) {
            $entryIndent = $sawEntryIndent;
        }

        if ($sawPropIndent !== null) {
            $propIndent = $sawPropIndent;
        }

        array_splice($lines, $lastContentLine + 1, 0, [
            $entryIndent . $key . ':',
            $propIndent . 'type: text',
            $propIndent . 'label: ' . $labelYaml,
            $propIndent . 'default: ""',
        ]);

        return implode("\n", $lines);
    }

    /**
     * Insert a new `'key' => ''` default into the `@props([...])` array at
     * the top of the section's Blade file, matching the array's own
     * indentation. Bracket-depth (and quote-aware) scanning finds the
     * array's true close even when a default value contains its own `[]`
     * (an empty repeater default, say) — a naive search for the first `]`
     * would stop short of that.
     */
    protected function appendPropDefault(string $blade, string $key): string
    {
        $propsAt = strpos($blade, '@props(');

        if ($propsAt === false) {
            return "@props([\n    '{$key}' => '',\n])\n" . $blade;
        }

        [$openBracket, $closeBracket] = $this->findBracketedArray($blade, $propsAt) ?? [null, null];

        if ($openBracket === null) {
            return $blade;
        }

        $inner = substr($blade, $openBracket + 1, $closeBracket - $openBracket - 1);
        $indent = '    ';

        if (preg_match('/\n(\s+)\S/', $inner, $m)) {
            $indent = $m[1];
        }

        $newlineInside = strpos($inner, "\n") !== false;

        if (!$newlineInside) {
            $entry = "\n" . $indent . "'{$key}' => '',\n";

            return substr($blade, 0, $openBracket + 1) . $entry . substr($blade, $openBracket + 1);
        }

        $lineStart = strrpos(substr($blade, 0, $closeBracket), "\n");
        $insertAt = $lineStart === false ? $openBracket + 1 : $lineStart + 1;

        // A hand-written array's last entry may have no trailing comma yet
        // (valid PHP — one is only required BEFORE a further element): find
        // where one needs to go, or the new entry would run straight into
        // it as a syntax error. This has to recognise every PHP comment
        // form (`//`, `#`, `/* … */`) and never mistake a comma or a `//`
        // sitting inside a string value for the array's own separator —
        // exactly the ground a hand-rolled scanner keeps losing, so this
        // asks PHP's own tokenizer instead (see `missingCommaOffset()`).
        $arrayText = substr($blade, $openBracket, $closeBracket - $openBracket + 1);
        $commaOffset = $this->missingCommaOffset($arrayText);

        $entry = $indent . "'{$key}' => '',\n";

        if ($commaOffset !== null) {
            $commaAt = $openBracket + $commaOffset;
            $blade = substr($blade, 0, $commaAt) . ',' . substr($blade, $commaAt);
            $insertAt++;
        }

        return substr($blade, 0, $insertAt) . $entry . substr($blade, $insertAt);
    }

    /**
     * Where a trailing comma needs to be inserted in `$arrayText` (a full
     * `[...]` array literal, brackets included) — the byte offset, within
     * `$arrayText`, right after the last meaningful token inside it — or
     * null when one is already there (or the array is empty).
     *
     * Built on `token_get_all()` rather than hand-rolled scanning: three
     * rounds of this feature's own history is proof that a scanner which
     * only knows about slash-slash and quotes keeps reopening the same
     * failure mode for the comment form or literal it wasn't told about
     * (a hash comment, a block comment, a `//` inside a URL string). The
     * tokenizer already knows all of PHP's comment and string syntax, by
     * definition, so nothing here has to.
     */
    protected function missingCommaOffset(string $arrayText): ?int
    {
        $prefix = '<?php $x = ';
        $raw = token_get_all($prefix . $arrayText . ';');

        $tokens = [];
        $pos = 0;

        foreach ($raw as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : $token;
            $tokens[] = [$id, $text, $pos];
            $pos += strlen($text);
        }

        $arrayStart = strlen($prefix);
        $arrayEnd = $arrayStart + strlen($arrayText);

        $inRange = array_values(array_filter(
            $tokens,
            fn (array $t): bool => $t[2] >= $arrayStart && $t[2] < $arrayEnd
        ));

        if ($inRange === []) {
            return null;
        }

        // The array's own closing `]` — drop it so what remains is only
        // what's actually inside the brackets. Anything else here (e.g. an
        // unterminated string/heredoc swallowing the rest) means this
        // isn't the plain `[...]` shape expected, so bail rather than
        // guess at an offset.
        $closing = array_pop($inRange);

        if ($closing[1] !== ']') {
            return null;
        }

        while ($inRange !== [] && in_array(end($inRange)[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            array_pop($inRange);
        }

        if ($inRange === []) {
            return null;
        }

        [, $lastText, $lastPos] = end($inRange);

        if ($lastText === ',') {
            return null;
        }

        return $lastPos + strlen($lastText) - $arrayStart;
    }

    /**
     * Bracket-depth scan for the `[...]` array right after `@props(` — a
     * naive search for the first `]` would stop short of a default value
     * that contains its own `[]` (an empty repeater default, say).
     * Returns `[openBracket, closeBracket]` byte offsets into `$blade`, or
     * null if the array has no matching close.
     *
     * This one stays a bounded character scan rather than a full
     * `token_get_all()` pass (unlike `missingCommaOffset()` and
     * `arrayTokenizesCleanly()`, which tokenize an already-extracted,
     * already-bounded array): the file after `@props(` is Blade/HTML, not
     * PHP, so there is no safe upper bound on how much of it a tokenizer
     * would have to consume looking for the array's true close. It does
     * still have to recognise every PHP comment form (single/double-quoted
     * strings, a slash-slash comment, a hash comment, a block comment) —
     * an apostrophe inside a block comment used to be read as opening a
     * string, desyncing the whole scan, which is exactly the bug this
     * replaced.
     */
    protected function findBracketedArray(string $blade, int $propsAt): ?array
    {
        $openBracket = strpos($blade, '[', $propsAt);

        if ($openBracket === false) {
            return null;
        }

        $depth = 0;
        $inString = null;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = $openBracket, $len = strlen($blade); $i < $len; $i++) {
            $char = $blade[$i];

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }

                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && ($blade[$i + 1] ?? '') === '/') {
                    $inBlockComment = false;
                    $i++;
                }

                continue;
            }

            if ($inString !== null) {
                if ($char === '\\') {
                    $i++;

                    continue;
                }

                if ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;

                continue;
            }

            if ($char === '/' && ($blade[$i + 1] ?? '') === '/') {
                $inLineComment = true;
                $i++;

                continue;
            }

            if ($char === '#') {
                $inLineComment = true;

                continue;
            }

            if ($char === '/' && ($blade[$i + 1] ?? '') === '*') {
                $inBlockComment = true;
                $i++;

                continue;
            }

            if ($char === '[') {
                $depth++;

                continue;
            }

            if ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return [$openBracket, $i];
                }
            }
        }

        return null;
    }

    /**
     * Same guarantee as the YAML side, for the Blade half — held to the
     * standard the ORIGINAL array already met, not a stricter one: a
     * rewritten array is required to still parse as a pure PHP literal
     * (never evaluating it — see `PhpLiteral`) only when the original one
     * did. A hand-written section may legitimately mix in a non-literal
     * default somewhere else in the array (a helper call, a constant);
     * requiring the whole array to be a literal after our edit would
     * refuse a perfectly valid promotion over an untouched neighbour. When
     * the original wasn't a pure literal either, the weaker but still
     * meaningful check is: the brackets still balance (guaranteed by
     * `findBracketedArray` returning a match at all) and the exact entry
     * we spliced in is really there.
     */
    protected function propsArrayIsValid(string $originalBlade, string $newBlade, string $key): bool
    {
        $newPropsAt = strpos($newBlade, '@props(');

        if ($newPropsAt === false) {
            return false;
        }

        $newBounds = $this->findBracketedArray($newBlade, $newPropsAt);

        if ($newBounds === null) {
            return false;
        }

        [$newOpen, $newClose] = $newBounds;
        $newArrayText = substr($newBlade, $newOpen, $newClose - $newOpen + 1);

        $originalPropsAt = strpos($originalBlade, '@props(');
        $originalBounds = $originalPropsAt === false ? null : $this->findBracketedArray($originalBlade, $originalPropsAt);
        $originalWasLiteral = false;

        if ($originalBounds !== null) {
            [$origOpen, $origClose] = $originalBounds;
            [$originalWasLiteral] = PhpLiteral::parse(substr($originalBlade, $origOpen, $origClose - $origOpen + 1));
        }

        if ($originalWasLiteral) {
            [$isLiteral, $value] = PhpLiteral::parse($newArrayText);

            return $isLiteral && is_array($value) && array_key_exists($key, $value);
        }

        // The array carries something PhpLiteral can't evaluate (a helper
        // call, a constant) elsewhere — that's fine, but the rewrite still
        // has to be syntactically real PHP. TOKEN_PARSE makes the
        // tokenizer itself enforce that (it throws on exactly the missing-
        // comma shape this feature exists to guard against), which is a
        // genuine syntax check rather than a substring guess.
        return $this->arrayTokenizesCleanly($newArrayText)
            && str_contains($newArrayText, "'{$key}' => ''");
    }

    /** Whether `$arrayText` (a `[...]` array literal) is syntactically valid PHP. */
    protected function arrayTokenizesCleanly(string $arrayText): bool
    {
        try {
            token_get_all('<?php $x = ' . $arrayText . ';', TOKEN_PARSE);

            return true;
        } catch (\ParseError) {
            return false;
        }
    }

    /**
     * `eyebrowText` → `Eyebrow text` — the same humanisation the canvas
     * chip falls back to for a field with no yml label yet (see
     * `labelFor()` in studio.js), so the label this writes and the one the
     * client would have shown agree.
     */
    protected function humanizeKey(string $key): string
    {
        $words = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $key);
        $words = trim((string) preg_replace('/[_\-]+/', ' ', $words));

        if ($words === '') {
            return $key;
        }

        return mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);
    }
}
