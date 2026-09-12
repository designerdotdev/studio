# Imagery — what goes on the page, and how it gets there

## Preference order

1. **Drawn in markup** — product windows, dashboards, inboxes, terminals,
   invoices, charts, stat cards: HTML + token utilities (+ inline SVG for
   chart paths). Razor sharp at any size, restyles with the tokens, zero
   bytes. **This is the first choice for anything a software product shows.**
   Pilot's console and Draft's hero window are the standard: three panes, real
   data (odd numbers, plausible names), 4–6 rows per pane, one overlapping
   element (a terminal, a toast) so it reads as an object in a room.
2. **Inline SVG** — logo mark, icons, decorative rules. `fill="currentColor"`
   so they theme. Icons are 16–20px, 1.5px stroke, from one family
   (Heroicons-style outlines or a single custom set) — never mixed, never
   emoji.
3. **Generated rasters (Higgsfield)** — only for photograph-class needs: a
   painted landscape behind a product window, people, interiors, food,
   product shots, a textured wallpaper for a dark band. Every raster is an
   `image`-type field with its path as the default so the owner can swap
   it.
4. **Avatars** — `https://assets.ui.sh/avatars/N.webp?size=160` (N 1–16 only — higher IDs 404) is
   acceptable for small people shots. Never the reference library's
   avatar/placeholder URLs.
5. **Logo strips** — invented wordmarks set as text in the mono or display
   face at 13–15px with letter-spacing, `text-faint` → `text-ink` on hover.
   Never real brands, never SVGs lifted from the reference.

## Generating with Higgsfield

Use the MCP tools directly (`generate_image` for one, `generate_image_batch`
+ `jobs_wait` + `show_generation_by_ids` for several); the
`higgsfield-generate` skill documents the model catalog. Defaults that work
for site imagery:

- **Model:** `gpt_image_2_5` for scenes, product shots, wallpapers, and
  anything with typography. `marketing_studio_image` for a commercial
  product shot. `soul_2` for editorial portraits.
- **Aspect ratio:** `16:9` for hero bands and card images, `3:2` for
  editorial photos, `1:1` for avatars and product tiles, `21:9` for a
  full-bleed stage. Check `models_explore` if a ratio is refused.
- **Never generate video** unless Tony asks for one by name. Images are fine
  without asking. Pass `use_unlim` only if Tony asked to use unlimited
  generations.

### Prompt recipe

```
<subject>, <composition and crop>, <light>, <palette relation>, <lens/medium>, <negative>
```

Examples that have shipped:

- *Hero stage (Pilot):* "Painted valley landscape at golden hour, wide
  panoramic crop, soft impressionist brushwork, warm off-white sky, muted
  ochre and sage, no people, no text, no buildings."
- *Dark-band wallpaper:* "Abstract silk fabric folds, deep midnight blue,
  soft studio light from the upper left, macro, subtle grain, no text."
- *Editorial person:* "Woman in her thirties at a standing desk in a bright
  studio, candid, looking at a laptop, natural daylight from a large window,
  true colour, 35mm, shallow depth of field, no text, no logos."
- *Product object:* "A matte ceramic coffee cup on a pale oak table, bright
  daylight, crisp focus, true colour, minimal, no text."

**Photographs are bright.** Ask for "bright natural daylight, true colour,
crisp focus". Never desaturate, tint shadows toward the canvas, or grade
into the palette in post — a light, colourful picture on a dark page is
the contrast that makes both work. Coherence across a set comes from one
consistent prompt tail (time of day, lens, treatment), not from a filter.

### Saving what came back

`jobs_wait` returns a result URL per job. Then:

```bash
curl -sL "<result-url>" -o /tmp/raw.png
# ≤ 1600px wide, JPEG q80 (≈ 120–300KB). sips is on every Mac.
sips -Z 1600 -s format jpeg -s formatOptions 80 /tmp/raw.png --out files/public/images/<name>.jpg
```

- Templates: `files/public/images/<name>.jpg`, referenced as
  `/images/<name>.jpg` (the installer rewrites it to `/designer/images/…`).
- Blocks: `<block>/images/<name>.jpg`, referenced as
  `/designer/images/blocks/<block-slug>/<name>.jpg` (install-block copies it
  there).
- Every `<img>` gets `width`/`height` (or an aspect box), `alt` (empty for
  decorative), `loading="lazy"` below the fold, `fetchpriority="high"` in the
  hero.
- Budget: a page carries a handful of rasters, not a gallery of full-bleed
  heroes. Total rasters per template ≲ 1.5MB.

## Logos and favicons

- The brand mark is **hand-drawn inline SVG** (one or two `<path
  fill="currentColor">`), 20–24px in the nav, derived from the name's initial
  or metaphor. Higgsfield may generate concepts ("minimal flat vector logo
  mark, single black shape on white, geometric, no text, no gradients");
  the raster never ships — trace it.
- `public/favicon.svg` carries explicit fills plus an embedded
  `prefers-color-scheme` media query so it reads on light and dark browser
  chrome. It is never a copy of the inline mark (which uses `currentColor`
  and would vanish).

## Replacing reference imagery (create-block)

A block converted from Relume or shadcnblocks arrives with their
placeholders (`placeholder-1.svg`, `avatar-1.webp`, grey boxes with a
crossed diagonal). None of it ships. Replace by the preference order above:
a product block gets a drawn window; a testimonial gets `assets.ui.sh`
avatars or generated portraits; a gallery gets generated photographs with
one consistent prompt tail; a logo strip gets invented wordmarks. The
block's `preview.png` is captured after the replacement, never before.
