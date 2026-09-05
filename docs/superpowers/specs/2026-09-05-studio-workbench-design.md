# Studio workbench: rail, Pages, Media, Collections, Assistant

Date: 2026-09-05. Approved scope: bring the DevDojo Sites builder's editing
surfaces into Designer Studio — an icon rail with Assistant / Pages /
Content / Media panels, collections as first-class data, and an Assistant
that drives the user's locally installed `claude` (or `codex`) CLI with the
CLI's own file tools over the host app directory. No commit until the whole
set is done.

## 1. Shell: the rail and the panel region

The sidebar becomes two columns: a 48px **rail** of icon buttons on the far
left and the existing 320px **panel** beside it. The rail lists, top to
bottom: Assistant (sparkles), Sections (the current panel), Pages, Content,
Media. One rail item is active at a time; clicking the active one collapses
the panel (the existing `$store.studio.sidebar` flag keeps meaning
"panel open"). The choice persists in `localStorage['studio.rail']`.
Selecting a section on the canvas switches the rail to Sections and opens
the inspector exactly as today.

The current Sections / Layout / Page tabs stay inside the Sections panel
untouched. Pages, Content and Media are new Livewire components mounted in
the same `<aside>`; only the active one renders (`x-show` on the wrapper,
`wire:ignore` not needed because each is its own component).

Files: `components/layouts/app.blade.php` (rail + panel columns),
`home.blade.php` (mounts the four panels), `studio.css` (`s-rail`,
`s-rail-btn`, `s-rail-btn.is-active`).

## 2. Pages panel (`Livewire/PagesPanel`)

A list of every page with title, slug and a "home" marker; the current page
highlighted. Actions per row (hover kebab): Open, Rename, Duplicate,
Set as home, Delete (confirm). A "New page" button opens the existing
create-page modal (`studio:open-create-page`). Rows are drag-sortable
(SortableJS helper already in studio.js) and the order is stored as
`order` on each page doc; `PageRepository::all()` sorts by it. The Page tab
in the Sections panel keeps owning SEO/meta.

Backend additions: `PageRepository::reorder(array $slugs)`,
`PageRepository::rename(slug, title)`. `setHome` writes
`studio.page_routing.home_slug`'s runtime equivalent into `site/data.json`
(`home_slug`) and `SiteUrls` reads it before the config default.

## 3. Media panel (`Livewire/MediaPanel`) + `MediaLibrary` service

A library over `public/studio-uploads` (uploads root already used by image
fields). `Services/MediaLibrary` lists files and folders (name, url, size,
dimensions, mtime), creates/renames/deletes folders, renames/moves/
duplicates/deletes files, and accepts uploads (reusing the validation in
`StudioController::upload`). Paths are always relative to the root and
validated against `..`.

UI: folder breadcrumb, grid of thumbnails, drag-and-drop or button upload
(multi-file), search box, per-item context menu (Copy URL, Rename, Move to
folder, Duplicate, Delete) and a lightbox preview. Every image field gets a
"Choose from library" affordance: it opens the Media panel in **picker
mode** (`studio:media-pick` event carrying a callback id); clicking an
image returns its URL to the field. Sub-field images in repeaters use the
same path.

Routes: `GET /api/media?dir=`, `POST /api/media/upload`, `POST
/api/media/folder`, `PATCH /api/media` (rename/move), `POST
/api/media/duplicate`, `DELETE /api/media`.

## 4. Collections (`Storage/CollectionRepository`, Content panel)

A collection is a JSON document at `storage/studio/collections/<name>.json`
(workspaced draft/live like pages; published with the site):

```json
{
  "name": "guides",
  "title": "Guides",
  "fields": { "title": {"type": "text"}, "content": {"type": "richtext"}, ... },
  "rows": [ { "id": "uuid", "title": "…", ... } ],
  "updated_at": "…"
}
```

Field types: text, textarea, richtext, url, image, select, toggle, number.
`rows[*].id` is a UUID; `slug` is a plain text column by convention.

**Binding.** A section instance may carry `bindings`, mapping a repeater
field to a collection: `"bindings": {"items": "collections.guides"}`.
`SectionRenderer::context()` (via a new `CollectionBinder`) replaces the
bound variable with the collection's rows at render time, in every render
path (canvas, live page, draft preview, export, RenderController). The
inspector shows a bound repeater as a locked "Bound to Guides — edit in
Content" panel with an Unbind button (copies rows into the instance) and a
Bind menu on unbound repeaters (collections whose fields cover the
sub-fields). Section yml may declare `source: collections.<name>` on a
repeater as the default binding applied when the section is added.

**Template import.** `TemplateImporter` currently inlines
`resources/data/collections/*.json` into instance values. It now writes
each collection as a collection document (schema from the sibling `.yml`
when present, inferred from row keys otherwise) and records a binding on
every instance whose bound attribute was `$<collection>`.

**Content panel (`Livewire/ContentPanel`).** Collection cards (title, row
count) → table view (columns from the schema, sortable, add row, delete
row, reorder) → entry editor (a form of the schema's field partials;
richtext uses a minimal contenteditable toolbar that stores HTML). "New
collection" asks for a title and fields; "Manage fields" edits the schema.
Rows save through Livewire and dispatch `studio:refresh-preview` so bound
sections update.

## 5. Assistant (`Livewire/AssistantPanel` + `Services/Assistant/*`)

Runs only when `DevMode::enabled()` **and** an engine binary is found.
Engines: `claude` (Claude Code CLI) and `codex` (Codex CLI). The picker
lists both, disabling one whose binary is missing from `PATH`
(`config('studio.assistant.engines')` may pin absolute paths).

**Threads.** `storage/studio/assistant/<thread-id>.json` (not workspaced):
`{id, title, engine, session_id, created_at, messages: [{role, text,
activity: [{kind, label, detail}], files: [path…], at}]}`. History menu
lists threads; New thread; Rename; Delete.

**Turn protocol.** `POST /api/assistant/turn` `{thread, engine, prompt,
context}` starts a turn and returns immediately; `GET
/api/assistant/stream/{turn}` is an SSE endpoint (Symfony Process, cwd =
`base_path()`) that spawns:

- claude: `claude -p <prompt> --output-format stream-json --verbose
  --include-partial-messages --permission-mode acceptEdits
  --dangerously-skip-permissions --append-system-prompt <studio prompt>
  [--resume <session_id>] [--model …]`
- codex: `codex exec --json -C <base_path> --full-auto <prompt>` or
  `codex exec resume --json <thread_id> <prompt>`

and translates the JSON-line events into SSE events: `text` (delta),
`activity` (tool start/end: Read/Edit/Write/Bash with a short label and
the path), `files` (paths touched), `done` (final text, session id,
usage), `error`. The turn is also appended to the thread JSON on the
server so a refresh restores it. Process kill on client disconnect or a
Stop button (`DELETE /api/assistant/stream/{turn}`).

**Studio system prompt** (`Services/Assistant/SystemPrompt`): explains the
storage layout (pages/layouts/blocks/collections JSON and their shapes,
the draft workspace and that the editor works in `draft/`), the section
pairs in `resources/views/designer/<category>/`, the site document, the
authoring rules from `docs/authoring-sections.md`, and that after editing
it should say what changed in one or two sentences. The prompt is
generated per turn with the current page slug, its section refs, and the
selected section (ref + id + field keys) so "the hero" resolves.

**Context chip.** The composer shows a chip for the selected section
("hero-split · page hero") and, in element-select mode (crosshair button),
for a clicked element inside the canvas (`section > header > nav` path,
gathered in the iframe and posted back via the bridge). Both are sent as
`context` and rendered into the prompt.

**After a turn** the panel dispatches `studio:refresh-preview` and
`studio:code-saved` (EditorPanel reloads fields), and the toast shows the
files list from the "More details" disclosure.

**Composer**: textarea (Enter sends, Shift+Enter newline), engine picker,
element-select toggle, Stop while busy, suggestions row (three canned
prompts computed from the page: "Tighten the hero copy", "Add a FAQ
section", "Make the nav sticky") until the thread has messages.

## 6. Publish, draft, and export

Collections are part of `PublishService::status/publishAll/discardAll`
(the `collections/` directory joins pages/layouts/blocks). Blade export
inlines bound rows at export time (the renderer already does). The
assistant's edits land wherever the CLI writes; the panel's refresh shows
them, and Publish carries them live as usual.

## 7. Testing

No test suite exists; verification is against the host app with headless
Chrome / puppeteer (see the dev-harness memory). Each part ships with a
curl-level check of its endpoints and a screenshot of its panel.

## 8. Out of scope

Credits, cloud publishing, App Mode, the Code mode file tree, premium
wizard, Tails import, multi-user permissions on threads.
