# The token contract

Every Designer template ships its own `resources/css/site.css` with a Tailwind
v4 `@theme` block. Every block written for the library uses **only** the
canonical utilities below, so a block dropped into any template picks up
that template's palette and type without edits. A template may add tokens;
it may not rename or omit the canonical ones.

## Canonical tokens (a template MUST define all of these)

| Token | Utility | Role |
|---|---|---|
| `--color-canvas` | `bg-canvas`, `text-canvas` | the page. Never pure white or pure black |
| `--color-panel` | `bg-panel` | raised cards, windows, popovers — one step above canvas |
| `--color-raised` | `bg-raised` | the toasted step: hover fills, chips, inset wells |
| `--color-line` | `border-line`, `divide-line` | hairlines. Low-alpha ink, not a grey |
| `--color-line-strong` | `border-line-strong` | outlined buttons, window frames |
| `--color-ink` | `text-ink`, `bg-ink` | headings, primary buttons |
| `--color-lede` | `text-lede` | body copy in sections |
| `--color-muted` | `text-muted` | supporting copy, labels |
| `--color-faint` | `text-faint`, `fill-faint` | metadata, icons at rest, placeholders |
| `--color-accent` | `bg-accent`, `text-accent`, `border-accent` | the one spice. ≤ 3 elements per viewport |
| `--color-accent-ink` | `text-accent-ink` | text that sits ON the accent |
| `--color-accent-soft` | `bg-accent-soft` | tinted wells behind accent content |
| `--color-shade` | `bg-shade` | the dark counterpart of a light page (dropdowns, terminal, one dark band); on a dark template it is the *lighter* counterpart |
| `--color-shade-ink` / `--color-shade-muted` / `--color-shade-line` | `text-shade-ink` … | text and hairlines on `shade` |
| `--font-display` | `font-display` | headings, the hero, big numbers |
| `--font-sans` | `font-sans` | everything else |
| `--font-mono` | `font-mono` | eyebrows, labels, code, terminal, nav in a technical brand |
| `--ease-out-quart` | `ease-[var(--ease-out-quart)]` or in site.css | fast-in, long settle: hovers, dropdowns |
| `--ease-spring` | in site.css | reveals, arrivals |

Rules that follow from the table:

- **Real values live in `@theme` and nowhere else.** No hex, rgb, oklch, or
  `color-mix` in a section. If a section needs a colour the table lacks,
  add a token with a role name (`--color-success`), never a literal.
- **No Tailwind palette utilities in sections** — `bg-zinc-900`,
  `text-white`, `text-black`, `border-gray-200`, `bg-[#0a0a0a]`,
  `text-yellow-400` all fail the lint. `text-white` on a dark button is
  `text-canvas`; a star rating's amber is `fill-accent` or a named token.
- **Text on the accent is `text-accent-ink`**, never `text-white`, because
  an accent can be lime, cream, or ink itself (Pilot's accent is its ink).
- **Opacity variants are allowed** (`bg-panel/95`, `border-accent/40`,
  `shadow-black/15`) — they modulate a token rather than introduce a colour.
- Focus rings use `:focus-visible { outline: 2px solid var(--color-accent) }`
  (or ink on a monochrome brand) in site.css, not per-element `ring-*`.

## The motion contract (a template MUST ship these too)

Blocks rely on three things every template's `site.css` + `main.js`
provide, so a block never has to ask whether they exist:

- the reveal system: `.js [data-reveal]` hidden until `.is-visible`, with
  `.reveal-1 … .reveal-6` stagger delays, and an observer in `main.js`
  that flips the class (without JS nothing is hidden);
- `[data-dropdown]` / `[data-dropdown-panel]` / `[data-dropdown-trigger]`
  and `[data-mobile-panel]` / `[data-mobile-toggle]` in the nav;
- `details.faq-item` with the smooth `::details-content` transition;
- a `prefers-reduced-motion` block that resolves all of the above.

`create-designer-template/scripts/scaffold.sh` writes all of it; motion.md has the
CSS. A block may use `data-reveal` and `reveal-N` freely.

## Starter `site.css` (light)

```css
/*
    <Name> — the design system and the motion system.
    Inlined right after Tailwind by @vite in the layout. One palette, one
    type pairing, tuned together. Real values live here and only here.
*/
@theme {
    --font-display: 'Instrument Serif', ui-serif, Georgia, serif;
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;

    /* Surfaces: a warm off-white page, true white for raised cards */
    --color-canvas: #f7f6f3;
    --color-panel: #ffffff;
    --color-raised: #eeece7;
    --color-line: rgba(20, 19, 16, 0.10);
    --color-line-strong: rgba(20, 19, 16, 0.20);

    /* Text in four tiers */
    --color-ink: #141310;
    --color-lede: #3b3a36;
    --color-muted: #6b6a64;
    --color-faint: #9a9891;

    /* The accent — one colour, budgeted */
    --color-accent: #1d4ed8;
    --color-accent-ink: #f7f6f3;
    --color-accent-soft: rgba(29, 78, 216, 0.08);

    /* The dark counterpart: soft black, not #000 */
    --color-shade: #1b1a17;
    --color-shade-ink: #f3f2ee;
    --color-shade-muted: #a3a19a;
    --color-shade-line: rgba(255, 255, 255, 0.12);

    --ease-out-quart: cubic-bezier(0.25, 1, 0.5, 1);
    --ease-spring: cubic-bezier(0.16, 1, 0.3, 1);
}

html { background-color: var(--color-canvas); color-scheme: light; }
body { overflow-x: clip; }
::selection { background-color: var(--color-ink); color: var(--color-canvas); }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; border-radius: 3px; }
h1, h2, h3, h4 { font-family: var(--font-display); }
```

For a **dark template** swap the roles, keep the names: canvas `#0b0b0d`,
panel `#121216`, raised `#1a1a20`, line `rgba(255,255,255,0.10)`, ink
`#f4f4f5`, lede `#c9c9ce`, muted `#9a9aa3`, faint `#66666f`, shade becomes
the *lighter* surface (`#f4f4f5` with dark shade-ink). Dark is a design
decision made once in the brief, not a runtime toggle — never ship a
`.dark` block unless the brief asks for both and both were built and
checked.

## Choosing the values (Phase 2.5 of create-designer-template)

1. **Read the catalog first** — `config/studio.php` `templates.catalog`
   descriptions and the thumbnails in `storage/studio/templates/*/`. A new
   template must not share canvas mood + type family + accent family with an
   existing one. The catalog leans dark and monochrome; a warm light canvas
   with one saturated accent is currently the open lane.
2. **Canvas is tinted**, never neutral grey: warm (`#f7f6f3`), cool
   (`#f5f7fa`), paper (`#faf7f1`), or a deep colour on dark (`#0a0f1a`).
3. **Accent budget**: primary CTA, one focal moment in the hero, link hover.
   That is all. If the accent is on every card, it is a base colour, not an
   accent.
4. **Contrast**: `lede` and `muted` on canvas ≥ 4.5:1; `faint` ≥ 3:1 (it is
   only for ≥ 14px metadata and icons); `accent-ink` on `accent` ≥ 4.5:1.
   Check with a real calculation, not by eye.
5. **Type pairing** — pick a display face with an opinion and a text face
   that disappears. Load two families max through one Google Fonts
   `<link>` with `display=swap`, only the weights you use. Pairings that
   have shipped well: Geist + Geist Mono (technical), Instrument Serif +
   Inter (editorial SaaS), Fraunces + Söhne-alikes (warm), Space Grotesk +
   IBM Plex Sans (product). Avoid Inter-for-everything unless the brief is
   deliberately quiet.

## What the lint enforces from this file

- No `#hex`, `rgb(`, `oklch(`, `hsl(` outside `css/*.css`
- No `bg|text|border|fill|stroke|ring|from|to|via-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d` and no `text-white|text-black|bg-white|bg-black` in section markup
- No arbitrary colour utilities (`bg-[#…]`, `text-[rgb…]`)
- Every canonical token defined once in `css/site.css`
