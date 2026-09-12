# Motion — how a Designer page should feel under the cursor

The standard is shadcn/ui's discipline (short, eased, purposeful, exits
faster than entrances) with Motion's vocabulary (springs for arrivals,
`inView` for reveals, `stagger` for cascades). Three tiers, always:

1. **No JavaScript** — nothing hidden, every link works, every `<details>`
   opens.
2. **JavaScript, no library** — `main.js` drives reveals with an
   IntersectionObserver and dropdowns with class toggles; the transitions
   are CSS in `site.css`.
3. **A library present** (Motion or GSAP from a CDN, `defer`) — it takes over
   scroll work and adds the signature motion. It never becomes a
   requirement.

`prefers-reduced-motion: reduce` short-circuits all three: everything is
visible, still, and instant. This is tested in every workflow skill.

## Timing table (memorise these; the lint checks the guard, not the values)

| Moment | Duration | Easing | Notes |
|---|---|---|---|
| Hover colour / opacity | 150–200ms | `ease` or `--ease-out-quart` | `transition-colors duration-200` |
| Hover transform (lift, arrow nudge) | 200–250ms | `--ease-out-quart` | ≤ 2px lift, ≤ 0.5rem arrow travel, scale ≤ 1.02 |
| Dropdown / popover open | 220–250ms | `--ease-out-quart` | opacity + translate 6px + scale 0.98 → 1; origin at the trigger |
| Dropdown close | 150–180ms | `ease-in` or same ease | exits are faster than entrances |
| Mobile sheet | 280–320ms | `--ease-out-quart` | translate −8px + opacity; lock body scroll |
| Accordion (`<details>`) | 300–350ms | `--ease-out-quart` | `interpolate-size: allow-keywords` + `::details-content` |
| Scroll reveal | 700–900ms | `--ease-spring` | opacity 0→1, translate 18px→0, optional blur 6px→0 |
| Arrival cascade (hero) | 70ms steps, ≤ 6 steps | `--ease-spring` | `.reveal-1 … .reveal-6` delays |
| Marquee / ticker | 30–60s loop | `linear` | pause on hover; hide under reduced motion; clone the track with JS, not a second `@foreach` |
| Number count-up | 900–1400ms | `--ease-out-quart` | only on a signature stat; final value in the markup |
| Page navigation | 220ms | `ease` | `@view-transition { navigation: auto }` |

Hover intent for menus: open after **50ms**, close after **180ms**, so
diagonal travel into the panel never slams it shut. Click toggles for
touch; Escape and outside clicks dismiss; focus inside holds it open.

## The CSS vocabulary (paste into site.css, adjust the tokens)

```css
/* Reveal system — main.js adds .js to <html> before first paint */
.js [data-reveal] {
    opacity: 0;
    translate: 0 18px;
    filter: blur(6px);
    transition: opacity .9s var(--ease-spring), translate .9s var(--ease-spring), filter .9s var(--ease-spring);
    transition-delay: var(--reveal-delay, 0ms);
}
.js [data-reveal].is-visible { opacity: 1; translate: 0 0; filter: blur(0); }
.reveal-1 { --reveal-delay: 70ms; }  .reveal-2 { --reveal-delay: 140ms; }
.reveal-3 { --reveal-delay: 210ms; } .reveal-4 { --reveal-delay: 280ms; }
.reveal-5 { --reveal-delay: 350ms; } .reveal-6 { --reveal-delay: 420ms; }

/* Dropdown panel — in the DOM at full size, moved and faded */
[data-dropdown-panel] {
    opacity: 0; translate: 0 6px; scale: .98; visibility: hidden;
    transform-origin: top center;
    transition: opacity .18s ease-in, translate .18s ease-in, scale .18s ease-in, visibility 0s linear .18s;
}
[data-dropdown].is-open [data-dropdown-panel] {
    opacity: 1; translate: 0 0; scale: 1; visibility: visible;
    transition: opacity .24s var(--ease-out-quart), translate .24s var(--ease-out-quart), scale .24s var(--ease-out-quart), visibility 0s;
}
[data-dropdown] .dropdown-caret { transition: rotate .25s var(--ease-out-quart); }
[data-dropdown].is-open .dropdown-caret { rotate: 180deg; }

/* Header: transparent at rest, glass once scrolled (main.js sets data-scrolled) */
#header { border-bottom: 1px solid transparent; transition: background-color .4s ease, border-color .4s ease, backdrop-filter .4s ease; }
#header[data-scrolled] { background-color: color-mix(in oklab, var(--color-canvas) 72%, transparent); border-bottom-color: var(--color-line); backdrop-filter: blur(12px); }

/* Accordion */
details.faq-item { interpolate-size: allow-keywords; }
details.faq-item::details-content { block-size: 0; overflow: hidden; transition: block-size .35s var(--ease-out-quart), content-visibility .35s; transition-behavior: allow-discrete; }
details.faq-item[open]::details-content { block-size: auto; }
details.faq-item summary::-webkit-details-marker { display: none; }

/* Cross-page fades */
@view-transition { navigation: auto; }
::view-transition-old(root), ::view-transition-new(root) { animation-duration: 220ms; }

/* Reduced motion wins outright */
@media (prefers-reduced-motion: reduce) {
    .js [data-reveal] { opacity: 1; translate: 0 0; filter: none; transition: none; }
    [data-dropdown-panel], [data-mobile-panel] { transition-duration: 0s; }
    [data-marquee] > * { animation: none; }
    ::view-transition-old(root), ::view-transition-new(root) { animation: none; }
}
```

Positioning note: the open/close motion animates `translate`, so a panel
is centred with `left-1/2` plus a negative margin of half its width
(`-ml-[18rem] w-[36rem]`), never with `-translate-x-1/2`.

## The JS vocabulary (`public/js/main.js`, `defer`, no build step)

Pilot's `main.js` (in `storage/studio/templates/pilot/files/public/js/`) is
the reference implementation. Its shape:

- `document.documentElement.classList.add('js')` first, so CSS only hides
  reveal targets when JS will reveal them.
- `reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches`
  checked once; when true, `showAll()` and no timelines.
- `headerState()` toggles `data-scrolled` at `scrollY > 8` (passive
  listener).
- `dropdowns()` — hover intent timers (50/180ms), click toggle, Escape,
  outside click, `focusout` closes, one open at a time, `aria-expanded`.
- `mobileMenu()` — toggles `.menu-open` on `<html>`, `aria-expanded`,
  closes on Escape and on navigation.
- `reveals(root)` — `IntersectionObserver` with `rootMargin: '0px 0px -8%
  0px'`, `threshold: .05`, unobserve after reveal; anything already above
  the fold reveals immediately.
- A `setUp(root)` / `tearDown()` pair so instant-navigation page swaps
  rebind cleanly.

### Using Motion (motion.dev) from a CDN, when the design earns it

```html
<script type="module">
    import { animate, inView, stagger, spring } from "https://cdn.jsdelivr.net/npm/motion@12/+esm";
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // Hero arrival: one cascade, spring-eased
        animate('[data-arrive]', { opacity: [0, 1], y: [16, 0] }, { delay: stagger(0.07), type: spring, stiffness: 180, damping: 22 });
        // Reveals: fire once, resolve to visible
        inView('[data-reveal]', (el) => { animate(el, { opacity: [0, 1], y: [18, 0], filter: ['blur(6px)', 'blur(0px)'] }, { duration: .8, ease: [0.16, 1, 0.3, 1] }); }, { margin: '0px 0px -8% 0px' });
    }
</script>
```

Pin the major version (`motion@12`). Keep the CSS reveal system in place
underneath so the page still resolves if the module never loads. GSAP
(`gsap@3` + `ScrollTrigger`) is the alternative when you need pinning or
scrubbed timelines; Pilot shows the fallback pattern.

## What earns motion (and what doesn't)

Earns it: the hero arrival; a product mock whose rows "resolve" once
(trace lines, a cursor moving to a button, a chart drawing); a dropdown
opening from its trigger; a card's arrow nudging on hover; a marquee of
wordmarks; a `<details>` opening smoothly; a pricing toggle sliding.

Doesn't: parallax on every section; infinite floating blobs; typewriter
headlines; gradient shimmer on buttons; auto-playing count-ups on every
stat; hover effects that move layout (use `translate`, never `margin`).

## Alpine on the canvas

Studio's editor iframe loads Alpine (`config/studio.php` → `iframe`), but
the **live site does not** unless the layout includes it. A section that
depends on Alpine ships the CDN `<script defer>` in the layout head, and
must never put a Blade echo inside an Alpine attribute
(`x-data="{ open: {{ $x }} }"` breaks on quoting — the lint fails it).
Prefer `data-*` hooks driven by `main.js`; reach for Alpine only for state
a few lines of vanilla can't express cleanly (tabs with keyboard roving,
a pricing toggle that rewrites six prices).
