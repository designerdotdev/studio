#!/bin/bash
#
# Refresh the pictures the template picker shows (resources/thumbnails).
#
# The catalog's repositories are not all public, so the picker cannot rely on
# downloading a template to show what it looks like: each catalogued
# template's thumbnail.png is shipped with the package instead, as a 1000px
# JPEG. Run this after a template's thumbnail changes or the catalog does.
#
#     bin/thumbnails.sh [templates-folder]
#
# The folder holds one template repository per slug (default:
# $DESIGNER_TEMPLATES, else ~/Sites/designer/templates). Needs `sips` (macOS).

set -euo pipefail

cd "$(dirname "$0")/.."

TEMPLATES="${1:-${DESIGNER_TEMPLATES:-$HOME/Sites/designer/templates}}"
OUT="resources/thumbnails"

slugs=$(php -r '
    function env($key, $default = null) { return $default; }
    function storage_path($path = "") { return $path; }
    $config = require "config/studio.php";
    echo implode("\n", array_keys($config["templates"]["catalog"]));
')

mkdir -p "$OUT"
missing=0

for slug in $slugs; do
    source="$TEMPLATES/$slug/thumbnail.png"

    if [ ! -f "$source" ]; then
        echo "  missing  $slug ($source)"
        missing=1
        continue
    fi

    sips -s format jpeg -s formatOptions 82 -Z 1000 "$source" --out "$OUT/$slug.jpg" > /dev/null
    echo "  wrote    $OUT/$slug.jpg ($(($(stat -f %z "$OUT/$slug.jpg") / 1024)) KB)"
done

# A picture whose template has left the catalog
for file in "$OUT"/*.jpg; do
    slug=$(basename "$file" .jpg)

    if ! echo "$slugs" | grep -qx "$slug"; then
        rm "$file"
        echo "  removed  $file"
    fi
done

exit $missing
