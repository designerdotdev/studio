# Monaco Dev-Mode Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace CodeMirror 6 with a slim, lazy-loaded Monaco editor (adapted from the DevDojo components package) in Studio's dev-mode source modal.

**Architecture:** A new esbuild step builds a slim Monaco bundle (editor core + HTML/YAML languages only) plus two worker files into `dist/` as flat, stably-named static files. `Studio.codeEditor()` keeps its name and `{getValue, setValue}` contract but becomes async, injecting the bundle on first modal open — `studio.js` carries zero editor bytes. PHP-side asset plumbing (allowlist, publish lists) gains the five new files.

**Tech Stack:** monaco-editor ^0.55.1, esbuild ^0.25.0 (both npm devDependencies), Vite 6, Laravel package (no composer changes).

**Spec:** `docs/superpowers/specs/2026-07-08-monaco-devmode-editor-design.md`

## Global Constraints

- Composer dependencies stay exactly `livewire/livewire` + `symfony/yaml` — this feature adds npm devDependencies only.
- All new dist files are flat-named, matching the asset route constraint `[a-zA-Z0-9._-]+` (no subdirectories).
- Slim bundle: HTML + YAML languages only. No TypeScript/CSS/JSON language services, no other basic-languages.
- `Studio.codeEditor(parent, {language, doc})` keeps its name and its `{getValue, setValue}` return contract (becomes async).
- This package has no test suite (per CLAUDE.md). Test cycles are build commands with expected output plus verification against the host app at `<host-app>` (`php artisan serve`, then `/studio`).
- The repo commits `dist/` output — include rebuilt dist files in commits.
- Work happens on the current branch (`dev`).

---

### Task 1: Slim Monaco engine + esbuild build pipeline

Builds the five Monaco asset files into `dist/`. CodeMirror stays in place until Task 3 — this task is purely additive.

**Files:**
- Modify: `package.json`
- Modify: `vite.config.js` (add `emptyOutDir: false` — see Step 3)
- Create: `esbuild.monaco.mjs`
- Create: `resources/js/monaco/studio-monaco.js`
- Create: `resources/js/monaco/editor-worker.js`
- Create: `resources/js/monaco/html-worker.js`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces:
  - `dist/studio-monaco.js` — IIFE bundle exposing `window.StudioMonaco = { monaco, create(host, {language, value, workers}) }` where `workers` is `{editor: string, html: string}` (same-origin URLs) and `create()` returns a Monaco `IStandaloneCodeEditor` (has `.getValue()`, `.setValue(v)`, `.layout()`).
  - `dist/monaco-editor-worker.js`, `dist/monaco-html-worker.js`, `dist/studio-monaco.css`, `dist/codicon.ttf`.
  - npm scripts: `build` = `vite build && node esbuild.monaco.mjs`, `build:monaco`, `dev` (builds Monaco once, then Vite watch).

- [ ] **Step 1: Add npm devDependencies and build scripts**

In `package.json`, replace the `scripts` and `devDependencies` blocks (leave `dependencies` — CodeMirror is removed in Task 3):

```json
  "scripts": {
    "build": "vite build && node esbuild.monaco.mjs",
    "build:monaco": "node esbuild.monaco.mjs",
    "dev": "node esbuild.monaco.mjs && vite build --watch"
  },
  "devDependencies": {
    "@tailwindcss/vite": "^4.0.0",
    "esbuild": "^0.25.0",
    "monaco-editor": "^0.55.1",
    "tailwindcss": "^4.0.0",
    "vite": "^6.0.0"
  },
```

Then run: `npm install`
Expected: completes without errors; `node_modules/monaco-editor/esm/vs/editor/edcore.main.js` exists.

- [ ] **Step 2: Create the worker entry files**

`resources/js/monaco/editor-worker.js` (base worker — used by every language without its own service, including YAML):

```js
import 'monaco-editor/esm/vs/editor/editor.worker.js';
```

`resources/js/monaco/html-worker.js` (HTML language service — completions, formatting):

```js
import 'monaco-editor/esm/vs/language/html/html.worker.js';
```

- [ ] **Step 3: Stop Vite from emptying dist/**

Monaco files are built by esbuild after Vite runs, and `vite build --watch` (npm run dev) would wipe them at startup. All outputs have fixed names, so nothing goes stale. In `vite.config.js`, change:

```js
    build: {
        outDir: 'dist',
        emptyOutDir: true,
```

to:

```js
    build: {
        outDir: 'dist',
        // esbuild.monaco.mjs also writes into dist/; all outputs have fixed
        // names, so never wipe the directory (watch mode would delete the
        // Monaco assets at startup otherwise).
        emptyOutDir: false,
```

- [ ] **Step 4: Create the engine bundle source**

`resources/js/monaco/studio-monaco.js`:

```js
/**
 * Studio's slim Monaco build for the dev-mode source editor.
 *
 * Bundles the editor core plus only the languages the code modal needs:
 * HTML (monarch highlighting + worker-backed language service) and YAML
 * (highlighting only — YAML validation happens server-side via
 * symfony/yaml on save). Loaded lazily by Studio.codeEditor() on first
 * modal open; never part of studio.js.
 *
 * Adapted from the DevDojo components package's monaco-editor.js.
 */
import * as monaco from 'monaco-editor/esm/vs/editor/edcore.main.js';
import 'monaco-editor/esm/vs/basic-languages/html/html.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/yaml/yaml.contribution.js';
import 'monaco-editor/esm/vs/language/html/monaco.contribution.js';

// vs-dark with Studio's editor-chrome tokens (see resources/css/studio.css
// @theme): shell #0b0b0d, panel #121215, ink #f4f4f6, soft #9d9da7,
// faint #61616b, accent #4c7dfa. Monaco themes need literal colors —
// CSS variables don't resolve here.
const studioDark = {
    base: 'vs-dark',
    inherit: true,
    rules: [
        { background: '0b0b0d', token: '' },
        { foreground: '61616b', token: 'comment' },
    ],
    colors: {
        'editor.foreground': '#f4f4f6',
        'editor.background': '#0b0b0d',
        'editor.selectionBackground': '#4c7dfa4d',
        'editor.lineHighlightBackground': '#ffffff0f',
        'editorCursor.foreground': '#f4f4f6',
        'editorWhitespace.foreground': '#ffffff26',
        'editorLineNumber.foreground': '#61616b',
        'editorLineNumber.activeForeground': '#9d9da7',
        'editorWidget.background': '#121215',
        'editorWidget.border': '#2a2a30',
        'editorSuggestWidget.background': '#121215',
        'editorSuggestWidget.selectedBackground': '#4c7dfa33',
        'input.background': '#0b0b0d',
        'scrollbarSlider.background': '#ffffff1a',
        'scrollbarSlider.hoverBackground': '#ffffff2a',
        'focusBorder': '#4c7dfa',
    },
};

let booted = false;

function boot(workers) {
    if (booted) return;
    booted = true;

    window.MonacoEnvironment = {
        getWorker(workerId, label) {
            if (label === 'html' || label === 'handlebars' || label === 'razor') {
                return new Worker(workers.html);
            }
            return new Worker(workers.editor);
        },
    };

    monaco.editor.defineTheme('studio-dark', studioDark);
}

window.StudioMonaco = {
    monaco,

    /**
     * Create an editor in `host`. `workers` carries the same-origin URLs
     * for the base and HTML workers (resolved by StudioAssets on the PHP
     * side — they may include ?v= cache busters, which Worker() accepts).
     */
    create(host, { language = 'html', value = '', workers }) {
        boot(workers);

        const mono = getComputedStyle(document.body).getPropertyValue('--font-mono').trim();

        return monaco.editor.create(host, {
            value,
            language,
            theme: 'studio-dark',
            fontSize: 12.5,
            fontFamily: mono || undefined,
            automaticLayout: true,
            minimap: { enabled: false },
            wordWrap: 'on',
            lineNumbers: 'on',
            lineNumbersMinChars: 3,
            padding: { top: 12 },
            scrollBeyondLastLine: false,
            autoIndent: 'advanced',
            formatOnPaste: true,
            // The modal clips overflow; render completion widgets
            // position:fixed so they aren't cut off.
            fixedOverflowWidgets: true,
            stickyScroll: { enabled: false },
        });
    },
};
```

- [ ] **Step 5: Create the esbuild config**

`esbuild.monaco.mjs`:

```js
import * as esbuild from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Builds the slim Monaco assets into dist/ as flat, stably-named files
 * (the asset route only allows [a-zA-Z0-9._-]+ — no subdirectories).
 * Runs after `vite build`; Vite has emptyOutDir off so these survive.
 * Adapted from the DevDojo components repo's esbuild.config.js.
 */
const packageDir = import.meta.dirname;
const outDir = path.join(packageDir, 'dist');

export const MONACO_FILES = [
    'studio-monaco.js',
    'studio-monaco.css',
    'monaco-editor-worker.js',
    'monaco-html-worker.js',
    'codicon.ttf',
];

await esbuild.build({
    entryPoints: {
        'studio-monaco': 'resources/js/monaco/studio-monaco.js',
        'monaco-editor-worker': 'resources/js/monaco/editor-worker.js',
        'monaco-html-worker': 'resources/js/monaco/html-worker.js',
    },
    outdir: outDir,
    bundle: true,
    format: 'iife',
    minify: true,
    sourcemap: false,
    platform: 'browser',
    loader: { '.ttf': 'file' },
    // Stable name (codicon.ttf, no hash): the CSS references it
    // relatively and AssetController allowlists exact filenames.
    assetNames: '[name]',
});

// Mirror vite.config.js's publishToHost(): keep hosts that serve
// published assets in sync. Same target resolution, same opt-in.
const target = process.env.STUDIO_PUBLISH_DIR
    || path.resolve(packageDir, '../../../public/vendor/studio');

if (process.env.STUDIO_PUBLISH_DIR || fs.existsSync(target)) {
    fs.mkdirSync(target, { recursive: true });
    for (const file of MONACO_FILES) {
        const source = path.join(outDir, file);
        if (fs.existsSync(source)) {
            fs.copyFileSync(source, path.join(target, file));
        }
    }
    console.log(`  studio → published Monaco assets to ${target}`);
}

console.log(`✓ Built Monaco assets to ${outDir}`);
```

- [ ] **Step 6: Build and verify the output**

Run: `npm run build:monaco`
Expected output ends with `✓ Built Monaco assets to …/dist`.

Run: `ls -la dist/`
Expected: `studio-monaco.js` (~2–3MB), `studio-monaco.css` (~100–150KB), `monaco-editor-worker.js` (~250–300KB), `monaco-html-worker.js` (~700–800KB), `codicon.ttf` (~100–130KB) — alongside the existing `studio.js` and `studio-css.css`.

Slimness check — the bundle must be meaningfully smaller than the all-languages DevDojo build (4.3MB):

```bash
node -e "const s=require('fs').statSync('dist/studio-monaco.js').size; console.log(s < 4_000_000 ? 'OK slim ('+s+' bytes)' : 'TOO BIG ('+s+' bytes) — check that only the html/yaml contributions are imported')"
```

Expected: `OK slim (… bytes)`. (Functional verification — highlighting, completions — happens in Task 4 against the host app.)

> **Amended 2026-07-08 (user-approved):** gate relaxed from 3.5MB to 4MB. Measured: 3,754,907 bytes. Monaco 0.55's editor core (`edcore.main.js`) is ~3.65MB by itself — the original 3.5MB estimate wrongly assumed language services dominated the main bundle. The slim build still avoids ever shipping the TS/CSS/JSON workers (~8.4MB) and is lazy-loaded, dev-mode-only.

Run: `npm run build`
Expected: Vite builds `studio.js`/`studio-css.css`, then esbuild rebuilds Monaco files; `ls dist/` still shows all seven files (proves `emptyOutDir: false` works).

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json vite.config.js esbuild.monaco.mjs resources/js/monaco/ dist/
git commit -m "Add slim lazy-loadable Monaco build (html+yaml) via esbuild

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: PHP asset plumbing

Makes the five Monaco files servable (AssetController), publishable (`studio:publish` + `vendor:publish --tag=studio-assets`), and URL-resolvable (`StudioAssets::url()`). Includes the approved cleanup: the service provider's hardcoded publish array now iterates `StudioAssets::FILES`.

**Files:**
- Modify: `src/Support/StudioAssets.php:15`
- Modify: `src/Http/Controllers/AssetController.php:10-13`
- Modify: `src/StudioServiceProvider.php:105-108` (plus one `use` statement)

**Interfaces:**
- Consumes: the five dist filenames from Task 1 (exact names: `studio-monaco.js`, `studio-monaco.css`, `monaco-editor-worker.js`, `monaco-html-worker.js`, `codicon.ttf`).
- Produces: `StudioAssets::url('<file>')` returns a same-origin URL for each of the seven files (published copy preferred per file, else `/{studio.path}/assets/{file}`). Task 3's Blade changes call this.

- [ ] **Step 1: Extend StudioAssets::FILES**

In `src/Support/StudioAssets.php`, replace:

```php
    public const FILES = ['studio.js', 'studio-css.css'];
```

with:

```php
    public const FILES = [
        'studio.js',
        'studio-css.css',
        'studio-monaco.js',
        'studio-monaco.css',
        'monaco-editor-worker.js',
        'monaco-html-worker.js',
        'codicon.ttf',
    ];
```

- [ ] **Step 2: Extend the AssetController allowlist**

In `src/Http/Controllers/AssetController.php`, replace:

```php
    protected array $allowedFiles = [
        'studio.js' => 'application/javascript',
        'studio-css.css' => 'text/css',
    ];
```

with:

```php
    protected array $allowedFiles = [
        'studio.js' => 'application/javascript',
        'studio-css.css' => 'text/css',
        'studio-monaco.js' => 'application/javascript',
        'studio-monaco.css' => 'text/css',
        'monaco-editor-worker.js' => 'application/javascript',
        'monaco-html-worker.js' => 'application/javascript',
        'codicon.ttf' => 'font/ttf',
    ];
```

- [ ] **Step 3: Make the provider's publish list iterate StudioAssets::FILES**

In `src/StudioServiceProvider.php`, add the import after line 14 (`use Designer\Studio\Services\Storage\StudioStorage;`):

```php
use Designer\Studio\Support\StudioAssets;
```

Then replace:

```php
            $this->publishes([
                __DIR__ . '/../dist/studio.js' => public_path('vendor/studio/studio.js'),
                __DIR__ . '/../dist/studio-css.css' => public_path('vendor/studio/studio-css.css'),
            ], 'studio-assets');
```

with:

```php
            $publishableAssets = [];
            foreach (StudioAssets::FILES as $file) {
                $publishableAssets[__DIR__ . '/../dist/' . $file] = public_path(StudioAssets::PUBLISH_PATH . '/' . $file);
            }
            $this->publishes($publishableAssets, 'studio-assets');
```

- [ ] **Step 4: Lint and verify against the host app**

```bash
php -l src/Support/StudioAssets.php && php -l src/Http/Controllers/AssetController.php && php -l src/StudioServiceProvider.php
```

Expected: `No syntax errors detected` ×3.

Then exercise the publish round-trip from the host app (this walks `StudioAssets::FILES` against real dist files):

```bash
cd <host-app>
php artisan studio:publish
```

Expected: seven `✓ <file> → public/vendor/studio/<file>` lines, no errors.

```bash
php artisan studio:publish --remove
```

Expected: `Published assets removed. Assets are served from the package again.` (Removing matters — a lingering published copy is the CLAUDE.md stale-asset gotcha.)

Optional if `php artisan serve` is running: `curl -sI http://127.0.0.1:8000/studio/assets/studio-monaco.js | head -3` → `HTTP/1.1 200 OK` with `Content-Type: application/javascript`.

- [ ] **Step 5: Commit**

```bash
cd <host-app>/packages/designer/studio
git add src/Support/StudioAssets.php src/Http/Controllers/AssetController.php src/StudioServiceProvider.php
git commit -m "Serve and publish the Monaco assets (single PHP-side asset list)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Frontend swap — lazy loader, modal wiring, CSS, CodeMirror removal

Swaps the engine behind `Studio.codeEditor()`, wires the modal, restyles the code pane, and removes CodeMirror entirely.

**Files:**
- Modify: `resources/js/studio.js:4-9` (imports) and `:681-705` (codeEditor)
- Modify: `resources/views/home.blade.php:915-916` (asset config script), `:929-935` (ensureEditors), `:950-951` (await)
- Modify: `resources/css/studio.css:727-742` (code pane styles)
- Modify: `package.json` (remove CodeMirror dependencies)

**Interfaces:**
- Consumes: `window.StudioMonaco.create(host, {language, value, workers})` from Task 1; `StudioAssets::url()` from Task 2.
- Produces: `Studio.codeEditor(parent, {language, doc})` → `Promise<{editor, getValue(), setValue(v)}>`; `window.__studioMonacoAssets = {script, css, workers: {editor, html}}` (set only when dev mode is enabled).

- [ ] **Step 1: Remove the CodeMirror imports from studio.js**

Delete lines 4–9:

```js
import { basicSetup, EditorView } from 'codemirror';
import { keymap } from '@codemirror/view';
import { indentWithTab } from '@codemirror/commands';
import { html as htmlLang } from '@codemirror/lang-html';
import { yaml as yamlLang } from '@codemirror/lang-yaml';
import { oneDark } from '@codemirror/theme-one-dark';
```

- [ ] **Step 2: Replace Studio.codeEditor with the async lazy loader**

In `resources/js/studio.js`, replace the whole `codeEditor(parent, …) { … }` method (the block from the `/** CodeMirror 6 instance … */` doc comment through its closing `},`) with:

```js
    /**
     * Lazy-loading Monaco factory for the dev-mode source editor.
     * Injects the slim Monaco bundle + CSS on first use — studio.js
     * itself carries no editor code. Resolves { editor, getValue, setValue }.
     * Asset URLs come from window.__studioMonacoAssets, rendered by the
     * dev-mode block in home.blade.php.
     */
    async codeEditor(parent, { language = 'html', doc = '' } = {}) {
        const assets = window.__studioMonacoAssets;

        if (!assets) {
            throw new Error('The code editor is only available in dev mode.');
        }

        if (!window._studioMonacoPromise) {
            window._studioMonacoPromise = new Promise((resolve, reject) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = assets.css;
                document.head.appendChild(link);

                const script = document.createElement('script');
                script.src = assets.script;
                script.onload = resolve;
                script.onerror = () => reject(new Error('Could not load the code editor.'));
                document.head.appendChild(script);
            }).catch((error) => {
                // Allow the next open to retry a failed load
                window._studioMonacoPromise = null;
                throw error;
            });
        }

        await window._studioMonacoPromise;

        const editor = window.StudioMonaco.create(parent, {
            language,
            value: doc,
            workers: assets.workers,
        });

        return {
            editor,
            getValue: () => editor.getValue(),
            setValue(value) {
                editor.setValue(value);
            },
        };
    },
```

- [ ] **Step 3: Expose the asset URLs and await the editors in home.blade.php**

Right after the `@if(\Designer\Studio\Support\DevMode::enabled())` line (before the modal's outer `<div`), add:

```blade
    <script>
        window.__studioMonacoAssets = {
            script: @js(\Designer\Studio\Support\StudioAssets::url('studio-monaco.js')),
            css: @js(\Designer\Studio\Support\StudioAssets::url('studio-monaco.css')),
            workers: {
                editor: @js(\Designer\Studio\Support\StudioAssets::url('monaco-editor-worker.js')),
                html: @js(\Designer\Studio\Support\StudioAssets::url('monaco-html-worker.js')),
            },
        };
    </script>
```

Replace `ensureEditors()`:

```js
                ensureEditors() {
                    if (this.editors) return;
                    this.editors = {
                        html: window.Studio.codeEditor(this.$refs.htmlHost, { language: 'html' }),
                        yaml: window.Studio.codeEditor(this.$refs.yamlHost, { language: 'yaml' }),
                    };
                },
```

with:

```js
                ensureEditors() {
                    // Assigned synchronously so overlapping openEditor() calls
                    // share one in-flight boot instead of double-creating
                    // Monaco instances on the same hosts.
                    if (!this._editorsPromise) {
                        this._editorsPromise = (async () => {
                            this.editors = {
                                html: await window.Studio.codeEditor(this.$refs.htmlHost, { language: 'html' }),
                                yaml: await window.Studio.codeEditor(this.$refs.yamlHost, { language: 'yaml' }),
                            };
                        })().catch((error) => {
                            this._editorsPromise = null; // allow retry after a failed load
                            throw error;
                        });
                    }
                    return this._editorsPromise;
                },
```

> **Amended 2026-07-08 (review finding):** the original `async ensureEditors() { if (this.editors) return; … }` guard raced — `this.editors` isn't assigned until both awaits resolve, so two `studio:open-code-editor` dispatches before the first Monaco load finished would each create editors on the same host nodes. The promise-guard version above closes the window.

And in `openEditor(detail)`, replace:

```js
                        await this.$nextTick();
                        this.ensureEditors();
```

with:

```js
                        await this.$nextTick();
                        await this.ensureEditors();
```

(The surrounding try/catch already routes a load failure into the modal's error state, and `loading` now also covers the first-open bundle download.)

- [ ] **Step 4: Swap the code-pane CSS**

In `resources/css/studio.css`, replace the three CodeMirror blocks (lines 727–742):

```css
    .s-code-pane .cm-editor {
        height: 100%;
        font-size: 12.5px;
        background: var(--color-shell);
        outline: none;
    }

    .s-code-pane .cm-gutters {
        background: var(--color-shell);
        border-right: 1px solid var(--color-line);
    }

    .s-code-pane .cm-scroller {
        font-family: var(--font-mono);
        line-height: 1.6;
    }
```

with:

```css
    /* Monaco paints its own theme; the pane bg covers the lazy-load gap */
    .s-code-pane {
        background: var(--color-shell);
    }

    .s-code-pane .monaco-editor {
        outline: none;
    }
```

(Font family and size are passed as editor options by `StudioMonaco.create()` — Monaco doesn't inherit them from CSS.)

- [ ] **Step 5: Remove the CodeMirror npm dependencies**

In `package.json`, delete these lines from `dependencies`:

```json
    "@codemirror/commands": "^6.10.4",
    "@codemirror/lang-html": "^6.4.11",
    "@codemirror/lang-yaml": "^6.1.3",
    "@codemirror/theme-one-dark": "^6.1.3",
    "@codemirror/view": "^6.43.6",
    "codemirror": "^6.0.2",
```

Then run: `npm install`
Expected: lockfile prunes the CodeMirror packages.

- [ ] **Step 6: Build and verify CodeMirror is gone**

Run: `npm run build`
Expected: clean build (any leftover CodeMirror reference would fail Vite's import resolution).

```bash
grep -ci "codemirror" dist/studio.js || echo "0 — clean"
node -e "console.log('studio.js', require('fs').statSync('dist/studio.js').size, 'bytes')"
```

Expected: `0 — clean`, and `studio.js` dramatically smaller than before (CodeMirror was its biggest chunk — expect roughly a half-size drop; compare `git show HEAD:dist/studio.js | wc -c`).

- [ ] **Step 7: Commit**

```bash
git add resources/js/studio.js resources/views/home.blade.php resources/css/studio.css package.json package-lock.json dist/
git commit -m "Swap dev-mode source editor to lazy-loaded Monaco, drop CodeMirror

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Docs + end-to-end verification

**Files:**
- Modify: `CLAUDE.md` (frontend bullet, dev-mode bullet, gotchas)

**Interfaces:**
- Consumes: everything prior.
- Produces: n/a (docs + verification).

- [ ] **Step 1: Update CLAUDE.md**

Three edits:

1. In the **Frontend** section, replace:

> `Studio.codeEditor` (CodeMirror 6, statically bundled — the biggest chunk of the bundle) + `Studio.codeModalOpen` (global shortcuts stand down while the dev-mode code modal is open)

with:

> `Studio.codeEditor` (lazy-loads the slim Monaco bundle on first modal open — `dist/studio-monaco.js` + worker files built by `esbuild.monaco.mjs`, HTML+YAML languages only; studio.js carries no editor code) + `Studio.codeModalOpen` (global shortcuts stand down while the dev-mode code modal is open)

2. In the **Dev mode** bullet, replace `opens a CodeMirror modal (HTML/YAML tabs)` with `opens a Monaco modal (HTML/YAML tabs)`.

3. In **Gotchas**, add a bullet:

> - The compiled asset list lives in several places that must stay in sync when files are added: `StudioAssets::FILES` (drives `studio:publish` + the provider's `studio-assets` publish tag), `AssetController::$allowedFiles` (adds MIME types), Vite's `ASSET_FILES` (`vite.config.js`), and `MONACO_FILES` in `esbuild.monaco.mjs`.

4. In **Build Commands**, after the `npm run dev` line's comment, note Monaco: replace the comment `# Watch mode: rebuilds dist/ on every change (and re-publishes to the host's public/vendor/studio if published assets exist)` with `# Watch mode: builds Monaco once, then rebuilds dist/ on every change (re-publishing to the host's public/vendor/studio if published assets exist)`.

- [ ] **Step 2: Full build + host-app verification**

```bash
npm run build
cd <host-app> && php artisan serve
```

In the browser at `http://127.0.0.1:8000/studio` (dev mode on via the hamburger menu), verify with the network tab open:

1. Select a section → inspector header shows Edit code (`</>`); click it.
2. First open: `studio-monaco.js` + `studio-monaco.css` load lazily; a worker request fires; the modal shows the loading spinner until the editor appears. Second open: no re-download.
3. HTML tab: syntax highlighting; typing `<di` offers tag completions; the completion popup isn't clipped by the modal.
4. YAML tab: highlighting; switching tabs back and forth keeps both editors laid out correctly.
5. Edit the HTML (visible copy change), Cmd+S → toast "Section source saved…", canvas refreshes, files on disk under the host's `resources/views/designer/` updated.
6. Break the YAML (e.g. remove a colon), save → clear error in the modal footer, nothing written.
7. Escape closes the modal; while the modal is open, canvas shortcuts (⌘D, ⌫) stand down.
8. Published mode: `php artisan studio:publish`, hard-reload, repeat step 2 (assets now from `/vendor/studio/`), then `php artisan studio:publish --remove`.

If browser automation is unavailable, hand this checklist to Tony rather than marking the task complete.

- [ ] **Step 3: Commit**

```bash
cd <host-app>/packages/designer/studio
git add CLAUDE.md
git commit -m "Update CLAUDE.md for the Monaco dev-mode editor

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
