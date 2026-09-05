# Studio Workbench Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Designer Studio the DevDojo Sites editing surfaces — an icon rail with Pages, Content (collections), Media and an Assistant panel that drives the user's local `claude`/`codex` CLI.

**Architecture:** Each panel is its own Livewire 3 component mounted in the existing `<aside>`; a 48px Alpine rail chooses which one shows. Data stays in Studio's JSON storage (`storage/studio/…`, draft-workspaced), with one new repository (`CollectionRepository`), one new service (`MediaLibrary`), and a small assistant service that spawns the CLI with Symfony Process and relays its JSON-line events over SSE.

**Tech Stack:** Laravel 12 package, Livewire 3, Alpine 3, Tailwind 4 (`resources/css/studio.css`, `npm run build`), SortableJS (already bundled), Symfony Process (already required).

**Spec:** `docs/superpowers/specs/2026-09-05-studio-workbench-design.md`

## Global Constraints

- No commits until every task is done (user instruction). Build assets with `npm run build` after any `resources/js|css` change.
- Editor chrome controls are 32px tall (`h-8`), compact, dark tokens from `studio.css` (`bg-shell/panel/raised`, `text-ink/soft/faint`).
- All storage writes go through `StudioStorage` so the draft workspace is honoured; the component library and assistant threads are NOT workspaced.
- Every new route lives in the `api` group in `routes/web.php`, with slug/name constraints `[a-z0-9-]+`, and throttles on mutating endpoints.
- Shared contracts (`Livewire.dispatch` names, `studio:*` postMessage/window events) change everywhere or nowhere.
- Verification is against the host app `~/Sites/designer` (`designer.test`): curl for endpoints, puppeteer from `~/devdojo/devdojo/node_modules` for screenshots/interactions (see the dev-harness memory).

---

## File structure

```
src/Livewire/PagesPanel.php            list/rename/duplicate/delete/reorder/set-home
src/Livewire/MediaPanel.php            library browser + picker mode
src/Livewire/ContentPanel.php          collections → table → entry editor
src/Livewire/AssistantPanel.php        threads, composer, engine picker (turn streaming is JS)
src/Services/MediaLibrary.php          filesystem ops under public/studio-uploads
src/Services/Storage/CollectionRepository.php   collection docs CRUD + rows
src/Services/CollectionBinder.php      resolves instance bindings into variables
src/Services/Assistant/Engines.php     which CLIs exist, their argv builders
src/Services/Assistant/Threads.php     thread JSON CRUD
src/Services/Assistant/SystemPrompt.php
src/Services/Assistant/TurnRunner.php  Process + event translation
src/Http/Controllers/MediaController.php
src/Http/Controllers/AssistantController.php   turn/stream/stop
resources/views/livewire/pages-panel.blade.php
resources/views/livewire/media-panel.blade.php
resources/views/livewire/content-panel.blade.php
resources/views/livewire/assistant-panel.blade.php
resources/views/components/layouts/app.blade.php   rail column
resources/views/home.blade.php         mounts the panels
resources/js/studio.js                 assistant stream client, media picker, element-select in iframe
resources/css/studio.css               s-rail, panel bits
```

---

### Task 1: Rail + panel switching

**Files:**
- Modify: `resources/views/components/layouts/app.blade.php` (aside becomes rail + panel)
- Modify: `resources/views/home.blade.php` (`$store.studio.rail`, mount stub panels)
- Modify: `resources/css/studio.css` (`.s-rail`, `.s-rail-btn`)

**Interfaces:**
- Produces: `Alpine.store('studio').rail` ∈ `assistant|sections|pages|content|media`, `setRail(name)` (same name again collapses the panel), persisted in `localStorage['studio.rail']`. Window event `studio:rail` `{name}` fired on change. Any panel opens the sidebar.
- The sidebar slot renders `<div x-show="$store.studio.rail === 'sections'">@livewire('studio::editor-panel')</div>` and one wrapper per new panel.

- [ ] Add the store fields and `setRail()`; on `studio:select-section` (iframe → editor) call `setRail('sections')` without collapsing.
- [ ] Rail markup: five `button.s-rail-btn` with inline SVGs (sparkles, layers, file, database, image); `is-active` when `$store.studio.rail === name && $store.studio.sidebar`.
- [ ] Verify: screenshot `/studio?page=home`; clicking Pages shows the Pages placeholder; clicking Pages again collapses the panel; refresh restores the choice.

### Task 2: Pages panel

**Files:**
- Create: `src/Livewire/PagesPanel.php`, `resources/views/livewire/pages-panel.blade.php`
- Modify: `src/Services/Storage/PageRepository.php` (`reorder(array $slugs)`, order-aware `all()`), `src/Support/SiteUrls.php` (home slug from site doc), `src/StudioServiceProvider.php` (register component)

**Interfaces:**
- `PagesPanel` public: `rename(string $slug, string $title)`, `duplicate(string $slug)`, `delete(string $slug)`, `setHome(string $slug)`, `reorder(array $slugs)`. Emits browser event `studio:pages-changed` (topbar switcher re-fetches via a full reload when the current page is deleted/renamed).
- `PageRepository::all()` sorts by `order` (int, default = position on first save) then title.

- [ ] Component + view: rows with title, `/slug`, home badge, hover kebab (`s-pop` menu). Drag handle uses `Studio.sortable(el, '.handle', ids => $wire.reorder(ids))`.
- [ ] Set-home writes `home_slug` into `site/data.json` via `SiteDocument::save`; `SiteUrls::homeSlug()` prefers it over config.
- [ ] Verify: rename/duplicate/delete via the panel on the seeded site; `ls storage/studio/draft/pages`; order persists across reload.

### Task 3: Media library service + endpoints

**Files:**
- Create: `src/Services/MediaLibrary.php`, `src/Http/Controllers/MediaController.php`
- Modify: `routes/web.php`

**Interfaces:**
```php
final class MediaLibrary {
  public function root(): string;                         // public_path('studio-uploads')
  public function list(string $dir = ''): array;           // ['dir' => 'a/b', 'folders' => [['name','path']], 'files' => [['name','path','url','size','width','height','mtime','type']]]
  public function upload(UploadedFile $file, string $dir = ''): array; // file entry
  public function createFolder(string $dir, string $name): string;
  public function rename(string $path, string $newName): string;      // files or folders; returns new path
  public function move(string $path, string $toDir): string;
  public function duplicate(string $path): string;
  public function delete(string $path): void;                        // folder must be empty
}
```
Paths are relative to root, normalised, `..` rejected (`InvalidArgumentException` → 422). `designer/` (template assets) is listed but read-only.

Routes (api group): `GET media` (`?dir=`), `POST media/upload` (throttle 30/min; `dir` field), `POST media/folder`, `PATCH media` (`{path, name|to}`), `POST media/duplicate`, `DELETE media` — all JSON.

- [ ] Verify with curl: upload a png, list, rename, move into a new folder, duplicate, delete; `..` returns 422.

### Task 4: Media panel + picker

**Files:**
- Create: `src/Livewire/MediaPanel.php` (thin: only holds `pickerRequest`), `resources/views/livewire/media-panel.blade.php` (Alpine-driven grid over the JSON endpoints)
- Modify: `resources/views/livewire/fields/image.blade.php`, `resources/views/livewire/fields/sub-input.blade.php` (add "Library" button), `resources/js/studio.js` (`Studio.mediaPick()` promise helper), `resources/css/studio.css`

**Interfaces:**
- `window.Studio.mediaPick(): Promise<string|null>` — dispatches `studio:media-pick` `{id}`, sets rail to media in picker mode, resolves on `studio:media-picked` `{id, url}` or `null` on cancel (`Esc` / "Cancel picking" bar).
- Image field: `<button @click="Studio.mediaPick().then(url => url && (preview(...), $wire.setVariable(...)))">`.

- [ ] Grid (folders first, thumbnails `object-cover`), breadcrumb, search filter, upload button + drop zone (multi), context menu (Copy URL, Rename, Move to…, Duplicate, Delete), lightbox (`Esc` closes, arrows navigate).
- [ ] Verify with puppeteer: open Media, upload two files via `page.setInputFiles`, right-click → rename, pick into the hero image field.

### Task 5: Collections repository + binder

**Files:**
- Create: `src/Services/Storage/CollectionRepository.php`, `src/Services/CollectionBinder.php`
- Modify: `src/Services/SectionRenderer.php` (`context()` applies bindings), `src/Http/Controllers/RenderController.php`, `src/Http/Controllers/StudioController.php::iframe`, `src/Http/Controllers/PageController.php`, `src/Services/BladeGenerator.php` (pass instance `bindings` through), `src/Services/PublishService.php` (add `collections`), `src/Services/Storage/StudioStorage.php` (ensure dir)

**Interfaces:**
```php
final class CollectionRepository {
  public function all(): array;                                     // [name => doc]
  public function find(string $name): ?array;
  public function create(string $title, array $fields): array;     // name = slug(title)
  public function updateSchema(string $name, array $fields, ?string $title = null): array;
  public function delete(string $name): bool;
  public function rows(string $name): array;
  public function saveRow(string $name, array $row): array;         // upsert by row['id'] (uuid when missing)
  public function deleteRow(string $name, string $id): void;
  public function reorderRows(string $name, array $ids): void;
}
final class CollectionBinder {
  /** @param array $bindings ['items' => 'collections.guides'] */
  public function apply(array $variables, array $bindings): array;   // replaces bound keys with rows
}
```
- Every call site that builds variables for an instance passes `$instance['bindings'] ?? []` into `SectionRenderer::render($component, $vars, $bindings)`; `render()` calls `CollectionBinder::apply` before `context()`.
- Instance docs gain `bindings` (object, optional). `ComponentData::fields[*]['source']` (`collections.<name>`) is applied by `PageRepository::addComponent` when the collection exists.

- [ ] Verify: create `guides` via tinker, bind the Pilot guides section instance, render `/guides` and the canvas — rows come from the collection; publish carries `collections/`.

### Task 6: Template import → collections

**Files:**
- Modify: `src/Services/Templates/TemplateImporter.php` (write collection docs; instances whose bound attribute is `$<name>` get `bindings[field] = 'collections.<name>'` instead of inlined rows)

- [ ] Schema from sibling `.yml` (`fields:` map) else inferred (`text`, `textarea` for >120 chars, `image` for urls ending in image extensions, `richtext` for values containing `<p`).
- [ ] Verify: re-import Monarch; `storage/studio/draft/collections/*.json` exist; testimonials render identically; editing a row in Content updates the canvas.

### Task 7: Content panel

**Files:**
- Create: `src/Livewire/ContentPanel.php`, `resources/views/livewire/content-panel.blade.php`, `resources/views/livewire/content/field.blade.php` (field partial by type incl. richtext)
- Modify: `resources/views/livewire/fields/repeater.blade.php` (bound state + Bind/Unbind), `src/Livewire/EditorPanel.php` (`bindRepeater`, `unbindRepeater`)

**Interfaces:**
- `ContentPanel` public: `open(string $name)`, `back()`, `edit(string $id)`, `newRow()`, `saveRow()`, `deleteRow(string $id)`, `reorder(array $ids)`, `createCollection()`, `saveSchema()`; `view` ∈ `list|table|entry|schema`. Dispatches `studio:refresh-preview` after any row save.
- `EditorPanel::bindRepeater(string $sectionId, string $key, string $collection)`, `unbindRepeater(string $sectionId, string $key)` (copies rows into the instance value).
- Richtext: contenteditable with B/I/link/H2/H3/list/quote/code buttons; stores HTML; `x-init` from value, `@input.debounce` → `$wire.set`.

- [ ] Verify with puppeteer: add a guide row, edit richtext, see the guides index update; bind/unbind a repeater in the inspector.

### Task 8: Assistant engines, threads, system prompt

**Files:**
- Create: `src/Services/Assistant/Engines.php`, `Threads.php`, `SystemPrompt.php`
- Modify: `config/studio.php` (`assistant.engines.claude.bin`, `.codex.bin`, `assistant.model`)

**Interfaces:**
```php
final class Engines {
  public function available(): array;   // ['claude' => ['label' => 'Claude Code', 'bin' => '/path', 'ok' => true], 'codex' => [...]]
  public function command(string $engine, string $prompt, ?string $session, string $systemPrompt): array; // argv
}
final class Threads {
  public function all(): array; public function find(string $id): ?array;
  public function create(string $engine): array; public function rename(string $id, string $title): void;
  public function delete(string $id): void;
  public function append(string $id, array $message): void;   // {role,text,activity,files,at}
  public function setSession(string $id, string $sessionId): void;
}
final class SystemPrompt { public function build(array $context): string; }  // context: page, sections, selected, element
```
- Binary discovery: config path → `which` via `Symfony\Component\Process\ExecutableFinder`, plus `~/.claude/local/claude` and `~/.local/bin`.

- [ ] Verify: `php artisan tinker` — `Engines::available()` shows claude ok, codex missing; `SystemPrompt::build` mentions the current page's refs.

### Task 9: Turn runner + SSE endpoints

**Files:**
- Create: `src/Services/Assistant/TurnRunner.php`, `src/Http/Controllers/AssistantController.php`
- Modify: `routes/web.php` (gated: 404 unless `DevMode::enabled()`)

**Interfaces:**
- `POST api/assistant/turn` `{thread, engine, prompt, context}` → `{turn: uuid}` (stores the pending prompt in `storage/studio/assistant/turns/<uuid>.json`).
- `GET api/assistant/stream/{turn}` → `text/event-stream`. Events: `text {delta}`, `activity {kind: read|edit|write|bash|search|other, label, path?}`, `files {paths}`, `done {text, session, usage}`, `error {message}`.
- `DELETE api/assistant/stream/{turn}` writes a stop flag the runner polls; runner kills the process.
- `TurnRunner::run(array $turn, callable $emit): array` — spawns via `Process` (`setTimeout(null)`, cwd `base_path()`, `HOME` inherited), reads stdout line-by-line, parses:
  - claude stream-json: `assistant` messages → `content[]` `text` (emit `text` if not partial-streamed) / `tool_use` (activity from `name` + `input.file_path|command|pattern`); `stream_event` `content_block_delta.text_delta` → `text`; `result` → `done` (`result`, `session_id`, `total_cost_usd`, `duration_ms`).
  - codex `--json`: `thread.started` (thread_id → session), `item.completed` `agent_message` → `text`, `command_execution` → activity bash, `file_change` → files, `turn.completed` → done.
- Threads: `Threads::append` for user (at turn start) and assistant (at done, with activity + files).

- [ ] Verify with curl: `curl -N` the stream for a prompt like "List the pages in storage/studio/draft/pages and reply with their titles" — events arrive, `done` has a session id, second turn with `--resume` remembers the first.

### Task 10: Assistant panel UI + canvas hooks

**Files:**
- Create: `src/Livewire/AssistantPanel.php`, `resources/views/livewire/assistant-panel.blade.php`
- Modify: `resources/js/studio.js` (`Studio.assistant` stream client; iframe element-select mode posting `studio:element-selected {sectionId, ref, path}`), `resources/views/iframe.blade.php` (element-select cursor + hover outline), `resources/css/studio.css`

**Interfaces:**
- `AssistantPanel` public: `threads`, `activeId`, `newThread()`, `open(id)`, `rename(id,title)`, `delete(id)`, `setEngine(name)`; messages are loaded from `Threads` on render; streaming happens in Alpine and calls `$wire.$refresh()` on `done`.
- Message bubbles: user (accent), assistant (raised) with a live activity line while busy ("Editing resources/views/designer/heroes/pilot-hero.html…"), then "More details · N files" disclosure listing paths.
- Composer: chip for selected section (`studio:select-section` listener), element-select toggle → sends `studio:to-iframe {type:'studio:element-select', on}`; chip text `hero-split › header › nav`.
- After `done`: dispatch `studio:refresh-preview` + `studio:code-saved`; toast "Assistant changed N files".
- Suggestions row (3 canned prompts from the page's refs) shown on an empty thread.

- [ ] Verify with puppeteer on dev mode: send "Change the hero heading to 'Hello from the assistant'" → activity shows Edit, canvas refreshes with the new heading, thread persisted after reload.

### Task 11: Docs + final verification

- Modify: `CLAUDE.md` (rail, panels, collections, bindings, assistant), `docs/authoring-sections.md` (`source:` on repeaters), `config/studio.php` comments.
- Full pass: dev-reset + Pilot seed, exercise each panel, mobile canvas unaffected, publish carries collections, export inlines bound rows.
- Then one commit (user instruction) — message lists the five parts.
