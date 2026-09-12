#!/usr/bin/env bash
#
# new-block.sh <category> <slug> "<Title>" [--dir <library-root>]
#
# Scaffolds ~/Sites/designer-blocks/blocks/<category>/<slug>/ with a starter
# .blade.php, .yml and block.json (the library repo is created on first use).
set -euo pipefail
CAT="${1:-}"; SLUG="${2:-}"; TITLE="${3:-}"
[ -n "$CAT" ] && [ -n "$SLUG" ] && [ -n "$TITLE" ] || { sed -n 3,6p "$0"; exit 2; }
[[ "$SLUG" =~ ^[a-z][a-z0-9-]*$ ]] || { echo "slug must be lowercase letters, digits, hyphens"; exit 2; }
CATS="banners headers heroes logos features stats gallery testimonials pricing faq team blog contact newsletter cta footers content"
[[ " $CATS " == *" $CAT "* ]] || { echo "category must be one of: $CATS"; exit 2; }
LIB="$HOME/Sites/designer-blocks"
for ((i=1;i<=$#;i++)); do [ "${!i}" = "--dir" ] && j=$((i+1)) && LIB="${!j}"; done
if [ ! -d "$LIB/.git" ]; then
    mkdir -p "$LIB/blocks"
    cat > "$LIB/README.md" <<'MD'
# Designer blocks

Sections for Designer Studio, one folder each under `blocks/<category>/<slug>/`:
the `.blade.php` + `.yml` pair, `block.json` (provenance and metadata),
`preview.png`, and any `collections/` or `images/` the block ships.
Every block uses the shared token contract (`designer-craft/references/tokens.md`)
so it drops into any template. Install one into a site with
`create-block/scripts/install-block.sh <block-dir> <app-root>`.
MD
    printf '.DS_Store\nnode_modules/\n' > "$LIB/.gitignore"
    git -C "$LIB" init -q -b main && git -C "$LIB" add -A && git -C "$LIB" commit -q -m "Start the block library"
    echo "created library at $LIB"
fi
B="$LIB/blocks/$CAT/$SLUG"
[ -e "$B" ] && { echo "$B already exists"; exit 1; }
mkdir -p "$B"

cat > "$B/$SLUG.blade.php" <<BLADE
@props([
    'eyebrow' => 'Eyebrow',
    'heading' => 'A heading written for the invented product',
    'body' => 'One or two sentences in the product\'s voice. Rewrite before shipping.',
])
<!--
    $TITLE. Describe the composition in one line, and where repeated data
    lives if the block binds a collection.
-->
<section id="$SLUG" class="px-6 py-24 sm:py-32">
    <div class="mx-auto w-full max-w-6xl">
        <p class="font-mono text-[11px] font-medium tracking-widest text-muted uppercase" data-reveal>{{ \$eyebrow }}</p>
        <h2 class="mt-4 max-w-[20ch] text-4xl leading-[1.05] tracking-[-0.03em] text-balance sm:text-5xl" data-reveal>{{ \$heading }}</h2>
        <p class="reveal-1 mt-6 max-w-[54ch] text-lg/8 text-pretty text-muted" data-reveal>{{ \$body }}</p>
    </div>
</section>
BLADE

cat > "$B/$SLUG.yml" <<YML
title: $TITLE
description: One line the Add Section picker shows
category: $CAT
fields:
    eyebrow:
        type: text
        label: Eyebrow
        default: "Eyebrow"
    heading:
        type: text
        label: Heading
        default: "A heading written for the invented product"
    body:
        type: textarea
        label: Supporting copy
        rows: 3
        default: "One or two sentences in the product's voice. Rewrite before shipping."
YML

cat > "$B/block.json" <<JSON
{
    "slug": "$SLUG",
    "title": "$TITLE",
    "category": "$CAT",
    "tags": [],
    "product": "",
    "source": { "kind": "brief", "ref": "", "kept": "" },
    "interaction": "",
    "collections": [],
    "images": [],
    "tokens": []
}
JSON
echo "scaffolded $B"
