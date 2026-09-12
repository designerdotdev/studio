# Block categories — taxonomy and recipes

The yml `category:` must be one of Studio's library categories (left
column). The other columns say which reference-library categories map onto
it, so a Relume "Header 47" or a shadcnblocks "hero7" lands in the right
group.

| Studio category | Relume | shadcnblocks | Notes |
|---|---|---|---|
| `banners` | Banners, Cookie consent | Banner | thin announcement strips |
| `headers` | Navbars | Navbar | the site nav — usually a template's, rarely a library block |
| `heroes` | Hero Header, Header, Blog/Portfolio/Event headers | Hero | the page opener |
| `logos` | Logo | Logos | wordmark strips and marquees |
| `features` | Feature, Comparison, Timeline, Long-form content | Feature, Bento, Integration, Timeline, Dashboard mocks | how the product works |
| `stats` | Stats | Stats, Chart Card | numbers |
| `gallery` | Gallery, Portfolio, Product list | Gallery, Project(s), Product Card, Case Studies | grids of work or things |
| `testimonials` | Testimonial | Testimonial, Reviews | proof |
| `pricing` | Pricing | Pricing | plans and comparison tables |
| `faq` | FAQ | FAQ | questions |
| `team` | Team, Career | Team, Careers | people |
| `blog` | Blog, Blog post header | Blog, Blog Post | listings and post headers |
| `contact` | Contact, Contact modal, Multi-step form | Contact | forms and locations |
| `newsletter` | — | (Cta with input) | email capture |
| `cta` | CTA | Cta | the closing band |
| `footers` | Footers | Footer | site footer |
| `content` | Long-form content, Link pages | Application shell, About | prose, split content, about |

Application-UI blocks (shells, sidebars, tables, dashboards) are not
library blocks; their *drawn* versions appear inside heroes and features
as product visuals.

## Recipes

Each recipe: **shapes** that avoid the generic tell · **fields** that must
exist · **interaction** · **tell** to avoid.

### heroes
- **Shapes:** split (text left, drawn window right, or the reverse);
  centred statement with a full-width window below overlapping a stage
  band; type-led with a small proof row and no visual; asymmetric 7/5 with
  the visual bleeding off the right edge; product-first (window on top,
  copy below).
- **Fields:** eyebrow/badge (+ `showBadge`), heading, body, ctaText/Link,
  secondaryText/Link, the visual's inner copy (window title, 3–6 rows,
  figures), `image` for any raster, proof line, `imageSide` or `align`
  select.
- **Interaction:** arrival cascade `reveal-1…6`; one resolving beat inside
  the window; arrow nudge on the secondary button.
- **Tell:** centred headline + two pills + a grey screenshot box; a
  category-naming headline; gradient text.

### headers
- **Shapes:** brand + links + quiet link + outlined Sign in + solid CTA
  (the SaaS default); floating pill nav on a canvas; oversized menu
  capsule; nav with a mega-panel (child rows with descriptions + a "latest"
  card).
- **Fields:** brand, links (`source: site.nav_links`, `nestable`),
  signInText/Link, ctaText/Link, optional secondary link; `fixed: true`.
- **Interaction:** glass on scroll; hover-intent dropdowns (50/180ms);
  Escape/outside-click; mobile sheet with auth buttons at the bottom.
- **Tell:** dropdowns that snap; no Sign in; a nav taller than 72px.

### logos
- **Shapes:** a single row of 5–7 wordmarks with a one-line caption; a
  two-row marquee; a bordered grid of 8 cells; wordmarks inline with a
  stat ("1,400 studios…").
- **Fields:** caption, `items` repeater (name, optional url), `marquee`
  toggle.
- **Interaction:** marquee (pause on hover, hidden under reduced motion;
  the second copy of the row is cloned by the block's script at runtime,
  never a second `@foreach`) or `text-faint → text-ink` on hover.
- **Tell:** real brand SVGs; greyscale PNG logos; "Trusted by".

### features
- **Shapes:** bento (one 2×2 hero cell + four 1×1); alternating split
  rows (3–4, each with its own drawn visual); numbered steps in a
  horizontal rail; a two-column list with a sticky visual; a comparison
  table (us vs. the old way); an integrations grid of wordmark tiles.
- **Fields:** eyebrow, heading, body, `items` repeater (title, body, and
  a visual key or image), per-row link text/href, `layout` select where
  the block supports two.
- **Interaction:** cards lift ≤ 2px with a hairline darkening; the visual
  inside the active row resolves once; step numbers count in.
- **Tell:** three equal icon-in-a-circle cards; the same grid twice on a
  page; icons doing the work of a visual.

### stats
- **Shapes:** a dark `shade` band with 3–4 giant figures and one-line
  captions; figures inline with a paragraph; a stat ledger (label / value /
  delta rows).
- **Fields:** eyebrow, heading, `items` repeater (value, label, optional
  delta).
- **Interaction:** count-up only on the signature stat; otherwise reveal.
- **Tell:** round numbers (10x, 1M+, 100%); four equal boxes with icons.

### testimonials
- **Shapes:** one pull quote large, with a portrait and a company
  wordmark; a wall (masonry of 5–6 cards, varied heights); a two-column
  editorial quote with a stat beside it; a carousel with scroll-snap.
- **Fields:** eyebrow, heading, `items` repeater (quote, name, role,
  company, image), `source: collections.testimonials`.
- **Interaction:** cards lift; carousel snaps with visible arrows and
  keyboard support.
- **Tell:** three equal cards with five yellow stars each; "John Doe, CEO".

### pricing
- **Shapes:** three plans, middle highlighted with a badge and an accent
  border; two plans + an enterprise band; a single plan with a usage
  slider; plans + a comparison table (≤ 8 rows, a "compare all" disclosure
  for more).
- **Fields:** eyebrow, heading, body, `showToggle` + monthly/annual labels
  and a savings note, `items` repeater (name, tagline, priceMonthly,
  priceAnnual, period, ctaText/Link, featured toggle, badge, feature lines),
  `source: collections.plans`.
- **Interaction:** the `role="switch"` toggle rewrites every price with a
  150ms fade; the highlighted card sits 8px higher on desktop.
- **Tell:** every card the same; checkmarks in accent circles; "Most
  popular" on a card that isn't visually different.

### faq
- **Shapes:** two-column (heading + intro left, `<details>` list right);
  single centred column of 5–7; categorised with a sticky index.
- **Fields:** eyebrow, heading, body, `items` repeater (question, answer),
  `source: collections.faq`.
- **Interaction:** native `<details>` with the smooth `::details-content`
  transition; a plus icon that rotates 45°.
- **Tell:** JS accordions that break without JS; chevrons in circles.

### cta
- **Shapes:** a dark rounded band (the template default); full-bleed
  stage with a photograph and a veil; a split band (statement left, an
  email input right); a quiet single line with one link.
- **Fields:** heading, body, ctaText/Link, secondaryText/Link, `image` if
  a stage, `tone` select (shade | canvas | accent).
- **Interaction:** reveal; the stage image scales 1.02 over 8s under
  `.js` only.
- **Tell:** a gradient band; two equal buttons; "Get started today!".

### footers
- **Shapes:** four columns + brand + legal row; a giant wordmark footer;
  a compact single row for small sites.
- **Fields:** brand, tagline, columns (`source: site.footer_links`,
  `nestable`), social (`source: site.social_links`), legal.
- **Interaction:** link colour on hover; that is all.
- **Tell:** a link wall; a newsletter form crammed into the footer.

### gallery / blog / team / contact / newsletter / banners / content
- **gallery:** masonry or 3/2 editorial grid, captions on hover, one
  large cell; `items` with image, title, meta, url; images bright.
- **blog:** a featured post + a list of 4 with date and read time; post
  header block with title, meta, hero image; `source: collections.posts`.
- **team:** portraits in a 4-up grid with role and one line; or a
  single-row "the people" strip; avatars real or generated, never
  placeholder.
- **contact:** a split (copy + a short form of ≤ 4 fields); `action` url
  field; native inputs styled with tokens; a success note toggle.
- **newsletter:** one input + one button inline; a privacy line; `action`
  url.
- **banners:** one line + a link + a dismiss (stored in `localStorage`,
  guarded script).
- **content:** prose with a real measure (`max-w-[65ch]`), an aside, pull
  quotes; `richtext` for the body via a collection.
