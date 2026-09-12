# Converting reference code to a Designer block

## React / JSX → Blade

| Source | Designer |
|---|---|
| `className` | `class` |
| `{cond && <X/>}` | `@if ($show) … @endif` with a `toggle` field |
| `items.map(i => …)` | `@foreach ($items as $item) … @endforeach` with a `repeater` (rows as `(object)` literals in `@props` and in the yml `default:`) |
| props / `const data = [...]` at the top | yml fields + `@props` defaults |
| `useState` / handlers | `data-*` attributes + a guarded `<script>` at the end of the section, or native (`<details>`, `<dialog>`, scroll-snap) |
| `cn("a", cond && "b")` | merge the static classes; conditional classes via `@if`/ternary in `class="… {{ $x ? 'a' : 'b' }}"` |
| `import { X } from "lucide-react"` | inline `<svg>` with the path, `aria-hidden="true"`, `size-4`/`size-5`, `fill="currentColor"` or `stroke="currentColor" stroke-width="1.5"` |
| `next/image`, `<Image>` | `<img src="{{ $image }}" alt="{{ $imageAlt }}" width height loading="lazy">` behind an `image` field |
| `<Link href>` | `<a href="{{ $ctaLink }}">` behind a `url` field |
| Fragments, `key=` | drop |

## shadcn/ui components → markup

| Component | Markup (token utilities) |
|---|---|
| `Button` default | `inline-flex items-center justify-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-medium text-canvas transition-opacity duration-200 hover:opacity-85` (accent version: `bg-accent text-accent-ink`) |
| `Button variant="outline"` | `… rounded-lg border border-line-strong bg-panel px-4 py-2.5 text-sm font-medium text-ink transition-colors duration-200 hover:bg-raised` |
| `Button variant="ghost"/"link"` | `text-lede hover:text-ink transition-colors duration-200` (+ `underline-offset-4 hover:underline` for link) |
| `Button size="lg"` | `px-6 py-3.5 text-[15px]` |
| `Badge` | `inline-flex items-center gap-1.5 rounded-full border border-line bg-panel px-3 py-1 font-mono text-[11px] font-medium tracking-wide text-muted uppercase` (accent: `bg-accent-soft text-accent border-transparent`) |
| `Card` / `CardHeader` / `CardContent` | `rounded-2xl border border-line bg-panel p-6` (+ `hover:border-line-strong transition-colors duration-200` when linked) |
| `Avatar` / `AvatarImage` | `<img class="size-10 rounded-full border-2 border-canvas object-cover" width="40" height="40">` |
| `Separator` | `<hr class="border-line">` or `divide-y divide-line` on the list |
| `Accordion` | `<details class="faq-item group"><summary>…</summary>…</details>` |
| `Tabs` | buttons with `role="tab"` + `aria-selected`, panels with `hidden`, ≤ 30-line guarded script; roving arrow keys |
| `Carousel` | `flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth` with `snap-start shrink-0` children and prev/next buttons that `scrollBy` |
| `Dialog` / `Sheet` / `Drawer` | native `<dialog>` + `showModal()`; or drop the pattern — blocks rarely need modals |
| `NavigationMenu` | the `[data-dropdown]` pattern from designer-craft/motion.md |
| `Tooltip` | `title=""` or nothing |
| `Input` / `Textarea` | `w-full rounded-lg border border-line-strong bg-panel px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-ink focus:outline-none` |
| `Switch` (pricing toggle) | `<button type="button" role="switch" aria-checked="false" data-toggle>` with a sliding knob (`transition-transform duration-200`) |
| `Checkbox` | native `<input type="checkbox" class="size-4 rounded border-line-strong accent-ink">` |
| `Progress` / `Slider` | drawn: a `bg-raised` track and a `bg-ink` fill with `style="width: {{ $pct }}%"` only if `$pct` is a field; otherwise fixed widths |
| `Table` | `<table class="w-full text-sm">` with `border-line` row dividers; ≤ 8 rows visible |
| `Skeleton` | never — draw real content |

## shadcn semantic classes → tokens

| shadcn | Designer |
|---|---|
| `bg-background` | `bg-canvas` |
| `text-foreground` | `text-ink` |
| `text-muted-foreground` | `text-muted` (metadata: `text-faint`) |
| `bg-muted` | `bg-raised` |
| `bg-card`, `bg-popover` | `bg-panel` |
| `border`, `border-border`, `border-input` | `border-line` (strong: `border-line-strong`) |
| `bg-primary text-primary-foreground` | `bg-accent text-accent-ink` (quiet primary: `bg-ink text-canvas`) |
| `bg-secondary` | `bg-raised` |
| `bg-accent` (shadcn's hover tint) | `bg-raised` |
| `text-destructive` | a named token if the block truly needs it; usually drop |
| `ring-ring` / `focus-visible:ring-*` | drop — site.css owns `:focus-visible` |
| `rounded-md` | `rounded-lg` (buttons) / `rounded-2xl` (cards) |
| `shadow-sm` / `shadow` | none, or `shadow-2xl shadow-black/10` on a floating window only |
| `container` | `mx-auto w-full max-w-6xl px-6` |
| `py-32` | `py-24 sm:py-32` |
| `text-4xl font-bold lg:text-6xl` | `text-4xl leading-[1.05] tracking-[-0.03em] text-balance sm:text-5xl lg:text-6xl` (weight from the display face; `font-semibold` at most) |
| `lg:text-xl` body | `text-lg/8` |
| `fill-yellow-400 text-yellow-400` | `fill-accent` |
| `dark:` variants | drop — the template's tokens are already light or dark |

## Relume → Designer

Relume React blocks use `@relume_io/relume-ui` (`Button`, `Dialog`, etc.
— same mapping as shadcn), `px-[5%] py-16 md:py-24 lg:py-28` containers
(→ `px-6 py-24 sm:py-32` inside `max-w-6xl`), `rb-*` gap classes (drop),
`text-md` (→ `text-base`), `size-full` images, and grey placeholder images
with a diagonal cross (replace per imagery.md). Their `Header 44–70` are
page-header blocks, not heroes: map to `heroes` only if they open a page
with a CTA; otherwise a `page-header` content block.

## HTML / Tailwind reference

Keep the structure, then run the class list through the token table.
Anything using Tailwind's `container`, palette colours, `dark:` variants,
or arbitrary hex is rewritten; the lint will point at what remains.

## Behaviour that ships inside a block

```blade
<section id="pricing" data-pricing>
    …
    <button type="button" role="switch" aria-checked="false" data-pricing-toggle class="…">…</button>
    …
    <span data-price data-monthly="{{ $plan->priceMonthly }}" data-annual="{{ $plan->priceAnnual }}">{{ $plan->priceMonthly }}</span>
    …
</section>
<script>
(function () {
    document.querySelectorAll('[data-pricing]:not([data-pricing-ready])').forEach(function (root) {
        root.setAttribute('data-pricing-ready', '');
        var toggle = root.querySelector('[data-pricing-toggle]');
        if (!toggle) return;
        toggle.addEventListener('click', function () {
            var annual = toggle.getAttribute('aria-checked') !== 'true';
            toggle.setAttribute('aria-checked', annual ? 'true' : 'false');
            root.querySelectorAll('[data-price]').forEach(function (el) {
                el.textContent = annual ? el.dataset.annual : el.dataset.monthly;
            });
        });
    });
})();
</script>
```

Values reach the script through `data-*` attributes (Blade echoes inside
`data-*` are fine; inside `x-*` they are not). The guard attribute keeps a
block placed twice from binding twice. Reduced motion is respected by the
CSS the script toggles, not by the script.
