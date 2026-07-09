# Monaco replaces CodeMirror in the dev-mode source editor

**Date:** 2026-07-08 · **Status:** approved
**Supersedes** the editor choice recorded in
`2026-07-08-devmode-source-editing-design.md` (everything else in that
spec stands).

## Problem

The dev-mode code modal uses CodeMirror 6, statically bundled into
`studio.js` (its biggest chunk). The DevDojo components package
(`~/devdojo/platform-packages/components`) has a nicer Monaco editor
component whose architecture — a lazy-loaded pre-built bundle plus flat
static worker files, no code-splitting — fits Studio's asset pipeline
after all. Replace CodeMirror with Monaco.

## Decisions

- **Scope:** the dev-mode source modal only (HTML/YAML tabs). Other
  code-ish inputs (`head_html`, JSON-LD textareas) are unchanged.
- **Integration: slim build inside Studio** (not a composer dependency
  on devdojo/components, not vendored pre-built assets). `monaco-editor`
  (^0.55) + `esbuild` (^0.25) become npm devDependencies; the engine JS
  is adapted from the DevDojo component's `monaco-editor.js`. Keeps
  Studio's composer deps at livewire + symfony/yaml and its zero-config
  asset serving; upgrades flow through npm.
- **Slim bundle:** editor core + HTML and YAML language contributions +
  the HTML language service only. No TypeScript/CSS/JSON services, no
  other languages (~2.5MB vs 4.3MB all-languages). YAML stays
  highlighting-only (as with CodeMirror); YAML validation remains
  server-side (symfony/yaml on save). HTML gains real completions and
  formatting via the HTML worker.
- **Lazy-loaded:** `studio.js` carries zero editor bytes. The Monaco
  bundle + CSS load on first modal open via injected script/link tags
  (single shared promise — the DevDojo component's own pattern).
  First open per session downloads ~2.5MB; dev mode is local-only by
  default, so this is a localhost round-trip, cached immutably after.

## Design

### Build

- New `esbuild.monaco.mjs` (adapted from the components repo's
  `esbuild.config.js`) emits **flat, stably-named** files into `dist/`:
  - `studio-monaco.js` — engine bundle (IIFE, exposes `window.StudioMonaco`)
  - `monaco-editor-worker.js`, `monaco-html-worker.js` — 1-line worker
    entries under `resources/js/monaco/`
  - `studio-monaco.css` — extracted by esbuild from Monaco's imports
  - `codicon.ttf` — stable name via esbuild asset naming (the CSS
    references it relatively, so it must sit beside the CSS)
- Flat names are required by the asset route constraint
  (`[a-zA-Z0-9._-]+` — no slashes).
- `npm run build` → `vite build && node esbuild.monaco.mjs`.
  `npm run dev` builds Monaco once, then runs Vite watch. The esbuild
  script mirrors the Vite `publishToHost` copy step (Vite's
  `emptyOutDir` runs first, so esbuild output lands after and must
  publish itself).

### Engine (`resources/js/monaco/studio-monaco.js`)

Adapted from the DevDojo component's `monaco-editor.js`:

- **Kept:** the dark theme (background aligned to the modal surface
  instead of pure black; always dark — no auto light/dark, matching the
  oneDark-always setup it replaces), editor options (word wrap on,
  minimap off, line numbers on, `automaticLayout`,
  `scrollBeyondLastLine: false`), `MonacoEnvironment` worker wiring.
- **Changed:** workers resolve from explicit URLs passed in by the page
  (not string-replacement on a base path). `label === 'html'` → HTML
  worker; default → editor worker.
- **Dropped:** Alpine wrapper, placeholder overlay, image drop-upload
  (hardcodes `/api/image/upload` and inserts markdown — wrong endpoint
  and wrong syntax here), `set-code`/`set-language`/
  `monaco-content-changed` window events, min/maxLines auto-height,
  `window.monacoInstances` registry.

### Runtime API

- `Studio.codeEditor(parent, { language, doc })` keeps its name and its
  `{ getValue, setValue }` contract but becomes **async**: first call
  injects the CSS link + script tag, then resolves the editor handle
  (also exposes the raw `editor` instance).
- The modal's `ensureEditors()` becomes async and is awaited inside
  `openEditor()` — already async with a `loading` state, which now also
  covers the first-open bundle download.
- Asset URLs (`studio-monaco.js`, `studio-monaco.css`, both workers)
  come from `StudioAssets::url()` and are exposed to JS by the dev-mode
  modal block in `home.blade.php` — rendered only when
  `DevMode::enabled()`, so the URLs don't appear in non-dev sessions.
- `Studio.codeModalOpen` standdown behavior is unchanged. Cmd+S and
  Escape keep working: Monaco binds neither by default, so both bubble
  to the modal's existing window handlers (verify).

### Asset serving

- `AssetController::$allowedFiles` gains the five files with correct
  MIME types (`font/ttf` for codicon).
- `StudioAssets::FILES` gains them, which flows through
  `studio:publish` (`PublishAssets` iterates the constant). Targeted
  cleanup: the service provider's hardcoded `studio-assets`
  `publishes()` array now iterates `StudioAssets::FILES` too — one
  PHP-side asset list. The JS-side lists (Vite `ASSET_FILES`, esbuild
  publish mirror) stay separate; note the cross-language duplication in
  CLAUDE.md's gotchas.
- Workers are same-origin in both serving modes (package route
  `/studio/assets/...`, published `/vendor/studio/...`). A `?v=` query
  on worker URLs is fine. Mixed states self-heal: `StudioAssets::url()`
  checks existence per file, so a stale published copy lacking the
  Monaco files falls back to the package route for just those files.

### Removal + docs

- Remove `codemirror` + all `@codemirror/*` deps from `package.json`
  and their imports from `studio.js`.
- Update CLAUDE.md: frontend bundle note (CodeMirror gone, Monaco
  lazy-loaded static assets), dev-mode bullet, gotchas (asset-list
  duplication).

## Verification

- `npm run build` → `dist/` contains the five Monaco files; `studio.js`
  is markedly smaller and contains no `codemirror` strings.
- Host app, dev mode on: Edit code → modal loads Monaco lazily (network
  tab shows `studio-monaco.js` + workers on first open only); HTML tab
  highlights with tag completion; YAML tab highlights; Cmd+S saves,
  canvas refreshes, panel reloads fields; invalid YAML rejected with a
  clear error; Escape closes; global shortcuts stand down while open.
- Published mode: `php artisan studio:publish` → all files land in
  `public/vendor/studio`, modal still works; `--remove` reverts.
