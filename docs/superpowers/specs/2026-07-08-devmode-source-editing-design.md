# Dev mode — edit section source in the editor

**Date:** 2026-07-08 · **Status:** approved direction (editor choice and
mechanics delegated to implementation)

## Problem

Section templates (`resources/views/designer/<category>/<name>.html` +
`.yml`) can only be edited on disk. Developers want to edit the selected
section's source without leaving the Studio.

## Decisions

- **Editor: CodeMirror 6, statically bundled** into `studio.js`. Monaco
  (DevDojo component) needs web workers + code-split chunks that this
  package's single-file Vite build and asset publishing pipeline can't
  serve; CodeMirror 6 bundles cleanly and is ~10× smaller. The bundle is
  editor-only (never loaded on public pages).
- **Availability gate:** `config('studio.dev_mode')` — `null` (default)
  means enabled only in the `local` environment; `true`/`false` force it.
  Editing app source files from a browser must never be silently possible
  in production.
- **UI toggle:** "Dev mode" row in the hamburger menu (rendered only when
  the server-side gate allows it), persisted in localStorage
  (`studio.devmode`) via the existing Alpine `$store.studio`.

## Design

### Entry point

When dev mode is on and a section is selected, the panel inspector header
shows an "Edit code" (`</>`) icon button. It opens a code modal for that
section's component. (Canvas-toolbar entry can come later.)

### Code modal (in `home.blade.php`)

- Tabs: **HTML** and **YAML**, one CodeMirror instance each (created via
  `Studio.codeEditor(host, { language })` from the bundle).
- Shows the target file paths; hint that sections must stay inside the
  supported Blade subset (`docs/authoring-sections.md`).
- Save via button or Cmd/Ctrl+S; Escape/Cancel closes.

### Backend (`DevModeController`)

- `GET /studio/api/dev/components/{name}` → current `html` + `yaml`
  source and resolved paths.
- `PUT /studio/api/dev/components/{name}` → validates: YAML parses
  (symfony/yaml), `name` key matches the URL name (renames are not
  allowed — filename must match), html present. **Copy-on-write:** if
  only the package copy exists, write to `resource_path('views/designer')`
  (the app copy wins per `DesignSyncService`); never write into the
  package from the app.
- After write: re-sync the library (`DesignSyncService::syncAll()` skips
  unchanged files) so fields/preview data update.
- Both routes 404 unless the dev-mode gate passes; throttled 30/min.
- `DesignSyncService` gains `sourcePathsFor($name)` to resolve a
  component's `.html`/`.yml` pair (app copy preferred).

### After save (frontend)

Refresh the canvas iframe (`studio:refresh-preview`) and tell the panel
to re-resolve the selected section's fields (Livewire event), since the
YAML may have added/removed fields.

### Editing semantics

Editing source edits the **component**, not the instance — all sections
referencing it change, same as editing the files on disk. The library is
not draft-workspaced, so source edits are immediately live for rendering;
this matches today's on-disk editing behavior.

## Verification

In the host app: toggle dev mode, edit a section's HTML (visible copy
change) and YAML (add a field), save — canvas updates, new field appears
in the inspector, files on disk under `resources/views/designer/` updated;
invalid YAML rejected with a clear error; endpoints 404 when
`studio.dev_mode` is false.
