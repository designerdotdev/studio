#!/usr/bin/env bash
#
# lab.sh — a throwaway Laravel app for verifying Designer templates and blocks.
#
#   lab.sh create    <lab-dir>                    new Laravel app with this package path-symlinked
#   lab.sh add       <lab-dir> <slug> <template>  register a local template folder in the lab catalog
#   lab.sh import    <lab-dir> <slug>             copy the template's WORKING TREE into the clone cache and install it (--force)
#   lab.sh serve     <lab-dir> [port]             php artisan serve in the background (default 8765)
#   lab.sh stop      <lab-dir>
#   lab.sh sweep     <lab-dir> <base-url>         request every page, the sitemap, and the editor; non-200 fails
#   lab.sh roundtrip <lab-dir> <slug>             install → flush → zero changed files; re-read → flush → zero again
#
# Never point this at the host app (~/Sites/designer): `import` and `roundtrip` replace resources/designer.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PKG="$(cd "$HERE/../../../.." && pwd)"          # the studio package this skill lives in
CMD="${1:-}"; LAB="${2:-}"

die() { echo "lab.sh: $*" >&2; exit 1; }
need_lab() { [ -n "$LAB" ] && [ -f "$LAB/artisan" ] || die "not a Laravel app: '$LAB'"; }
guard_host() {
    case "$(cd "$LAB" 2>/dev/null && pwd -P)" in
        "$HOME/Sites/designer"|"$HOME/Sites/designer/"*) die "refusing to run against the host app" ;;
    esac
}

case "$CMD" in
create)
    [ -n "$LAB" ] || die "usage: lab.sh create <lab-dir>"
    [ -e "$LAB" ] && die "$LAB already exists"
    composer create-project laravel/laravel "$LAB" --no-interaction --quiet
    cd "$LAB"
    composer config repositories.studio "{\"type\":\"path\",\"url\":\"$PKG\",\"options\":{\"symlink\":true}}"
    composer require designer/studio:@dev --no-interaction --quiet
    echo '{}' > lab-templates.json
    cat > config/studio.php <<PHP
<?php
// Lab override: the package config plus every local template registered by lab.sh add.
\$config = require '$PKG/config/studio.php';
\$local = json_decode((string) @file_get_contents(__DIR__ . '/../lab-templates.json'), true) ?: [];
\$config['templates']['catalog'] = [];
foreach (\$local as \$slug => \$path) {
    \$config['templates']['catalog'][\$slug] = ['repo' => \$path, 'name' => ucfirst(\$slug), 'category' => 'landing', 'description' => 'Local: ' . \$path];
}
return \$config;
PHP
    php artisan config:clear >/dev/null
    echo "lab ready at $LAB (package: $PKG)"
    ;;
add)
    need_lab; SLUG="${3:-}"; T="${4:-}"
    [ -n "$SLUG" ] && [ -f "$T/template.json" ] || die "usage: lab.sh add <lab-dir> <slug> <template-dir with template.json>"
    T="$(cd "$T" && pwd)"
    python3 - "$LAB/lab-templates.json" "$SLUG" "$T" <<'PY'
import json, sys
f, slug, path = sys.argv[1:]
d = json.load(open(f)); d[slug] = path; json.dump(d, open(f, 'w'), indent=2)
PY
    echo "registered $SLUG → $T"
    ;;
import)
    need_lab; guard_host; SLUG="${3:-}"
    [ -n "$SLUG" ] || die "usage: lab.sh import <lab-dir> <slug>"
    T="$(python3 -c "import json,sys; print(json.load(open(sys.argv[1])).get(sys.argv[2],''))" "$LAB/lab-templates.json" "$SLUG")"
    [ -n "$T" ] || die "$SLUG is not registered — run lab.sh add first"
    CACHE="$LAB/storage/studio/templates/$SLUG"
    mkdir -p "$CACHE"
    rsync -a --delete --exclude .git --exclude .DS_Store --exclude node_modules "$T/" "$CACHE/"
    cd "$LAB" && php artisan studio:templates:import "$SLUG" --force --no-interaction
    echo "installed $SLUG from the working tree (wait ~3s before requesting pages: OPcache revalidates every 2s)"
    ;;
serve)
    need_lab; PORT="${3:-8765}"
    cd "$LAB"
    if [ -f .serve.pid ] && kill -0 "$(cat .serve.pid)" 2>/dev/null; then echo "already serving (pid $(cat .serve.pid))"; exit 0; fi
    nohup php artisan serve --host=127.0.0.1 --port="$PORT" > .serve.log 2>&1 &
    echo $! > .serve.pid
    sleep 1.5
    echo "serving http://127.0.0.1:$PORT (pid $(cat .serve.pid), log $LAB/.serve.log)"
    ;;
stop)
    need_lab; cd "$LAB"
    [ -f .serve.pid ] && kill "$(cat .serve.pid)" 2>/dev/null && rm -f .serve.pid && echo stopped || echo "not running"
    ;;
sweep)
    need_lab; BASE="${3:-}"; [ -n "$BASE" ] || die "usage: lab.sh sweep <lab-dir> <base-url>"
    python3 - "$LAB" "${BASE%/}" <<'PY'
import json, os, re, sys, urllib.request
lab, base = sys.argv[1:]
pages_dir = os.path.join(lab, 'resources/designer/views/pages')
coll_dir = os.path.join(lab, 'resources/designer/data/collections')
urls = []
for dp, dn, fn in os.walk(pages_dir):
    for f in sorted(fn):
        if not f.endswith('.blade.php'): continue
        rel = os.path.relpath(os.path.join(dp, f), pages_dir)[:-len('.blade.php')]
        if rel == '404': continue
        m = re.search(r'\[(\w+)\.(\w+)\]$', rel)
        if m:
            coll, field = m.groups()
            try:
                rows = json.load(open(os.path.join(coll_dir, coll + '.json')))
                first = rows[0][field]
            except Exception:
                print(f'SKIP  {rel}: no first entry in collections/{coll}.json'); continue
            rel = rel[:m.start()] + str(first)
        urls.append('/' if rel == 'index' else '/' + rel.rstrip('/'))
urls += ['/sitemap.xml', '/studio', '/studio/preview']
bad = 0
for u in urls:
    try:
        req = urllib.request.Request(base + u, headers={'User-Agent': 'lab-sweep'})
        with urllib.request.urlopen(req, timeout=20) as r:
            code, size = r.status, len(r.read())
    except urllib.error.HTTPError as e:
        code, size = e.code, 0
    except Exception as e:
        code, size = 'ERR', str(e)
    ok = code == 200
    bad += 0 if ok else 1
    print(f"{'ok  ' if ok else 'FAIL'}  {code}  {u}  ({size} bytes)")
print(f'{len(urls)} urls, {bad} failing')
sys.exit(1 if bad else 0)
PY
    ;;
roundtrip)
    need_lab; guard_host; SLUG="${3:-}"; [ -n "$SLUG" ] || die "usage: lab.sh roundtrip <lab-dir> <slug>"
    "$0" import "$LAB" "$SLUG" >/dev/null
    cd "$LAB" && php "$HERE/roundtrip.php" "$LAB" "$SLUG"
    ;;
*)
    sed -n 2,13p "$0"; exit 2 ;;
esac
