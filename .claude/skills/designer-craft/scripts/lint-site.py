#!/usr/bin/env python3
"""
lint-site.py — the mechanical half of the Designer craft contract.

    python3 lint-site.py <dir> [--json]

<dir> is any of:
  - a template's files/resources folder (or the template root holding files/)
  - an installed site's resources/designer folder
  - a block folder holding <slug>.blade.php + <slug>.yml (+ collections/)

Zero findings is the only pass; WARN_-prefixed codes are printed but do not
fail the run. Findings are `path:line: CODE message`.
"""
import json
import os
import re
import sys

try:
    import yaml
except ImportError:  # pragma: no cover
    print("lint-site.py needs PyYAML: pip3 install pyyaml", file=sys.stderr)
    sys.exit(2)

PALETTE = r"(?:slate|gray|grey|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}"
RAW_UTIL = re.compile(
    r"(?<![\w-])(?:(?:hover|focus|active|group-hover|dark|sm|md|lg|xl|2xl|peer-hover):)*"
    r"(?:bg|text|border|fill|stroke|ring|from|to|via|divide|outline|shadow|decoration|placeholder|accent|caret)-"
    r"(?:" + PALETTE + r"|white|black)(?:/\d+)?(?![\w-])"
)
ARBITRARY_COLOR = re.compile(r"(?:bg|text|border|fill|stroke|ring|from|to|via|shadow)-\[(?:#|rgb|hsl|oklch)[^\]]*\]")
HEX_LITERAL = re.compile(r"(?<![\w&])#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b")
FUNC_COLOR = re.compile(r"\b(?:rgba?|hsla?|oklch|oklab|color-mix)\(")
ALPINE_ECHO = re.compile(r"\sx-[\w:.-]+=\"[^\"]*\{\{")
IMG_TAG = re.compile(r"<img\b(?:\{\{.*?\}\}|[^>])*>", re.S)
TRANSITION = re.compile(r"\b(?:transition|animation)\b")
PROPS_BLOCK_OPEN = re.compile(r"@props\(\s*\[", re.S)
PROP_ITEM = re.compile(r"'([A-Za-z_][A-Za-z0-9_]*)'\s*(?:=>\s*('(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\"|\[\]|[^,\]]+))?")
ECHO = re.compile(r"\{\{\s*\$([A-Za-z_][A-Za-z0-9_]*)")
CANONICAL_TOKENS = [
    "--color-canvas", "--color-panel", "--color-raised", "--color-line", "--color-line-strong",
    "--color-ink", "--color-lede", "--color-muted", "--color-faint",
    "--color-accent", "--color-accent-ink", "--color-accent-soft",
    "--color-shade", "--color-shade-ink", "--color-shade-muted", "--color-shade-line",
    "--font-display", "--font-sans", "--font-mono", "--ease-out-quart", "--ease-spring",
]
SCALAR_TYPES = {"text", "textarea", "url", "select", "toggle", "colorpicker", "color", "image", "number", "range", "richtext", "boolean", "checkbox"}
VALID_TYPES = SCALAR_TYPES | {"repeater"}
SUB_TYPES = {"text", "textarea", "url", "image", "select", "toggle", "richtext", "number"}
findings = []


def add(path, line, code, msg):
    findings.append({"path": path, "line": line, "code": code, "message": msg})


def rel(path, root):
    return os.path.relpath(path, root)


def resolve_root(arg):
    arg = os.path.abspath(arg)
    if os.path.isdir(os.path.join(arg, "files", "resources")):
        return os.path.join(arg, "files", "resources"), "template"
    if os.path.isdir(os.path.join(arg, "views")) or os.path.isdir(os.path.join(arg, "css")):
        return arg, "site"
    return arg, "block"


def unquote(v):
    v = v.strip()
    if len(v) >= 2 and v[0] == v[-1] and v[0] in "'\"":
        body = v[1:-1]
        return body.replace("\\'", "'").replace('\\"', '"').replace("\\\\", "\\")
    return v


def split_top_level(body):
    """Split the inside of @props([...]) at commas that sit at bracket depth 0."""
    items, depth, quote, cur, i = [], 0, None, [], 0
    while i < len(body):
        ch = body[i]
        if quote:
            cur.append(ch)
            if ch == "\\" and i + 1 < len(body):
                cur.append(body[i + 1]); i += 2; continue
            if ch == quote:
                quote = None
        elif ch in "'\"":
            quote = ch; cur.append(ch)
        elif ch in "([{":
            depth += 1; cur.append(ch)
        elif ch in ")]}":
            depth -= 1; cur.append(ch)
        elif ch == "," and depth == 0:
            items.append("".join(cur)); cur = []
        else:
            cur.append(ch)
        i += 1
    if "".join(cur).strip():
        items.append("".join(cur))
    return [x.strip() for x in items if x.strip()]


def parse_props(src):
    m = PROPS_BLOCK_OPEN.search(src)
    if not m:
        return None
    # find the matching close of @props( [ ... ] )
    start = m.end()
    depth, i, quote = 1, start, None
    while i < len(src) and depth:
        ch = src[i]
        if quote:
            if ch == "\\": i += 1
            elif ch == quote: quote = None
        elif ch in "'\"": quote = ch
        elif ch == "[": depth += 1
        elif ch == "]": depth -= 1
        i += 1
    body = src[start:i - 1]
    props = {}
    for item in split_top_level(body):
        km = re.match(r"^(['\"])([A-Za-z_][A-Za-z0-9_]*)\1\s*(?:=>\s*(.*))?$", item, re.S)
        if not km:
            continue
        name, default = km.group(2), km.group(3)
        if default is None:
            props[name] = ("bare", None)
        elif default.strip() == "[]":
            props[name] = ("array", [])
        elif default.strip().startswith("["):
            props[name] = ("rows", default.strip())
        elif default.strip()[0] in "'\"":
            props[name] = ("scalar", unquote(default))
        else:
            props[name] = ("expr", default.strip())
    return props


def load_yml(path, root):
    try:
        with open(path, encoding="utf-8") as fh:
            data = yaml.safe_load(fh) or {}
    except Exception as e:  # noqa: BLE001
        add(rel(path, root), 1, "YML_PARSE", f"cannot parse: {e}")
        return None
    if not isinstance(data, dict):
        add(rel(path, root), 1, "YML_SHAPE", "top level must be a mapping")
        return None
    return data


def lint_yml(path, data, root):
    p = rel(path, root)
    fields = data.get("fields")
    if not isinstance(fields, dict):
        add(p, 1, "YML_FIELDS", "no `fields:` mapping")
        return {}
    if not data.get("title"):
        add(p, 1, "YML_TITLE", "missing `title:` (shown in the Add Section picker)")
    for key, cfg in fields.items():
        if not re.match(r"^[A-Za-z_][A-Za-z0-9_]*$", str(key)):
            add(p, 1, "FIELD_KEY", f"`{key}` is not a valid prop name (camelCase, no hyphens)")
        if not isinstance(cfg, dict):
            add(p, 1, "FIELD_SHAPE", f"`{key}` must be a mapping")
            continue
        t = cfg.get("type", "text")
        if t not in VALID_TYPES:
            add(p, 1, "FIELD_TYPE", f"`{key}` has unknown type `{t}`")
        if t in ("toggle", "boolean", "checkbox") and "default" in cfg and not isinstance(cfg["default"], str):
            add(p, 1, "TOGGLE_DEFAULT", f"`{key}` toggle default must be the string \"1\" or \"0\" (got {cfg['default']!r})")
        if t == "select" and not isinstance(cfg.get("options"), dict):
            add(p, 1, "SELECT_OPTIONS", f"`{key}` select needs `options: {{value: Label}}`")
        if t == "textarea" and "rows" in cfg and not (2 <= int(cfg["rows"]) <= 20):
            add(p, 1, "TEXTAREA_ROWS", f"`{key}` rows must be 2–20")
        if t == "repeater":
            subs = cfg.get("sub_fields")
            if not isinstance(subs, dict) or not subs:
                add(p, 1, "REPEATER_SUBS", f"`{key}` repeater needs `sub_fields`")
            else:
                for sk, sc in subs.items():
                    st = (sc or {}).get("type", "text") if isinstance(sc, dict) else "text"
                    if st == "repeater":
                        add(p, 1, "REPEATER_NESTED", f"`{key}.{sk}`: repeaters cannot nest (use `nestable: true` for one level of children)")
                    elif st not in SUB_TYPES:
                        add(p, 1, "REPEATER_SUBTYPE", f"`{key}.{sk}` sub-field type `{st}` is not supported by the inspector")
                    if sk == "children":
                        add(p, 1, "REPEATER_RESERVED", f"`{key}.children` is a reserved sub-field key")
                if cfg.get("item_label") and cfg["item_label"] not in subs:
                    add(p, 1, "REPEATER_LABEL", f"`{key}` item_label `{cfg['item_label']}` is not a sub-field")
        elif "default" in cfg and cfg["default"] is not None and not isinstance(cfg["default"], (str, int, float, bool)):
            add(p, 1, "FIELD_DEFAULT", f"`{key}` default must be a scalar")
    return fields


def lint_blade(path, src, fields, root, in_sections):
    p = rel(path, root)
    lines = src.split("\n")
    props = parse_props(src)
    if fields is not None:
        if props is None:
            add(p, 1, "PROPS_MISSING", "section has a .yml but no @props([...]) block")
        else:
            for key, cfg in fields.items():
                cfg = cfg if isinstance(cfg, dict) else {}
                t = cfg.get("type", "text")
                if key not in props:
                    add(p, 1, "PROP_UNDECLARED", f"yml field `{key}` has no matching @props entry")
                    continue
                kind, val = props[key]
                if t == "repeater":
                    if kind == "rows" and not cfg.get("default"):
                        add(p, 1, "ROWS_UNMIRRORED", f"`{key}` ships rows in @props but the yml has no matching `default:` list")
                    if kind == "scalar":
                        add(p, 1, "PROP_REPEATER", f"`{key}` is a repeater; declare it bare (`'{key}'`) or as `[]`, not a string")
                    continue
                y = cfg.get("default", "")
                y = "" if y is None else str(y)
                if kind == "scalar" and val != y:
                    add(p, 1, "DEFAULT_DRIFT", f"`{key}` default differs: @props {val!r} vs yml {y!r}")
                elif kind == "bare":
                    add(p, 1, "DEFAULT_BARE", f"`{key}` is declared bare in @props; give it the yml default {y!r}")
                elif kind == "array":
                    add(p, 1, "DEFAULT_ARRAY", f"`{key}` is a {t} field but @props gives it []")
            for name, (kind, val) in props.items():
                if "/layouts/" in p:
                    break  # a layout's title/description come from the page tag, not the inspector
                if name not in fields and name not in ("class", "attributes", "slot"):
                    add(p, 1, "PROP_UNEDITABLE", f"@props `{name}` has no yml field — a site owner cannot change it")
            # echoes of undeclared variables
            declared = set(props) | {"site", "slot", "attributes", "loop", "entries"}
            for i, line in enumerate(lines, 1):
                for m in ECHO.finditer(line):
                    v = m.group(1)
                    if v not in declared and not re.search(r"\bas\s+(?:\$\w+\s*=>\s*)?\$" + re.escape(v) + r"\b", src) and not re.search(r"\$" + re.escape(v) + r"\s*=[^=>]", src):
                        add(p, i, "ECHO_UNDECLARED", f"`${v}` is echoed but not a prop, collection, loop variable, or $site")
    if in_sections or fields is not None:
        for i, line in enumerate(lines, 1):
            if line.lstrip().startswith(("<!--", "{{--", "*", "/*")):
                continue
            for m in RAW_UTIL.finditer(line):
                if re.match(r"(?:[\w-]+:)*shadow-(?:black|white)/\d+$", m.group(0)):
                    continue  # a black/white alpha shadow is the depth recipe, not a colour
                add(p, i, "RAW_PALETTE", f"`{m.group(0)}` — use a token utility (bg-canvas, text-ink, text-accent-ink…)")
            for m in ARBITRARY_COLOR.finditer(line):
                add(p, i, "RAW_ARBITRARY", f"`{m.group(0)}` — colours live in @theme")
            if "style=" in line or "stop-color" in line or "fill=\"#" in line or "stroke=\"#" in line:
                for m in HEX_LITERAL.finditer(line):
                    add(p, i, "HEX_IN_MARKUP", f"`{m.group(0)}` — colour literal in section markup; use currentColor or a token var()")
            if ALPINE_ECHO.search(line):
                add(p, i, "ALPINE_ECHO", "Blade echo inside an Alpine attribute — quoting breaks; move the value to a data-* attribute")
            if re.search(r"(?:^|\s)[A-Za-z0-9]@(?:if|else|endif|foreach|endforeach|php|endphp)\b", line):
                add(p, i, "DIRECTIVE_GLUED", "a Blade directive must have whitespace or a tag boundary before it")
            if re.search(r"->children\s*\?\?\s*false", line):
                add(p, i, "CHILDREN_TRUTHY", "`->children ?? false` is truthy on the canvas (an empty DataBag is an object); test `count($x->children ?? [])`")
            if re.search(r"@if\s*\(\s*\$\w+\s*\)\s*<x-", line):
                add(p, i, "TOGGLE_WRAPS_COMPONENT", "a toggle's @if wraps an <x-…> tag — the canvas cannot switch it off; wrap a plain element instead")
            if fields is not None:
                for m in re.finditer(r"\{!!\s*\$([A-Za-z_]\w*)\s*!!\}", line):
                    ft = (fields.get(m.group(1)) or {}).get("type") if isinstance(fields.get(m.group(1)), dict) else None
                    if ft in ("text", "textarea"):
                        add(p, i, "RAW_ECHO_TEXT", f"`{{!! ${m.group(1)} !!}}` is a {ft} field echoed raw — it becomes non-editable on the canvas; echo it as {{{{ }}}}")
            if re.search(r"shadcnblocks\.com|relume\.io|relume\.ai|placeholder-\d|lorem ipsum|Lorem ipsum", line, re.I):
                add(p, i, "REFERENCE_LEAK", "reference-library URL or placeholder text is still in the markup")
        if fields is not None and os.path.basename(path).split(".")[0] not in ("nav", "header", "footer"):
            # a nav renders its links twice on purpose (desktop bar + mobile sheet)
            loops = re.findall(r"@foreach\s*\(\s*\$([A-Za-z_]\w*)\s+as\b", src)
            for v in sorted(set(loops)):
                if loops.count(v) > 1 and (fields.get(v) or {}).get("type") == "repeater":
                    add(p, 1, "WARN_REPEATER_DOUBLE_LOOP", f"repeater `{v}` is rendered by {loops.count(v)} @foreach loops — the canvas merges them and the rows lose item controls; duplicate with JS/CSS instead")
        for m in IMG_TAG.finditer(src):
            tag = m.group(0)
            line = src.count("\n", 0, m.start()) + 1
            if "alt=" not in tag:
                add(p, line, "IMG_ALT", "<img> without alt (use alt=\"\" for decorative)")
            if not (("width=" in tag and "height=" in tag) or "aspect-" in tag or "absolute" in tag or "inset-0" in tag or "size-" in tag or re.search(r"\bh-\d", tag)):
                add(p, line, "IMG_DIMENSIONS", "<img> without width/height, an aspect-* class, or a fixed size — layout will shift on load")
        if re.search(r"src=\"https?://(?!assets\.ui\.sh|fonts\.)", src):
            add(p, 1, "IMG_REMOTE", "remote image that is not assets.ui.sh — generate it and ship it under images/")


def lint_css(path, src, root, is_site):
    p = rel(path, root)
    if is_site:
        theme = re.search(r"@theme\s*\{(.*?)\n\}", src, re.S)
        if not theme:
            add(p, 1, "THEME_MISSING", "site.css has no @theme block")
        else:
            for tok in CANONICAL_TOKENS:
                if not re.search(re.escape(tok) + r"\s*:", theme.group(1)):
                    add(p, 1, "TOKEN_MISSING", f"canonical token {tok} is not defined in @theme")
    if TRANSITION.search(src) and "prefers-reduced-motion" not in src:
        add(p, 1, "REDUCED_MOTION", "css declares transitions/animations but has no prefers-reduced-motion block")


def lint_collections(dirpath, root):
    for name in sorted(os.listdir(dirpath)):
        full = os.path.join(dirpath, name)
        if name.endswith(".json"):
            base = name[:-5]
            if not re.match(r"^[A-Za-z_]\w*$", base):
                add(rel(full, root), 1, "COLLECTION_NAME", f"`{base}` must be a valid variable name (no hyphens)")
            try:
                with open(full, encoding="utf-8") as fh:
                    rows = json.load(fh)
            except Exception as e:  # noqa: BLE001
                add(rel(full, root), 1, "COLLECTION_JSON", f"invalid JSON: {e}")
                continue
            if not isinstance(rows, list):
                add(rel(full, root), 1, "COLLECTION_SHAPE", "must be a JSON array of objects")
            elif not rows:
                add(rel(full, root), 1, "COLLECTION_EMPTY", "collection ships empty — the picker preview and dynamic pages break")
            else:
                for i, r in enumerate(rows):
                    if not isinstance(r, dict):
                        add(rel(full, root), 1, "COLLECTION_ROW", f"row {i} is not an object")
                        break
                    for v in r.values():
                        if isinstance(v, str) and re.search(r"lorem ipsum", v, re.I):
                            add(rel(full, root), 1, "COLLECTION_LOREM", f"row {i} contains lorem")
                            break


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    as_json = "--json" in sys.argv
    if not args:
        print(__doc__)
        sys.exit(2)
    root, mode = resolve_root(args[0])
    if not os.path.isdir(root):
        print(f"not a directory: {root}", file=sys.stderr)
        sys.exit(2)

    ymls = {}
    for dp, dn, fn in os.walk(root):
        dn[:] = [d for d in dn if d not in (".git", "node_modules", "vendor")]
        for f in fn:
            full = os.path.join(dp, f)
            if f.endswith(".yml") and "/data/collections" not in dp and not dp.endswith("collections"):
                ymls[full[:-4]] = full
    for dp, dn, fn in os.walk(root):
        dn[:] = [d for d in dn if d not in (".git", "node_modules", "vendor")]
        for f in fn:
            full = os.path.join(dp, f)
            if f.endswith(".blade.php"):
                base = full[:-len(".blade.php")]
                yml = ymls.pop(base, None)
                fields = None
                if yml:
                    data = load_yml(yml, root)
                    fields = lint_yml(yml, data, root) if data is not None else {}
                in_sections = "/components/" in full or mode == "block"
                if "/components/layouts/" in full:
                    in_sections = False
                with open(full, encoding="utf-8") as fh:
                    lint_blade(full, fh.read(), fields, root, in_sections)
            elif f.endswith(".css"):
                with open(full, encoding="utf-8") as fh:
                    lint_css(full, fh.read(), root, f == "site.css")
        if os.path.basename(dp) == "collections":
            lint_collections(dp, root)
    for base, yml in ymls.items():
        add(rel(yml, root), 1, "YML_ORPHAN", "a .yml with no .blade.php beside it")

    seen = set()
    unique = []
    for f in findings:
        k = (f["path"], f["line"], f["code"], f["message"])
        if k not in seen:
            seen.add(k)
            unique.append(f)
    findings[:] = unique
    findings.sort(key=lambda f: (f["path"], f["line"], f["code"]))
    if as_json:
        print(json.dumps(findings, indent=2))
    else:
        for f in findings:
            print(f"{f['path']}:{f['line']}: {f['code']} {f['message']}")
        hard = [f for f in findings if not f["code"].startswith("WARN_")]
        print(f"{len(hard)} finding(s), {len(findings) - len(hard)} warning(s) in {root}")
    sys.exit(1 if any(not f["code"].startswith("WARN_") for f in findings) else 0)


if __name__ == "__main__":
    main()
