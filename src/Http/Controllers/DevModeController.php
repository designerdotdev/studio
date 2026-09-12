<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\DesignSyncService;
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

        $openBracket = strpos($blade, '[', $propsAt);

        if ($openBracket === false) {
            return $blade;
        }

        $depth = 0;
        $inString = null;
        $closeBracket = null;

        for ($i = $openBracket, $len = strlen($blade); $i < $len; $i++) {
            $char = $blade[$i];

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

            if ($char === '[') {
                $depth++;

                continue;
            }

            if ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    $closeBracket = $i;

                    break;
                }
            }
        }

        if ($closeBracket === null) {
            return $blade;
        }

        $inner = substr($blade, $openBracket + 1, $closeBracket - $openBracket - 1);
        $indent = '    ';

        if (preg_match('/\n(\s+)\S/', $inner, $m)) {
            $indent = $m[1];
        }

        $newlineInside = strpos($inner, "\n") !== false;

        if ($newlineInside) {
            $lineStart = strrpos(substr($blade, 0, $closeBracket), "\n");
            $insertAt = $lineStart === false ? $openBracket + 1 : $lineStart + 1;
            $entry = $indent . "'{$key}' => '',\n";
        } else {
            $insertAt = $openBracket + 1;
            $entry = "\n" . $indent . "'{$key}' => '',\n";
        }

        return substr($blade, 0, $insertAt) . $entry . substr($blade, $insertAt);
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
