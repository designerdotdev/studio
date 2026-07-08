# Studio claims `/` automatically

**Date:** 2026-07-08 · **Status:** approved

## Problem

Studio's home route only registers when the host app doesn't define `/`
(`StudioServiceProvider::registerPageRoutes`). A fresh Laravel app always
defines `/` (the stock welcome route), so the published homepage never
serves at the root. Worse, `PageController::show` 301s `/{home_slug}` → `/`
to keep the home URL canonical, which makes the homepage completely
unreachable — publish reports success ("Everything is live" is truthful,
the draft/live trees do match) while the site shows the Laravel welcome
page forever.

## Decisions (user-approved)

- **Consent model: automatic.** Studio removes the stock welcome route
  from `routes/web.php` on its own — never asks. Safety comes from strict
  detection: only the stock Laravel boilerplate is ever touched.
- **Fallback for customized `/` routes: notice + reachable home.** When
  the app's root route is anything but stock boilerplate, surface an
  editor notice and stop 301-ing `/{home_slug}` so the homepage serves at
  its slug.

## Design

### 1. `Support/WelcomeRoutePruner` (new, singleton)

- `appDefinesRootRoute(): bool` — moved from the service provider; checks
  the route collection for a GET `/`.
- `stockWelcomeDetected(): bool` — reads `base_path('routes/web.php')`
  and matches the stock boilerplate with a whitespace-tolerant but
  structurally strict regex:

  ```php
  Route::get('/', function () {
      return view('welcome');
  });
  ```

  Named routes, controllers, or custom closures never match.
- `claimHome(): bool` — the idempotent orchestrator: page routing
  enabled → a **live** home page exists (raw live tree, workspace-immune)
  → `routes/web.php` writable → stock route detected → rewrite the file
  with the block removed (tidying leftover blank lines). If routes are
  cached, run `route:clear` so the change takes effect immediately.
  Returns true only when it actually pruned.

### 2. Trigger points (all funnel through `claimHome()`)

- **Seed completion** (`SampleDataSeeder::seedFromTemplate`) — a freshly
  seeded site starts published (`syncAfterSeed`), so this covers the
  common case where the user never clicks Publish.
- **`StudioController::publishSite`** — response gains
  `home_claimed: true`; the publish popover toasts
  "Removed Laravel's default welcome page — your homepage now serves at /".
- **Editor load** (`StudioController::index`) — self-heals sites created
  before this feature, which can't re-publish because everything is
  already live. Cheap early-exits keep this near-free.

### 3. Fallback when `/` is customized

- `editorNotices()` gains a warn notice when: routing enabled, a live
  home page exists, the app defines `/`, it wasn't just claimed, and the
  stock pattern doesn't match (customized or unwritable): *"Your app
  defines its own / route, so your homepage is served at /home. Remove
  that route to let Studio serve it at /."*
- New `Support/SiteUrls::pageUrl($slug)`: home maps to `url('/')` only
  when `Route::has('studio.page.home')`, else `url('/{home_slug}')`.
  Used by `PageController::show` (redirect guard), previous-slug 301s,
  the sitemap, and the editor's live-URL display.
- `PageController::show` only 301s `/{home_slug}` → `/` when Studio owns
  the root route — otherwise the homepage serves at its slug.

### 4. Safety properties

- Only `routes/web.php` is ever edited, only on an exact structural match
  of stock boilerplate, and the removal is surfaced (publish toast).
- No behavior change when `page_routing.enabled` is false.

## Verification

No test suite in this package. Drive the real flow against a stock host
app: onboard → welcome route gone from `routes/web.php`, `curl /` returns
the published homepage. Then with a customized `/` route: file untouched,
notice appears, `/home` serves 200 instead of redirecting.
