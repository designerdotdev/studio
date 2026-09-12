#!/usr/bin/env bash
#
# install-block.sh <block-dir> <app-root> [--preview]
#
# Copies a library block into an installed Designer site:
#   <slug>.blade.php + .yml  → resources/designer/views/components/sections/
#   collections/*            → resources/designer/data/collections/   (existing files are not overwritten)
#   images/*                 → public/designer/images/blocks/<slug>/
# then syncs the section library. --preview also writes
# resources/designer/views/pages/preview-<slug>.blade.php so the block can be
# looked at on /preview-<slug> inside the site's own layout.
set -euo pipefail
B="${1:-}"; APP="${2:-}"; PREVIEW=0; [ "${3:-}" = "--preview" ] && PREVIEW=1
[ -d "$B" ] && [ -f "$APP/artisan" ] || { sed -n 3,11p "$0"; exit 2; }
[ -d "$APP/resources/designer/views/components" ] || { echo "no site installed in $APP (resources/designer missing)"; exit 1; }
SLUG="$(basename "$B")"
[ -f "$B/$SLUG.blade.php" ] && [ -f "$B/$SLUG.yml" ] || { echo "expected $B/$SLUG.blade.php and $SLUG.yml"; exit 1; }

SEC="$APP/resources/designer/views/components/sections"
mkdir -p "$SEC"
cp "$B/$SLUG.blade.php" "$B/$SLUG.yml" "$SEC/"
echo "sections/$SLUG.blade.php + .yml"

if [ -d "$B/collections" ]; then
    mkdir -p "$APP/resources/designer/data/collections"
    for f in "$B"/collections/*; do
        [ -f "$f" ] || continue
        dest="$APP/resources/designer/data/collections/$(basename "$f")"
        if [ -e "$dest" ]; then echo "kept existing $(basename "$f")"; else cp "$f" "$dest"; echo "collections/$(basename "$f")"; fi
    done
fi
if [ -d "$B/images" ]; then
    mkdir -p "$APP/public/designer/images/blocks/$SLUG"
    cp "$B"/images/* "$APP/public/designer/images/blocks/$SLUG/" 2>/dev/null && echo "images → /designer/images/blocks/$SLUG/"
fi
if [ "$PREVIEW" = 1 ]; then
    LAYOUT="$(ls "$APP/resources/designer/views/components/layouts/" | grep -m1 -E '^main\.blade\.php$' || ls "$APP/resources/designer/views/components/layouts/" | grep -m1 '\.blade\.php$')"
    L="${LAYOUT%.blade.php}"
    BINDS=""
    if [ -d "$B/collections" ]; then
        for f in "$B"/collections/*.json; do [ -f "$f" ] && n="$(basename "$f" .json)" && BINDS="$BINDS :items=\"\$$n\""; done
    fi
    cat > "$APP/resources/designer/views/pages/preview-$SLUG.blade.php" <<BLADE
<x-layouts.$L title="Preview: $SLUG">
    <div class="pt-16"></div>
    <x-sections.$SLUG$BINDS />
</x-layouts.$L>
BLADE
    echo "preview page: /preview-$SLUG (delete resources/designer/views/pages/preview-$SLUG.blade.php when done)"
fi
(cd "$APP" && php artisan studio:sync >/dev/null 2>&1 && echo "library synced") || echo "library will sync on the next /studio load"
