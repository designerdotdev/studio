---
name: designer-craft
description: Use when writing or reviewing any Blade section, block, template, site.css, or main.js for Designer Studio — the shared design contract (tokens, motion, imagery, copy) that create-designer-template and create-block both build on. Also use when a Designer section looks generic, copied, static, or off-brand and needs grading.
---

# Designer craft — the shared contract

Every template and every block for Designer is judged against one bar:
**it looks and feels like a designer at Linear, Vercel, or Stripe made it,
and a site owner can change every word of it in the editor.** This skill is
the contract that makes templates and blocks interchangeable: the same
token names, the same motion vocabulary, the same imagery pipeline, the
same copy standard. `create-designer-template` and `create-block` require it; read
it before writing markup, not after.

**REQUIRED BACKGROUND:** `docs/authoring-sections.md` in this repo is the
mechanical contract (props ↔ yml, field types, `source:`, how a page
records a section). This skill is the taste contract on top of it.

## The four references (read the one you're about to need)

| You are about to… | Read |
|---|---|
| write `site.css`, pick a palette, choose fonts, use any colour class | [references/tokens.md](references/tokens.md) |
| add a dropdown, reveal, accordion, hover, marquee, tab, or any `transition` | [references/motion.md](references/motion.md) |
| put an image, logo, avatar, or product mock on a page — including generating any of them with the **Higgsfield MCP** | [references/imagery.md](references/imagery.md) |
| write a headline, feature, plan, testimonial, or any default value | [references/copy.md](references/copy.md) |

## The premium bar (checkable, not a vibe)

Hold every section against these. Each line is something a screenshot or a
grep can confirm.

- **Type** — one display family + one text family; display `clamp()`ed
  ~2.5→4.5rem with leading 1.02–1.12 and tracking −0.02 to −0.04em; body
  1rem/1.6–1.7; measure ≤ 65ch; ≤ 3 weights on the page. Every heading
  carries `font-display`.
- **Space** — desktop sections `py-24`–`py-32`, mobile `py-16`–`py-20`, with
  deliberate variation (one tight band, one airy one). Whitespace is the
  strongest premium signal; a wall of evenly padded sections reads cheap.
- **Colour** — the canvas is never pure `#fff` or `#000`; text sits in
  three tiers (`ink` / `muted` / `faint`); the accent appears on ≤ 3
  elements per viewport. No gradient unless the brief names one.
- **Depth** — hairlines (`border-line`) plus one soft layered shadow, *or*
  flat. One recipe page-wide. No glow, no `shadow-2xl` on cards, no
  glass panels except the nav.
- **Composition variety** — across a page: full-bleed, editorial two-column,
  offset grid, centred, edge-anchored — mixed. Never the same three-card
  grid twice; never six centred sections in a row.
- **States** — hover, active, and a visible `focus-visible` ring on every
  interactive element; transitions 150–250ms on the ease tokens.
- **Motion** — one arrival cascade above the fold, fade-and-rise reveals
  below, purposeful micro-interactions on hover; `prefers-reduced-motion`
  resolves everything to visible and still. See motion.md.
- **Signature** — a template has one custom, memorable element (a drawn
  product window, a live-feeling ticker, a composition nobody else has).
  A block has one *interaction detail* that makes it feel considered.
- **Mobile** — designed at 390px first: tap targets ≥ 44px, no horizontal
  scroll, the hero still demonstrates the product, nothing squished.
- **Copy** — specific, short, in one voice; no lorem, no "Build faster",
  no "All-in-one platform". See copy.md.
- **Editable** — every visible string, link, image, toggleable block and
  repeated list is a yml field or a collection row, and every yml default
  is mirrored verbatim in `@props`.

## Canvas editability (inline editing)

Studio derives what a marketer can edit directly on the canvas from the
Blade you write — nothing is annotated (`docs/inline-editing.md`). So:

- **Echo a field and it becomes editable in place**; `src="{{ $image }}"`
  opens the media library; `href="{{ $link }}"` gets a link popover.
- **Text fields are echoed as text** — `{{ $heading }}`, never
  `{!! $heading !!}`. A raw echo that renders markup is made
  non-editable so a commit cannot wipe it. Reserve `{!! !!}` for
  `icon`-style fields that hold SVG.
- **A toggle's `@if` wraps a plain element**, not an `<x-…>` tag; the
  canvas can only switch off what it can mark.
- **One `@foreach` per repeater.** A marquee that needs a second copy of
  its row duplicates it with JS at runtime (or a CSS animation over one
  copy), never a second loop — two loops merge and the rows lose their
  item controls.
- **Empty children test:** `@if (count($link->children ?? []))`, not
  `?? false` — on the canvas an empty list is a `DataBag` object and
  objects are truthy.
- A `select` used only in comparisons or class names has no canvas
  presence; that is correct, not a gap.
- `php artisan studio:inline:verify --section=<name>` in the lab reports
  every declared field that maps to a rendered position and proves the
  sentinels are inert. Fields it lists as unmapped must be selects in
  comparisons or toggles on components — anything else is a missing echo.

## The lint

```bash
python3 .claude/skills/designer-craft/scripts/lint-site.py <path-to-files/resources | resources/designer | a block folder>
```

Zero findings is the only pass, and a finding is fixed at its cause — never
by rewording the markup so the regex stops matching. If the lint is wrong,
fix the lint (it is a script in this repo) and say so. It checks props ↔ yml parity, raw palette
utilities and hex literals in sections, echoes inside Alpine attributes,
images without dimensions or alt, hyphenated collection names, empty
collections, unquoted toggle defaults, and transitions with no
reduced-motion guard. It cannot see taste — that is the screenshot pass in
each workflow skill.

## Red flags — the generic-AI tells

If you see one of these in your own output, redesign before showing it:

- Three equal cards as the answer to every section
- Everything centred; no asymmetry, no full-bleed, no editorial layout
- Indigo/violet gradient buttons, gradient text, gradient-on-everything
- Emoji as icons; lucide-style icons in circles on every feature card
- `rounded-full` pills and `shadow-2xl` everywhere
- `bg-zinc-*`, `text-white`, `bg-[#…]` inside a section (see tokens.md)
- A hero headline that names the category ("The modern platform for X")
- Placeholder avatars from the source library, `placeholder-1.svg`, lorem
- No hover feedback; a page that is dead under the cursor
- Reveals that leave content at `opacity: 0` under reduced motion
- Alpine `x-data` holding a Blade echo (`x-data="{ open: {{ … }} }"`)
- Copy or images that still identify the reference block they came from
