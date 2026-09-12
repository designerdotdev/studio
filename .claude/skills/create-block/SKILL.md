---
name: create-block
description: Use when Tony asks to create, convert, or add a block or section to the Designer block library — from a screenshot or image, a Relume or shadcnblocks block (URL or React/Tailwind code), a piece of HTML, or a written brief ("make a pricing block with a toggle", "turn this hero into a block"). Also use to review a block for copied copy, images, or reference leaks. Not for a whole template (create-designer-template).
---

# Creating a Designer block

A block is one section, ready to drop into **any** Designer site: a
`.blade.php` + `.yml` pair in the shared token vocabulary, with its own
copy, its own imagery, one considered interaction, a preview, and a
`block.json`. It lives in the block library repo and installs into a site's
`resources/designer/views/components/sections/`, where Studio's library
sync picks it up and the Add Section picker offers it.

The bar: **nobody looking at the finished block can tell which Relume or
shadcnblocks block it started from**, and a site owner can change every
word of it. The reference contributes a composition and a set of
proportions — nothing else survives.

**REQUIRED SUB-SKILL:** `designer-craft` — read its four references before
writing markup. The lint it ships is the gate. `docs/authoring-sections.md`
is the mechanical contract.

**References in this skill:**

- [references/categories.md](references/categories.md) — the library
  taxonomy (Relume + shadcnblocks → Studio categories), and per category
  the composition recipe, required fields, the interaction detail, and the
  generic tell to avoid.
- [references/conversion.md](references/conversion.md) — the React /
  shadcn / Relume → Blade + tokens mapping table.

## Where blocks live

```
~/Sites/designer-blocks/                       the library repo (git)
└── blocks/<category>/<slug>/
    ├── <slug>.blade.php                       the section
    ├── <slug>.yml                             the editor contract
    ├── block.json                             title, category, tags, source, product, interaction
    ├── preview.png                            1280px capture after imagery replacement
    ├── collections/<name>.json (+ .yml)       only if the block binds a collection
    └── images/*.jpg                           only if the block ships rasters
```

Slugs are descriptive, never numbered: `hero-split-window`,
`pricing-toggle-three`, `nav-mega-latest`, `testimonials-wall`,
`faq-two-column`. Studio names the section `sections-<slug>`; the yml
`category:` must be one of the library categories in categories.md so the
picker groups it.

```bash
.claude/skills/create-block/scripts/new-block.sh <category> <slug> "<Title>"        # scaffold the folder
.claude/skills/create-block/scripts/install-block.sh <block-dir> <app-root> [--preview]   # copy into a site (+ a /preview-<slug> page)
```

## Steps

### 1. Capture the reference (structure only)

- **URL** (relume.ai, shadcnblocks.com, any site): open it in the browser
  at 1440 and 390 and screenshot the block; for shadcnblocks also read the
  code tab if it is shown. `WebFetch` returns text, not a render — never
  reconstruct a block from a text summary.
- **Code** (React/shadcn, Relume React, HTML): read it whole.
- **Image**: Read it; measure proportions from it.
- **Brief only**: pick the closest recipe in categories.md and design from
  it.

Write a six-line *reference read* into the scratchpad: grid and column
proportions; alignment (centred vs left); type roles present (eyebrow,
heading, body, meta); element inventory (badge, two buttons, avatars,
rating, image, N cards); spacing rhythm; any interaction. That is the whole
inheritance. **Then make at least one deliberate structural change** — the
image side, the card count, a full-bleed edge, the eyebrow treatment, the
proof element — so the block has its own identity.

### 2. Invent the product and write the copy

`designer-craft/references/copy.md`. One invented product per block,
named, with a job; every default written in its voice; **length parity**
with the reference so the layout still holds. The reference's headline,
body, button labels, badge text, rating line, and names are all replaced —
including the ones that look harmless ("New Release", "5.0 from 200+
reviews"). Buttons name outcomes.

### 3. Map to Designer

conversion.md is the table. The rules that matter most:

- **Tokens only.** `text-muted-foreground` → `text-muted`, `bg-primary
  text-primary-foreground` → `bg-accent text-accent-ink`, `bg-card` →
  `bg-panel`, `border` → `border-line`, star yellow → `fill-accent` (or a
  named token the site may not have — so prefer the accent). No palette
  utilities, no hex. The lint fails them.
- **Editable everything.** Every string, href, image, toggleable element,
  and list is a field. Optional elements get a `toggle`; layout variants
  (`align`, `tone`, `imageSide`) get a `select`; lists get a `repeater`.
- **Repeater rows ship in the yml `default:` list** and are mirrored in
  `@props` as `(object)` literals, so the block renders complete the moment
  it is added. A block whose list is naturally site-wide (plans, FAQ,
  testimonials, logos) *also* declares `source: collections.<name>` and
  ships `collections/<name>.json` + `.yml`, so the owner can rebind.
- **Icons are inline SVG** shipped in the block (Heroicons-style, 16/20px,
  `fill="currentColor"` or 1.5px stroke). No lucide imports, no icon
  fonts, no emoji.
- **Container:** `mx-auto w-full max-w-6xl px-6`; section padding `py-24
  sm:py-32` (tight variants `py-16 sm:py-20`).
- **Comments:** an HTML comment at the top saying what the block is and
  where its data lives.

### 4. Replace the imagery

`designer-craft/references/imagery.md` — the Higgsfield MCP (`generate_image`,
`generate_image_batch`) is the image source for everything that is not drawn
in markup or inline SVG. A product block gets a **drawn
window with real-looking data** (never grey skeleton bars — those are the
placeholder look with the diagonal removed). A testimonial or team block
gets `assets.ui.sh` avatars or Higgsfield portraits with one consistent
prompt tail. A gallery or lifestyle block gets Higgsfield photographs,
bright and true-colour, saved under `images/` and referenced as
`/designer/images/blocks/<slug>/<name>.jpg`. Logo strips become invented
wordmarks. Nothing from `shadcnblocks.com/images` or Relume's placeholder
set ships.

### 5. Add the one interaction detail

Every block gets one thing that answers the cursor or the scroll, from
`designer-craft/references/motion.md`:

- `data-reveal` on the block's children (the template motion contract
  guarantees the reveal system exists; without it nothing is hidden).
- Hover feedback on every link and card (colour, arrow nudge, ≤ 2px lift).
- Category-specific: a `<details>` accordion for FAQ; a `role="switch"`
  monthly/annual toggle for pricing; scroll-snap for a carousel; a marquee
  for logos; a resolving beat inside a drawn window for a hero.

Behaviour the block needs beyond CSS ships **inside the block** as a short
guarded `<script>` at the end of the section (vanilla, ≤ 40 lines,
idempotent via a `data-<slug>-ready` attribute, reduced-motion aware). No
Alpine dependency, no Blade echo inside a `data-`/`x-` attribute that JS
reads — put values in `data-*` attributes and read them.

### 6. Verify

```bash
python3 .claude/skills/designer-craft/scripts/lint-site.py ~/Sites/designer-blocks/blocks/<category>/<slug>
.claude/skills/create-block/scripts/install-block.sh ~/Sites/designer-blocks/blocks/<category>/<slug> <lab-app> --preview
```

The lab app is `create-designer-template`'s (`scripts/lab.sh`) with any template
installed — a block must look right on **two** templates with different
palettes (Pilot and a dark one, say). `install-block` adds a
`/preview-<slug>` page wrapping the block in the site layout. Then:

- `snap.mjs --pages /preview-<slug> --widths 390,768,1280` and once more
  with `--reduced-motion`; nothing hidden, no horizontal overflow.
- Open `/studio`, add the block from the picker, edit one field of each
  type, publish, confirm one attribute changed.
- **Invoke `impeccable`** on the 1280 and 390 shots; grade against the
  premium bar and the category's "generic tell".
- **The recognisability check:** put the reference screenshot beside the
  block's preview. If the copy, the images, the icons, or the exact layout
  would let someone name the source, it is not done.

### 7. Record it

`block.json`:

```json
{
    "slug": "hero-split-window",
    "title": "Split hero with product window",
    "category": "heroes",
    "tags": ["hero", "saas", "two-column", "product-window", "social-proof"],
    "product": "Ledger — invoicing for small agencies",
    "source": { "kind": "shadcnblocks", "ref": "hero7", "kept": "two-column proportions, badge + proof row" },
    "interaction": "arrival cascade; window rows resolve once; arrow nudge on secondary button",
    "collections": [],
    "images": [],
    "tokens": ["canvas", "panel", "raised", "line", "ink", "muted", "faint", "accent", "accent-ink"]
}
```

`source` is internal provenance for the library; nothing in the markup or
copy refers to it. Capture `preview.png` at 1280 from the lab preview page
(`snap.mjs --thumbnail` on the preview URL, or a clipped shot of the
section). Commit the block folder.

## Red flags — stop and fix

- The reference's headline, badge text, rating line, button labels, or
  names still present, even "temporarily"
- `placeholder-*.svg`, `shadcnblocks.com/images`, Relume placeholders, real
  brand logos, lucide imports
- Grey skeleton bars standing in for a product visual
- A palette utility or hex in the markup (`text-amber-400` stars,
  `bg-zinc-950` windows, `text-white` on buttons)
- A string or href with no field; a list that is not a repeater; an
  optional element with no toggle
- Zero interaction — no reveal, no hover, no category detail
- A block that only looks right on the template it was drawn against
- Skipping the recognisability check because "the layout is generic
  anyway"
- Nested repeaters, `children` as a sub-field key, a `select` without
  `options`
- A `<script>` that runs twice when the block is placed twice

## Common mistakes seen in baseline runs

| Seen | The fix |
|---|---|
| Stars kept in `text-amber-400` "because that's how ratings look" | `fill-accent`; the token layer decides what the accent is |
| "5.0 from 240+ reviews" carried over with the number nudged | The proof line is rewritten for the invented product ("4.9 on G2 from 312 agencies") or replaced with a different proof element |
| Product visual drawn as grey text bars and empty boxes | A window with a title bar, real rows, one figure per card, one overlapping element |
| `data-reveal` left out "because I can't assume main.js handles it" | The motion contract guarantees it; without JS nothing is hidden anyway |
| No `block.json`, no preview, no provenance | Step 7 is part of done |
| Repeater rows written with double-quoted PHP keys "so the lint wouldn't see them" | The lint is fixed instead (it now parses nested row literals); dodging a finding is never a fix |
| Rounded-full pills on every button and badge because the exemplar used them | Read the target template's nav: match its button radius via a `select` (`shape: pill|rounded`) or default to `rounded-lg` |
