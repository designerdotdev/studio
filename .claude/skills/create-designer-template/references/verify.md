# Verifying a template (Phase 5)

The tooling proves the install bar and the editable bar. The screenshots
prove the taste bar. Both halves are required; a template that passes one
and skips the other is not verified.

All scripts live in `.claude/skills/create-designer-template/scripts/`. `T` below is
the template folder (`~/Sites/site-templates/<slug>`), `LAB` the throwaway
app, `SLUG` the template slug.

## 1. Lint — zero is the only pass

```bash
python3 .claude/skills/designer-craft/scripts/lint-site.py $T
```

## 2. The lab app (once per machine, reused afterwards)

Never verify against the host app (`~/Sites/designer`) — its
`resources/designer` is a real site that `--force` deletes. The lab is a
throwaway Laravel app with this package path-symlinked and a catalog that
points at local template folders.

```bash
scripts/lab.sh create  <scratchpad>/lab           # composer create-project + require designer/studio (2–3 min)
scripts/lab.sh add     <scratchpad>/lab $SLUG $T   # register the local folder in the lab catalog
scripts/lab.sh import  <scratchpad>/lab $SLUG      # rsync the WORKING TREE into the clone cache, then studio:templates:import --force
scripts/lab.sh serve   <scratchpad>/lab 8765       # php artisan serve in the background (pid in lab/.serve.pid)
```

`import` copies the working tree, so uncommitted edits are visible without
a commit. Re-run `import` after every batch of section changes. After an
import wait ~3s before requesting pages — `php -S` runs OPcache with a 2s
revalidate, so compiled views of the previous install can serve once.

## 3. HTTP sweep — every URL answers 200

```bash
scripts/lab.sh sweep <scratchpad>/lab http://127.0.0.1:8765
```

Walks `resources/designer/views/pages` (index → `/`, `pricing` →
`/pricing`, a `[coll.slug]` page → the first entry's slug), plus
`/sitemap.xml`, `/studio`, `/studio/preview`, and the editor iframe. Any
non-200 is a stop. Then open `/studio` in the browser and confirm every
section appears in the Add Section picker with its title.

## 4. Screenshots — the taste pass

```bash
NODE_PATH=<scratchpad>/node_modules node scripts/snap.mjs http://127.0.0.1:8765 <scratchpad>/shots
NODE_PATH=<scratchpad>/node_modules node scripts/snap.mjs http://127.0.0.1:8765 <scratchpad>/shots-rm --reduced-motion
```

(`npm i playwright` in the scratchpad once; chromium is provisioned.)
Use `snap.mjs` for the shots rather than the in-app browser pane: the pane
is shared with other Claude sessions and a subagent's screenshots get
re-navigated mid-run. Read the PNGs with the Read tool.
Widths 390, 768, 1024, 1280, 1440; every page from the sitemap; the page is
scrolled through first so reveals have fired; tall pages are captured in
1800px chunks so nothing tiles or repeats. `--reduced-motion` emulates the
media query: **every** element must be visible and still in those shots.
A hero whose signature beat resolves over a few seconds needs a second set
with `--settle 6000 --pages /` so the resolved state is what gets graded.

Then **invoke `impeccable`** and grade every desktop and 390 shot against
`designer-craft`'s premium bar, line by line: type scale, section rhythm,
colour restraint, composition variety, states, the signature moment,
mobile. Write the verdict per section into the scratchpad. Anything graded
"default card grid", "cramped", "centred wall", or "the accent is
everywhere" gets redesigned, then re-shot. This is the step that separates
a premium template from a correct one.

**The 1024–1280 shots matter most for the editor** — that is the canvas
width on a laptop (screen minus ~344px of sidebar). A two-column hero that
only works from 1280 looks broken in Studio even when the live site is
fine.

## 5. Interaction pass (real browser, by hand)

At 1440 and 390 on the live lab site (not `/studio/preview/*` — those set
`pointer-events: none`):

- Hover each nav dropdown: opens after a beat, survives diagonal travel
  into the panel, closes on leave/Escape/outside click; one open at a
  time; caret rotates.
- Tab through the page: a visible focus ring on every link, button,
  summary, and input; the dropdown opens on focus and closes on `focusout`.
- Mobile: the hamburger is ≥ 44px, the sheet slides, body scroll locks,
  the two auth buttons are at the bottom, links close it.
- Hover every card and link: something answers (colour, arrow, lift ≤ 2px).
- The signature beat resolves once, then stays.
- Accordions open smoothly; the pricing toggle rewrites every price.
- No horizontal scroll at 390 (`document.documentElement.scrollWidth ===
  innerWidth`).

## 6. Contrast + token sweep

Compute (don't eyeball) `lede`, `muted` on `canvas` ≥ 4.5:1; `faint` ≥
3:1; `accent-ink` on `accent` ≥ 4.5:1; `shade-ink` on `shade` ≥ 4.5:1.
The lint already fails palette utilities and hex in markup.

## 7. Editor sweep — the editable bar

In `/studio` on the lab site: open **every** section in the inspector;
edit one field of each type it has (text, textarea, url, image, select,
toggle, repeater row add/reorder/delete); watch the canvas follow; publish;
`git -C $LAB diff --stat resources/designer` shows the changed attributes
and nothing else. Edit a nav link and a footer link under the Layout tab
and confirm `data/site.json` changed.

## 8. Inline-editing coverage

```bash
cd <scratchpad>/lab && php artisan studio:inline:verify
```

(dev mode only — see `Support\DevMode` for how the lab enables it.) It
walks the installed site plus every clone in `storage/studio/templates`,
so register only the template under test. Two lines matter: **Coverage**
(every declared field maps to a rendered position — an unmapped field is
either a `select` used only in a comparison, a toggle wrapping an
`<x-…>` tag, or a bug) and **Inertness** (`0 diverged`). Use
`--section=<name>` to narrow it while iterating. The closing "Coverage
regressed: N < 365 expected" line compares against the host app's
baseline (Monarch + two clones) and means nothing in the lab.

## 9. The round-trip invariant

```bash
scripts/lab.sh roundtrip <scratchpad>/lab $SLUG
```

Installs with `replace: true`, hashes every file under `resources/designer`,
flushes the mirror, and compares; then forces a re-read and flushes again.
Zero changed files both times, or the writer would rewrite an untouched
template on first publish. A failure here is a section-authoring problem
(a value the reader normalises differently from how it was written —
unquoted attribute, a default duplicated on the tag) until proven
otherwise.

## Done looks like

- lint: `0 finding(s)`
- sweep: every URL 200
- shots + reduced-motion shots reviewed, `impeccable` grades recorded, no
  "redesign" verdicts outstanding
- interaction pass notes at 1440 and 390
- contrast table
- editor sweep: one attribute changed per edit, site.json changed for
  layout edits
- inline:verify: every unmapped field explained, `0 diverged`
- roundtrip: `0 changed` twice
