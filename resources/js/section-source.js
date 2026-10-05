/**
 * A section's Blade source as a tree — the model behind the inspector's
 * Elements tab.
 *
 * Pure text in, plain objects out: no DOM, no Blade compiler. Every node
 * keeps the exact offsets it was read from, so an edit made in the tree is a
 * splice of the source and never a re-print of it — what the developer did
 * not touch stays byte for byte as they wrote it.
 *
 * The parser is tolerant on purpose. It runs on every keystroke, on source
 * that is half typed more often than not, and a tree that is roughly right
 * is worth more than none: a stray closing tag is ignored, an unclosed one
 * is closed where its parent ends.
 *
 * Node kinds:
 *   element   <div …>…</div>   (`component` for <x-…>, `raw` for script/style)
 *   text      a run of text and echoes between tags, trimmed
 *   directive @if (…), @foreach, @endif … — a leaf among its siblings
 *   comment   <!-- … --> and {{-- … --}}
 */

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr']);
const RAW = new Set(['script', 'style']);

// Directives that open a block, and the ones that sit between its halves.
// Only used to indent the rows under them; the tree itself keeps directives
// flat, because a directive may straddle tags in ways an element cannot.
const OPENERS = new Set([
    'if', 'unless', 'isset', 'empty', 'auth', 'guest', 'env', 'production', 'hasSection', 'sectionMissing',
    'foreach', 'forelse', 'for', 'while', 'switch', 'once', 'push', 'pushOnce', 'pushIf', 'prepend',
    'section', 'component', 'slot', 'can', 'cannot', 'canany', 'error', 'fragment', 'session', 'persist',
]);
const MIDDLES = new Set(['else', 'elseif', 'elsecan', 'elsecannot', 'elsecanany', 'case', 'default', 'empty']);

const NAME_START = /[A-Za-z]/;
const NAME_CHAR = /[\w\-:.]/;

/** Offset just past the `}}` / `!!}` / `--}}` closing the echo at `at`, or the end of the text. */
function echoEnd(text, at) {
    let close = '}}';
    if (text.startsWith('{{--', at)) close = '--}}';
    else if (text.startsWith('{!!', at)) close = '!!}';

    const end = text.indexOf(close, at + 2);

    return end === -1 ? text.length : end + close.length;
}

/** Offset just past the `)` matching the `(` at `at`, strings respected. */
function parenEnd(text, at) {
    let depth = 0;

    for (let i = at; i < text.length; i++) {
        const ch = text[i];

        if (ch === '"' || ch === "'") {
            for (i++; i < text.length && text[i] !== ch; i++) {
                if (text[i] === '\\') i++;
            }
        } else if (ch === '(') {
            depth++;
        } else if (ch === ')' && --depth === 0) {
            return i + 1;
        }
    }

    return text.length;
}

/** A Blade directive starting at `at` (`@name` with optional arguments), or null. */
function directiveAt(text, at) {
    if (text[at] !== '@' || text[at + 1] === '@' || !NAME_START.test(text[at + 1] || '')) return null;
    // An address, or the second @ of an escaped @@directive
    if (at > 0 && /[\w@]/.test(text[at - 1])) return null;

    let end = at + 1;
    while (end < text.length && /\w/.test(text[end])) end++;

    const name = text.slice(at + 1, end);

    let args = end;
    while (text[args] === ' ') args++;
    if (text[args] === '(') end = parenEnd(text, args);

    return { name, end };
}

/** Read one opening tag's attributes; `i` is just past the tag name. */
function readAttributes(text, i) {
    const attrs = [];
    let selfClosing = false;

    while (i < text.length) {
        const ch = text[i];

        if (/\s/.test(ch)) { i++; continue; }
        if (ch === '>') { i++; break; }
        if (ch === '/' && text[i + 1] === '>') { selfClosing = true; i += 2; break; }
        if (ch === '/') { i++; continue; }

        // An echo among the attributes: {{ $attributes->merge([...]) }}
        if (ch === '{' && (text[i + 1] === '{' || text.startsWith('{!!', i))) {
            const end = echoEnd(text, i);
            attrs.push({ kind: 'code', start: i, end, raw: text.slice(i, end) });
            i = end;
            continue;
        }

        const start = i;
        while (i < text.length && !/[\s=>]/.test(text[i]) && !(text[i] === '/' && text[i + 1] === '>')) {
            if (text[i] === '(' && text[start] === '@') { i = parenEnd(text, i); break; }
            i++;
        }

        const name = text.slice(start, i);

        // A directive among the attributes: @class([...]), @if($x) … @endif
        if (name[0] === '@' && text[i] !== '=') {
            let end = i;
            let args = i;
            while (text[args] === ' ') args++;
            if (text[args] === '(' && !name.includes('(')) end = parenEnd(text, args);
            attrs.push({ kind: 'code', start, end, raw: text.slice(start, end) });
            i = end;
            continue;
        }

        if (i === start) { i++; continue; }

        if (text[i] !== '=') {
            attrs.push({ kind: 'attr', name, start, end: i, value: null });
            continue;
        }

        i++; // =

        const quote = text[i] === '"' || text[i] === "'" ? text[i] : '';
        let valueStart = i;
        let valueEnd;

        if (quote) {
            valueStart = ++i;
            // The value ends at its quote — but an echo inside it may hold
            // that same quote: class="{{ $on ? "a" : 'b' }}"
            while (i < text.length && text[i] !== quote) {
                if (text[i] === '{' && (text[i + 1] === '{' || text.startsWith('{!!', i))) i = echoEnd(text, i);
                else i++;
            }
            valueEnd = i;
            if (i < text.length) i++;
        } else {
            while (i < text.length && !/[\s>]/.test(text[i])) i++;
            valueEnd = i;
        }

        attrs.push({ kind: 'attr', name, start, end: i, value: text.slice(valueStart, valueEnd), valueStart, valueEnd, quote });
    }

    return { attrs, end: i, selfClosing };
}

/**
 * Parse Blade source.
 *
 * Returns `{ nodes, elements }`: the root's children, and every plain HTML
 * element in document order — an element's place in that list is its `n`,
 * the number `mark()` writes onto it for the canvas.
 */
export function parse(text) {
    const root = { type: 'root', children: [] };
    const stack = [root];
    const elements = [];
    let id = 0;
    let i = 0;
    let textStart = -1;

    const top = () => stack[stack.length - 1];
    const push = (node) => {
        const parent = top();

        node.id = id++;

        // An element's key is its path of names — `/section0/div1/h10` — so
        // what is unfolded stays unfolded while the text around it changes
        if (node.type === 'element') {
            parent.names ??= {};
            parent.names[node.name] = (parent.names[node.name] ?? -1) + 1;
            node.key = (parent.key || '') + '/' + node.name + parent.names[node.name];
        }

        parent.children.push(node);

        return node;
    };

    const flushText = (to) => {
        if (textStart === -1) return;

        let start = textStart;
        let end = to;
        textStart = -1;

        while (start < end && /\s/.test(text[start])) start++;
        while (end > start && /\s/.test(text[end - 1])) end--;

        if (end > start) push({ type: 'text', start, end, value: text.slice(start, end) });
    };

    const closeTo = (index, at, end) => {
        while (stack.length > index) {
            const node = stack.pop();
            node.closeStart = at;
            node.end = stack.length === index ? end : at;
        }
    };

    while (i < text.length) {
        const ch = text[i];

        if (ch === '<') {
            if (text.startsWith('<!--', i)) {
                flushText(i);
                const close = text.indexOf('-->', i + 4);
                const end = close === -1 ? text.length : close + 3;
                push({ type: 'comment', start: i, end, value: text.slice(i, end) });
                i = end;
                continue;
            }

            if (text.startsWith('<?', i)) {
                flushText(i);
                const close = text.indexOf('?>', i + 2);
                const end = close === -1 ? text.length : close + 2;
                push({ type: 'directive', name: 'php', start: i, end, value: text.slice(i, end), block: true });
                i = end;
                continue;
            }

            if (text[i + 1] === '/' && NAME_START.test(text[i + 2] || '')) {
                let end = i + 2;
                while (end < text.length && NAME_CHAR.test(text[end])) end++;
                const name = text.slice(i + 2, end);
                const gt = text.indexOf('>', end);
                const after = gt === -1 ? text.length : gt + 1;

                flushText(i);

                // The nearest open element of that name; a stray closer is dropped
                for (let at = stack.length - 1; at > 0; at--) {
                    if (stack[at].name === name) { closeTo(at, i, after); break; }
                }

                i = after;
                continue;
            }

            if (NAME_START.test(text[i + 1] || '')) {
                flushText(i);

                let nameEnd = i + 1;
                while (nameEnd < text.length && NAME_CHAR.test(text[nameEnd])) nameEnd++;

                const name = text.slice(i + 1, nameEnd);
                const { attrs, end, selfClosing } = readAttributes(text, nameEnd);
                const component = name.startsWith('x-') || name.includes(':');
                const lower = name.toLowerCase();

                const node = push({
                    type: 'element', name, start: i, nameEnd, openEnd: end, end, attrs,
                    component, children: [], n: null,
                    leaf: selfClosing || VOID.has(lower),
                });

                if (!component) {
                    node.n = elements.length;
                    elements.push(node);
                }

                i = end;

                if (node.leaf) continue;

                // Script and style hold no markup: one row, never descended
                if (RAW.has(lower)) {
                    const close = text.toLowerCase().indexOf('</' + lower, end);
                    const closeAt = close === -1 ? text.length : close;
                    const gt = text.indexOf('>', closeAt);

                    node.raw = true;
                    node.closeStart = closeAt;
                    node.end = close === -1 || gt === -1 ? text.length : gt + 1;
                    i = node.end;
                    continue;
                }

                stack.push(node);
                continue;
            }
        }

        if (ch === '{' && text.startsWith('{{--', i)) {
            flushText(i);
            const end = echoEnd(text, i);
            push({ type: 'comment', start: i, end, value: text.slice(i, end) });
            i = end;
            continue;
        }

        // An echo is text: it stays in the run, read whole so a `<` or `>`
        // inside it is never taken for a tag
        if (ch === '{' && (text[i + 1] === '{' || text.startsWith('{!!', i))) {
            if (textStart === -1) textStart = i;
            i = echoEnd(text, i);
            continue;
        }

        if (ch === '@') {
            const directive = directiveAt(text, i);

            if (directive) {
                flushText(i);

                let end = directive.end;
                let block = false;

                // @php … @endphp and @verbatim … @endverbatim hold no markup
                if ((directive.name === 'php' && text[end - 1] !== ')') || directive.name === 'verbatim') {
                    const closer = '@end' + directive.name;
                    const close = text.indexOf(closer, end);
                    end = close === -1 ? text.length : close + closer.length;
                    block = true;
                }

                push({ type: 'directive', name: directive.name, start: i, end, value: text.slice(i, end), block });
                i = end;
                continue;
            }
        }

        if (textStart === -1) textStart = i;
        i++;
    }

    flushText(text.length);
    closeTo(1, text.length, text.length);

    return { nodes: root.children, elements };
}

/**
 * The source with every plain element numbered for the canvas:
 * ` data-sn="<n>"` after its tag name. Nothing else changes and no line
 * moves, so what renders is the section as written plus a way to find,
 * for any element on the canvas, the tag that drew it.
 */
export function mark(text, tree = parse(text)) {
    let out = '';
    let at = 0;

    for (const element of tree.elements) {
        out += text.slice(at, element.nameEnd) + ' data-sn="' + element.n + '"';
        at = element.nameEnd;
    }

    return out + text.slice(at);
}

/** Take the numbering back out — the inverse of `mark()`. */
export function unmark(text) {
    return text.replace(/ data-sn="\d+"/g, '');
}

/** Whether a source value is plain text — nothing Blade would evaluate. */
export function isStatic(value) {
    return !/\{\{|\{!!|@[A-Za-z]|<\?/.test(value);
}

/**
 * The tree as the rows the Elements tab draws, top to bottom.
 *
 * `open` is the set of element keys that are unfolded. An element holding
 * nothing but one short run of text is drawn on a single row, as DevTools
 * does; every other open element gets a closing row of its own.
 */
export function rows(tree, open) {
    const list = [];

    const walk = (nodes, depth) => {
        let indent = 0;

        for (const node of nodes) {
            if (node.type === 'directive' && !node.block) {
                const closes = node.name.startsWith('end') && node.name !== 'endphp';
                const middle = MIDDLES.has(node.name) && indent > 0 && !(node.name === 'empty' && /\(/.test(node.value));

                if (closes && indent > 0) indent--;
                list.push({ kind: 'directive', node, depth: depth + indent - (middle ? 1 : 0) });
                if (!closes && !middle && OPENERS.has(node.name) && !inlineDirective(node)) indent++;
                continue;
            }

            if (node.type !== 'element') {
                list.push({ kind: node.type, node, depth: depth + indent });
                continue;
            }

            const inline = inlineText(node);
            const foldable = !node.leaf && !node.raw && node.children.length > 0 && !inline;
            const isOpen = foldable && open.has(node.key);

            list.push({ kind: 'open', node, depth: depth + indent, foldable, isOpen, inline });

            if (isOpen) {
                walk(node.children, depth + indent + 1);
                list.push({ kind: 'close', node, depth: depth + indent });
            }
        }
    };

    // The tree is the markup. What stands before the first element — @props,
    // @php, the opening comment — has nothing in it to select on the canvas
    // or edit in place, so it is left to the code view.
    const first = tree.nodes.findIndex((node) => node.type === 'element' || node.type === 'text');

    walk(first === -1 ? [] : tree.nodes.slice(first), 0);

    return list;
}

/** @section('name', 'value') and friends open nothing. */
function inlineDirective(node) {
    return (node.name === 'section' || node.name === 'push' || node.name === 'slot') && /,/.test(node.value);
}

/** The single short text child an element is drawn inline with, if that is all it holds. */
function inlineText(node) {
    if (node.leaf || node.raw || node.children.length !== 1) return null;

    const only = node.children[0];

    return only.type === 'text' && only.value.length <= 80 && !only.value.includes('\n') ? only : null;
}

/** Every ancestor's key of the element numbered `n` — what must be open to show it. */
export function pathTo(tree, n) {
    const target = tree.elements[n];
    const path = [];

    const find = (nodes) => {
        for (const node of nodes) {
            if (node === target) return true;
            if (node.type === 'element' && node.children.length && find(node.children)) {
                path.push(node.key);
                return true;
            }
        }
        return false;
    };

    return target && find(tree.nodes) ? { target, path } : null;
}

export default { parse, mark, unmark, isStatic, rows, pathTo };
