---
name: create-designer-template
description: Use when Tony asks to create, build, or design a brand-new Designer Studio site template (Designer's own version of the DevDojo create-template skill) — a premium SaaS landing site, marketing site, or any whole starter site in the site-templates format that Designer Studio installs. Also use when asked to add a page or a whole section set to a template in progress. Not for a single section or block (create-block), and not for converting a legacy template.
---

# Creating a Designer template

A template is a whole starter site in the `site-templates` repository
format that Studio installs into a Laravel app (`docs/site-templates.md`).
The build is: **brief → name + brand → scaffold → design direction →
sections → imagery → verify → ship on ask.** Three bars, all required:

1. **The install bar** — installs into a throwaway app, every page serves,
   every section syncs into the library, and the reader/writer round trip
   changes zero bytes.
2. **The editable-everything bar** — every visible piece of content is a
   yml field or a collection row. A hard-coded string a site owner would
   want to change is a bug.
3. **The taste bar** — it looks like a designer at Linear, Vercel, or
   Stripe made it. `designer-craft`'s premium bar defines this in checkable
   terms. It is the bar the lint cannot see, so it gets a screenshot pass.

**REQUIRED SUB-SKILLS:**

- **`designer-craft`** — read it and its four references before any
  markup: tokens, motion, imagery, copy. The lint it ships is the gate.
- **`impeccable`** — invoke at Phase 2.5 before writing the brief, and
  again at Phase 5 to grade the screenshots.
- **`design`** (ui.sh guidelines) — invoke at Phase 2.5; per section, read
  the guideline file for that surface (headers, pricing-cards, logo-clouds,
  testimonials, footers, section-layout, heading-groups…).
- **`create-block`'s** `references/categories.md` — the composition
  recipes per section type. A template's sections are blocks with a
  shared brand.

Invoke them; don't paraphrase them from memory. Where a sub-skill is more
specific than this file, it wins. `docs/authoring-sections.md` (the
props ↔ yml contract) beats everything.

**Exemplars** — clones live in `storage/studio/templates/` of the host app
(`~/Sites/designer`); clone others from `github.com/site-templates/<slug>`
into the scratchpad. Study **pilot** (the new-model reference: tokens,
progressive-enhancement `main.js`, a drawn console as the signature) and
**draft** (section granularity, mega-dropdown, updates + changelog + docs
depth). Study structure and field coverage, not their palettes.

## The SaaS landing anatomy (the default template type)

Tony's templates are mostly for SaaS products: a nav with **Sign in** and
a primary **Get started / Sign up** on the right, a hero that demonstrates
the product, and a pricing page. The page plan, unless the brief says
otherwise:

| Page | Sections (in rhythm order — shapes alternate on purpose) |
|---|---|
| `index` | hero (signature) → logos → how-it-works or steps → bento features → split (product detail, image right) → stats or proof band (dark `shade`) → split (align left) → testimonials → pricing teaser or FAQ → cta |
| `features` | page-header → feature rows (3–4 alternating) → integrations grid → comparison or FAQ → cta |
| `pricing` | page-header with monthly/annual toggle → 3 plans, one highlighted → comparison table (≤ 8 rows) → FAQ → cta |
| `changelog` or `updates` | page-header → dated entries from a collection (+ a `[updates.slug]` page if entries have bodies) |
| `404` | hand-written, on brand, one link home |

Six to nine sections on the home page. Every page shares nav + footer via
the layout; both have ymls (they surface under the Layout tab).

**The nav is a product in itself.** Brand mark + wordmark left; 3–5 links
with at most two dropdowns (one may be a mega-panel with descriptions and
a "latest" card); right side: a quiet link, an outlined **Sign in**, a
solid **Get started**. Transparent at rest, glass on scroll. Hover-intent
dropdowns per `designer-craft/references/motion.md`. Mobile: a 44px
hamburger and a sheet that flattens dropdowns into groups with the two
auth buttons at the bottom.

**The signature moment is the product, drawn.** For SaaS the hero shows
the app: a window with real-looking data, three panes or one focused
view, one overlapping element (a terminal, a toast, a notification), and
one motion beat that resolves once (rows arriving, a cursor, a chart
drawing). It is built in markup and token utilities, never a screenshot,
never a raster. Name it in the brief before building anything.

## Phase 0 — Brief

Gather, asking only for what is missing (autonomous runs: state the
assumption and continue):

1. **Product** — what it is, who buys it, the one number it improves.
   Invented, plausible, not the reference's business. Everything downstream
   describes it.
2. **Template type** — default SaaS landing; blog, portfolio, agency,
   docs, business change the page plan.
3. **References** — URLs, screenshots, a Relume/shadcnblocks block, a
   named template. Open each in the browser at 1440 and 390, screenshot,
   and write down *specific* signals (type scale, section padding, one
   layout idea, one interaction) — not "clean and modern".
4. **Name** — optional; Phase 1 proposes.
5. **Page plan** — from the anatomy table, adjusted to the product.

Present the page plan and the per-page section list before scaffolding;
it is the cheapest moment to change scope.

## Phase 1 — Name + brand

- **Name**: propose 4–5 single-word candidates that fit the product.
  Check each against `config/studio.php` (`templates.catalog`), `gh repo
  list site-templates --limit 100`, and `ls ~/Sites/site-templates`. Put
  them to Tony (AskUserQuestion) when he is present; the pick is the slug,
  the repo name, and the brand.
- **Logo**: 2–4 flat single-colour concepts from Higgsfield
  (`designer-craft/references/imagery.md`), then hand-trace the pick as
  inline SVG with `fill="currentColor"`. The mark lives in `nav.blade.php`
  and `footer.blade.php`; `public/favicon.svg` is a separate export with
  explicit fills and a `prefers-color-scheme` query.
- **No video** unless Tony asks for one by name.

## Phase 2 — Scaffold

Workspace: **`~/Sites/site-templates/<slug>`** — its own git repo, in the
org format, verified through a throwaway lab app (Phase 5). Never author
inside the host app's `resources/designer` or inside
`storage/studio/templates`.

```bash
.claude/skills/create-designer-template/scripts/scaffold.sh <slug> "<Name>" [light|dark]
```

creates the tree below with the starter `site.css`, a layout whose head
carries the fonts `<link>`, `@vite(['resources/css/site.css'])`, the
`.js` flag script, and `/js/main.js` `defer`; a nav/footer pair with ymls;
`site.json`; `template.json`; `favicon.svg`; `robots.txt`; `.gitignore`;
and a first commit.

```
<slug>/
├── template.json          name, dialect "blade", description, icon, repo, order, category, theme, accent, pages
├── thumbnail.png          Phase 6
└── files/
    ├── public/            favicon.svg · robots.txt · js/main.js · images/
    └── resources/
        ├── css/site.css   the @theme token layer + motion system
        ├── data/site.json name, nav_links, footer_links, social_links
        ├── data/collections/<name>.json (+ .yml for typed columns)
        └── views/
            ├── components/layouts/main.blade.php (+ .yml)
            ├── components/nav.blade.php (+ .yml) · footer.blade.php (+ .yml)
            ├── components/sections/*.blade.php (+ .yml each)
            └── pages/*.blade.php · pages/<dir>/[coll.slug].blade.php · 404.blade.php
```

`template.json` `category` is `landing` for SaaS (the picker filters on
it); `order` is max existing + 1; `accent` is the real hex; `pages` lists
the page titles in nav order.

**Tokens are decided here** — read `designer-craft/references/tokens.md`,
check the catalog for palette collisions, and write real values into
`@theme`. One palette, fixed. Dark is a brief decision, not a toggle.

## Phase 2.5 — Design direction

**Invoke `impeccable` and `design` now**, then write a one-page brief to
the scratchpad (never into the template tree). It is the north star every
section answers to:

- **Palette** — the token values and where the accent may appear.
- **Type scale** — the pairing plus actual sizes: hero display, h2, body,
  small, eyebrow.
- **Spacing rhythm** — section padding desktop/mobile; which section is
  the tight one and which the airy one.
- **Composition plan, per section** — one line each, chosen so shapes
  alternate (full-bleed, editorial two-column, bento, split, band, split,
  masonry, asymmetric close). This is where "six identical sections" is
  prevented — on paper.
- **The signature moment** — named and sketched in words.
- **Motion plan** — arrival cascade, reveals, the one resolving beat, the
  dropdown feel, reduced-motion fallback.
- **Voice** — two lines. Then every default is written in it
  (`designer-craft/references/copy.md`).
- **Closest catalog neighbour** and what differs.

Re-read the brief before each section.

## Phase 3 — Sections + fields

Every section is `sections/<name>.blade.php` + `<name>.yml`. Before its
markup: read the `design` guideline for that surface and the matching
recipe in `create-block/references/categories.md`. Then:

- **Editable everything.** Every string, href, image, toggleable block,
  and repeated list is a field or a collection row. Walk each finished
  section top to bottom asking "would a site owner change this?".
- **Parity.** Every yml default appears verbatim in `@props`. Toggle
  defaults are `"1"`/`"0"`. Repeaters are declared bare or `[]` and bound
  from the page (`:items="$plans"`) or from `site.json`
  (`:links="$site->nav_links"`), with `source:` in the yml.
- **Collections** for anything repeated across pages or with more than
  ~6 rows: `data/collections/<name>.json` (valid variable name, never
  hyphenated, never empty, ordered in-file) + `<name>.yml` typing the
  columns. Real content: write the plans, the changelog entries, the
  testimonials with names and companies.
- **Dynamic pages** for anything with a page per entry: ONE
  `pages/<dir>/[<collection>.slug].blade.php` over the collection; the
  entry's body is a `content` HTML string in the JSON. Never a page file
  per entry.
- **Tokens only.** No palette utilities, no hex in markup. The lint fails
  it.
- **Motion** from `designer-craft/references/motion.md`: `data-reveal` on
  section children, `reveal-N` cascade in the hero, hover states on every
  link and card, the dropdown recipe in the nav.
- Pages compose sections with only the attributes that differ from the
  defaults — the yml default IS the canonical page's content.
- **Comments in the section files** (HTML comments, so they survive into
  the page) explaining what the section is and where its data lives — the
  Pilot tone.

Run the lint after every section, not at the end:

```bash
python3 .claude/skills/designer-craft/scripts/lint-site.py ~/Sites/site-templates/<slug>
```

## Phase 4 — Imagery

`designer-craft/references/imagery.md`. Drawn product mocks first, inline
SVG second, Higgsfield MCP rasters for photograph-class needs (stage
landscapes, people, interiors, product shots, textured wallpapers), saved to
`files/public/images/` ≤ 1600px JPEG q80, every one behind an `image`
field. Wordmark logo strips are text. Avatars from `assets.ui.sh`.

## Phase 5 — Verify

Follow [references/verify.md](references/verify.md) end to end. In short:
lint at zero → lab app install (`scripts/lab.sh`) → HTTP sweep of every
page → screenshots at 390/768/1024/1280/1440 (`scripts/snap.mjs`) plus a
reduced-motion pass → **invoke `impeccable` and grade every screenshot
against the premium bar** → editor sweep (open each section, edit one
field of each type, publish, confirm one attribute changed) → inline
editing coverage (`studio:inline:verify`) → the round-trip invariant →
the 1024–1280px canvas check. Redesign what fails
the taste grade; do not ship a section that "passes lint but looks like a
default card grid".

## Phase 6 — Ship (only when Tony asks)

1. `thumbnail.png` — a 1440×900 capture of the page and scroll position
   that sells the design hardest (`scripts/snap.mjs --thumbnail`).
2. Commit; `gh repo create site-templates/<slug> --public --source=. --push`
   from inside the folder.
3. Add the catalog entry to `config/studio.php` (`repo`, `name`,
   `category`, one evocative `description` naming the standout surfaces)
   — this is what makes the template exist to the picker, so it lands only
   on an explicit "publish it".

## Red flags — stop and fix

- A string, href, or image in section markup with no yml field
- A yml default that differs from the `@props` default
- A hex/rgb/oklch outside `css/site.css`; `bg-zinc-*`/`text-white` in a
  section
- Building a section without having read its `design` guideline and the
  `categories.md` recipe — "I know what they'd say" means you don't
- A hero that is a screenshot or a raster instead of a drawn product
- A nav without Sign in + primary CTA, or dropdowns that snap open/closed
- Reveals that hide content under reduced motion
- A raster logo; a favicon using `currentColor`
- Lorem, "Coming soon", placeholder avatars, real brand logos
- A page file per collection entry; a collection that ships empty
- A catalog line before the org repo is pushed
- Skipping the screenshot grade because the lint passed
- Generating a video without an explicit ask

## Common mistakes seen in baseline runs

| Seen | The fix |
|---|---|
| Palette utilities (`bg-zinc-950`, `text-white`) in sections because "the design is monochrome" | Define the monochrome as tokens; `text-white` on ink is `text-canvas` |
| A hero window drawn with `text-emerald-500` status dots and `text-yellow-400` stars | Add `--color-success` or use `fill-accent`; tokens exist so the mock retunes with the palette |
| Nav dropdowns with no hover-intent delay and no Escape/outside-click handling | The `main.js` dropdown recipe; Pilot's is the reference |
| Six sections in the same centred shape | The composition plan in the brief, written before the first section |
| Fields for headings but not for links, eyebrows, or the window's inner copy | The top-to-bottom "would an owner change this?" walk, then the lint's `PROP_UNEDITABLE`/echo checks |
| A `pricing` collection named `pricing-plans.json` | Valid variable names only (`plans.json`) |
| Tokens named `--color-surface`, no `lede`, no `shade` family — so library blocks render with missing colours | The canonical names in `designer-craft/references/tokens.md`, all of them; extra tokens (`--color-warn`) are fine on top |
| The hero's signature beat loops forever | It resolves once and holds (`motion.md`); a loop is decoration, not a demonstration |
| A default menu hard-coded in the nav's `@props` "until site.json has nav_links" | `site.json` ships the links; the nav's default is `[]` and the page binds `:links="$site->nav_links"` |
| Footer, favicon, `site.json` skipped as "out of scope" for the opening | The scaffold writes them; a template with a hero and no footer is not installable |
