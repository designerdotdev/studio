{{-- A section's code, in the inspector (developer mode).

     The header's `</>` toggle swaps the section's fields for this panel.
     The tabs are the section's two files — Blade, YAML — and Blade has
     two ways of being looked at: Tree (its markup as elements, edited
     value by value) and Code (the whole file in Monaco). The canvas renders what the panel holds, saved or not,
     so every keystroke shows on the page; ⌘S writes the files.

     There is one buffer per section — its two Monaco models. The tree is
     read from the Blade model and writes back into it, so the tabs never
     disagree and undo is one history.

     It sits beside EditorPanel, not inside it: a Livewire morph must never
     reach Monaco. EditorPanel's header says which section is open
     (`studio:inspector-section`). --}}
<div
    x-data="sectionCode"
    x-show="$store.studio.codeShowing"
    x-cloak
    class="s-sc"
    @focusin="window.Studio.codeFocus = true"
    @focusout="if (!$el.contains($event.relatedTarget)) window.Studio.codeFocus = false"
    @studio:inspector-section.window="setSection($event.detail)"
    @studio:section-code-save.window="save()"
    @studio:code-leave.window="askLeave($event.detail.next)"
    @studio:canvas-loaded.window="resend()"
    @studio:files-changed.window="syncFromDisk()"
    @studio:source-ok.window="rendered($event.detail)"
    @studio:source-error.window="failed($event.detail)"
    @studio:node-picked.window="picked($event.detail)"
    @studio:node-pick-cancel.window="setPick(false)"
>
    {{-- Toolbar --}}
    <div class="s-sc-bar">
        <div class="s-seg" role="tablist" aria-label="Section code">
            <button type="button" role="tab" class="s-seg-btn s-sc-tab" :class="tab === 'html' && 'is-active'" :aria-selected="tab === 'html'" @click="setTab('html')">Blade</button>
            <button type="button" role="tab" class="s-seg-btn s-sc-tab" :class="tab === 'yaml' && 'is-active'" :aria-selected="tab === 'yaml'" @click="setTab('yaml')">YAML</button>
        </div>

        {{-- How the Blade file is shown --}}
        <div class="s-seg" x-show="tab === 'html'" role="group" aria-label="Show the Blade file as">
            <button type="button" class="s-seg-btn s-sc-tab" :class="view === 'tree' && 'is-active'" :aria-pressed="view === 'tree'" title="The markup as a tree of elements" @click="setView('tree')">Tree</button>
            <button type="button" class="s-seg-btn s-sc-tab" :class="view === 'code' && 'is-active'" :aria-pressed="view === 'code'" title="The whole file" @click="setView('code')">Code</button>
        </div>

        <button
            type="button"
            x-show="tab === 'html'"
            class="s-icon-btn"
            :class="picking && 'is-active'"
            :aria-pressed="picking ? 'true' : 'false'"
            title="Select an element on the page to find it here"
            aria-label="Select an element on the page"
            @click="setPick(!picking)"
        >
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 13.5h-3a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v3"/><path d="m8 8 2.2 6 1.1-2.7L14 10.2 8 8Z"/></svg>
        </button>

        <span class="flex-1"></span>

        <span class="s-sc-unsaved" x-show="dirty" x-cloak>Unsaved</span>
        <button type="button" class="s-btn-ghost" x-show="dirty" x-cloak :disabled="saving" @click="discard()">Discard</button>
        <button type="button" class="s-btn-accent" :disabled="!dirty || saving || loading" @click="save()">
            <span x-text="saving ? 'Saving…' : 'Save'"></span>
            <span class="s-kbd" x-show="!saving">⌘S</span>
        </button>
    </div>

    {{-- The tree, or the editor --}}
    <div class="relative min-h-0 flex-1">
        <div
            x-ref="tree"
            x-show="tree"
            class="s-sc-tree"
            tabindex="0"
            role="tree"
            aria-label="The section's elements"
            @mouseover="treeOver($event)"
            @mouseleave="treeLeave()"
            @click="treeClick($event)"
            @dblclick="treeDoubleClick($event)"
            @keydown="treeKey($event)"
        ></div>

        <div x-ref="host" x-show="!tree" class="s-code-pane absolute inset-0"></div>

        <div x-show="loading" x-cloak class="absolute inset-0 z-10 flex items-center justify-center" style="background: var(--color-code)">
            <svg class="h-5 w-5 animate-spin text-soft" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
        </div>

        <div x-show="fatal" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 px-6 text-center" style="background: var(--color-code)">
            <p class="text-[12.5px] text-soft" x-text="fatal"></p>
            <button type="button" class="s-btn-outline" @click="reload()">Try again</button>
        </div>
    </div>

    {{-- Asked before unsaved source is left behind --}}
    <div class="s-sc-leave" x-show="leaving" x-cloak role="alertdialog" aria-label="Unsaved code">
        <span class="min-w-0 flex-1">This section's code has unsaved changes.</span>
        <button type="button" class="s-btn-ghost" @click="leaving = false">Cancel</button>
        <button type="button" class="s-btn-outline" @click="discard(); leave()">Discard</button>
        <button type="button" class="s-btn-accent" x-ref="leaveSave" :disabled="saving" @click="save().then((ok) => ok && leave())">Save</button>
    </div>

    {{-- Status: the file, or what is wrong with it --}}
    <div class="s-sc-foot" :class="error && 'is-error'" :title="error ? error.message : ''">
        <span x-show="!error" x-text="note || (tab === 'yaml' ? paths.yaml : paths.html)"></span>
        <span x-show="error" x-cloak x-text="error ? (error.file === 'yaml' ? 'YAML' : error.file === 'save' ? 'Not saved' : 'Blade') + (error.line ? ' · line ' + error.line : '') + ' — ' + error.message : ''"></span>
    </div>
</div>

@once
<script>
    (() => {
        /* Monaco's models, the parsed tree and the DOM of an edit in
           progress stay out of Alpine: it deep-proxies what it holds, and
           these are large, getter-heavy or live objects. Only printable
           state (the tab, dirty, the error) is reactive. */
        const S = {
            section: window.__studioInspectorSection || null,
            bufs: {},        // ref → { ref, html, yaml, saved, paths, open, sel, tree, rows }
            ref: null,       // the buffer on screen
            shown: false,
            editor: null,
            mounting: null,
            pushTimer: 0,
            editing: null,   // an in-place edit: { el, range, original, sent, name, quote, n, plain }
            hover: null,
            next: null,      // what to do once unsaved source is settled
            stale: false,    // the tree needs drawing when its tab is next shown
        };

        const base = @js(url(trim(config('studio.path', 'studio'), '/') . '/api/dev/components'));
        const source = () => window.Studio.sectionSource;
        const toIframe = (type, payload = {}) => window.dispatchEvent(new CustomEvent('studio:to-iframe', { detail: { type, ...payload } }));
        const escape = (text) => String(text).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const clip = (text, max) => (text.length > max ? text.slice(0, max - 1) + '…' : text);
        const isDirty = (buf) => buf.html.getValue() !== buf.saved.html || buf.yaml.getValue() !== buf.saved.yaml;

        // Source text for a row: escaped, with what Blade evaluates set apart
        const blade = (text) => escape(text).replace(/\{\{.*?\}\}|\{!!.*?!!\}/gs, (echo) => `<span class="s-sc-code">${echo}</span>`);

        // An attribute's value: each word kept whole, so a long class list
        // breaks between classes and never inside one
        const words = (text) => text.split(/(\{\{.*?\}\}|\{!!.*?!!\})/s).map((part, at) => (at % 2
            ? `<span class="s-sc-code">${escape(part)}</span>`
            : escape(part).replace(/[^\s]+/g, '<span class="s-sc-word">$&</span>'))).join('');

        const rowKey = (row) => (row.kind === 'open' ? row.node.key : row.kind === 'close' ? row.node.key + '>' : row.node.type + '@' + row.node.start);

        // Unsaved source lives only in this window: a reload asks first
        window.addEventListener('beforeunload', (event) => {
            if (!Object.values(S.bufs).some(isDirty)) return;
            event.preventDefault();
            event.returnValue = '';
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('sectionCode', () => ({
                // The file, and how the Blade file is shown
                tab: localStorage.getItem('studio.code-tab') === 'yaml' ? 'yaml' : 'html',
                view: localStorage.getItem('studio.code-view') === 'code' ? 'code' : 'tree',
                get tree() { return this.tab === 'html' && this.view === 'tree'; },
                loading: false,
                saving: false,
                dirty: false,
                picking: false,
                leaving: false,
                fatal: '',
                error: null,
                note: '',
                paths: { html: '', yaml: '' },

                init() {
                    const studio = Alpine.store('studio');

                    Alpine.effect(() => {
                        const on = studio.codeShowing;
                        if (on === S.shown) return;
                        S.shown = on;
                        queueMicrotask(() => (on ? this.show() : this.hide()));
                    });
                },

                buf() { return S.bufs[S.ref] || null; },

                /* --- which section ------------------------------------- */

                setSection(section) {
                    S.section = section;
                    if (S.shown) this.load();
                },

                show() {
                    if (S.section) this.load();
                },

                // Back to the fields, or the inspector closed. Unsaved source
                // stays on the canvas; saved source goes back to the library's.
                hide() {
                    this.endEdit(false);
                    this.setPick(false);
                    this.leaving = false;
                    toIframe('studio:node-hover', { on: false });

                    const buf = this.buf();
                    if (buf && !isDirty(buf)) toIframe('studio:source-override', { ref: buf.ref, clear: true });
                },

                reload() {
                    this.fatal = '';
                    this.load();
                },

                async load() {
                    const section = S.section;
                    if (!section?.ref) return;

                    const ref = section.ref;

                    this.endEdit(false);
                    S.ref = ref;
                    this.error = null;
                    this.note = '';
                    this.fatal = '';

                    if (!S.bufs[ref]) {
                        this.loading = true;

                        try {
                            const [data] = await Promise.all([this.fetchSource(ref), this.mount()]);
                            const model = (path, language, value) => {
                                const made = window.StudioMonaco.model('.section-code/' + path, language, value);
                                if (made.getValue() !== value) made.setValue(value);
                                return made;
                            };
                            const buf = {
                                ref,
                                html: model(data.paths.html, 'html', data.html),
                                yaml: model(data.paths.yaml, 'yaml', data.yaml),
                                saved: { html: data.html, yaml: data.yaml },
                                paths: data.paths,
                                open: new Set(),
                                sel: null,
                                tree: null,
                                rows: [],
                            };

                            // Opens two levels deep, as the browser's inspector does
                            const unfold = (nodes, depth) => nodes.forEach((node) => {
                                if (node.type !== 'element' || depth > 2) return;
                                buf.open.add(node.key);
                                unfold(node.children, depth + 1);
                            });
                            unfold(source().parse(data.html).nodes, 0);

                            buf.html.onDidChangeContent(() => this.changed(buf));
                            buf.yaml.onDidChangeContent(() => this.changed(buf));

                            S.bufs[ref] = buf;
                        } catch (e) {
                            if (S.ref === ref) this.fatal = e.message || 'Could not load the source files.';
                        }

                        if (S.ref === ref) this.loading = false;
                    } else {
                        await this.mount().catch(() => {});
                    }

                    const buf = S.bufs[ref];

                    // Another section was opened while this one loaded
                    if (!buf || S.ref !== ref) return;

                    this.paths = buf.paths;
                    this.refreshDirty();
                    this.showTab();
                    this.push(null, true);
                },

                async fetchSource(ref) {
                    const response = await fetch(`${base}/${ref}`, { headers: { Accept: 'application/json' } });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not load the source files.');
                    return data;
                },

                // One Monaco instance for the panel; the tabs swap its model
                mount() {
                    S.mounting ??= window.Studio.codeEditor(this.$refs.host, { language: 'html' })
                        .then((wrapper) => { S.editor = wrapper.editor; })
                        .catch((error) => { S.mounting = null; throw error; });

                    return S.mounting;
                },

                /* --- tabs ---------------------------------------------- */

                setTab(tab) {
                    this.endEdit(true);
                    this.tab = tab;
                    localStorage.setItem('studio.code-tab', tab);
                    if (tab !== 'html') this.setPick(false);
                    this.showTab();
                },

                setView(view) {
                    this.endEdit(true);
                    this.view = view;
                    localStorage.setItem('studio.code-view', view);
                    this.showTab();
                },

                // The Code view, with the caret on the line holding `offset`
                showCode(offset) {
                    const buf = this.buf();
                    if (!buf) return;

                    this.tab = 'html';
                    this.setView('code');

                    const at = buf.html.getPositionAt(offset);
                    this.$nextTick(() => {
                        S.editor?.setPosition(at);
                        S.editor?.revealLineInCenter(at.lineNumber);
                    });
                },

                showTab() {
                    const buf = this.buf();
                    if (!buf) return;

                    this.note = '';

                    if (this.tree) {
                        this.drawTree();
                        return;
                    }

                    const model = this.tab === 'yaml' ? buf.yaml : buf.html;
                    if (S.editor && S.editor.getModel() !== model) S.editor.setModel(model);
                    this.$nextTick(() => { S.editor?.layout(); S.editor?.focus(); });
                },

                /* --- the buffer ---------------------------------------- */

                changed(buf) {
                    if (buf.ref !== S.ref) return;

                    this.refreshDirty();
                    this.note = '';

                    if (!S.editing) {
                        if (this.tree) this.drawTree();
                        else S.stale = true;
                    }

                    this.push();
                },

                refreshDirty() {
                    const buf = this.buf();
                    this.dirty = !!buf && isDirty(buf);
                    Alpine.store('studio').codeDirty = this.dirty;
                },

                /**
                 * Hand the canvas the source to render: the buffer, or
                 * `draft` — the buffer with an in-place edit that is still
                 * being typed. Every element is numbered on the way out
                 * (data-sn) so the tree and the canvas can find each other.
                 */
                push(draft = null, now = false) {
                    const buf = this.buf();
                    if (!buf) return;

                    clearTimeout(S.pushTimer);

                    const send = () => toIframe('studio:source-override', {
                        ref: buf.ref,
                        html: source().mark(draft ?? buf.html.getValue()),
                        yaml: buf.yaml.getValue(),
                    });

                    if (now) send();
                    else S.pushTimer = setTimeout(send, 60);
                },

                // The canvas loaded afresh (a save, a refresh): it has
                // forgotten every override. Say them again.
                resend() {
                    for (const buf of Object.values(S.bufs)) {
                        if (!isDirty(buf) && !(S.shown && buf.ref === S.ref)) continue;
                        toIframe('studio:source-override', { ref: buf.ref, html: source().mark(buf.html.getValue()), yaml: buf.yaml.getValue() });
                    }
                },

                rendered({ ref }) {
                    if (ref !== S.ref) return;
                    this.error = null;
                    this.mark(null);
                },

                failed({ ref, file, message, line }) {
                    if (ref !== S.ref) return;
                    this.error = { file, message, line };
                    this.mark(file === 'yaml' && line ? { line, message } : null);
                },

                // A YAML error is underlined on its line
                mark(problem) {
                    const buf = this.buf();
                    const monaco = window.StudioMonaco?.monaco;
                    if (!buf || !monaco) return;

                    monaco.editor.setModelMarkers(buf.yaml, 'studio', problem ? [{
                        severity: monaco.MarkerSeverity.Error,
                        message: problem.message,
                        startLineNumber: problem.line,
                        startColumn: 1,
                        endLineNumber: problem.line,
                        endColumn: buf.yaml.getLineMaxColumn(Math.min(problem.line, buf.yaml.getLineCount())),
                    }] : []);
                },

                /* --- save, discard, leave ------------------------------ */

                async save() {
                    const buf = this.buf();
                    if (!buf || this.saving || this.loading || !S.shown) return false;

                    if (!this.endEdit(true)) return false;
                    if (!isDirty(buf)) return true;

                    this.saving = true;

                    const html = buf.html.getValue();
                    const yaml = buf.yaml.getValue();
                    let ok = false;

                    try {
                        const response = await fetch(`${base}/${buf.ref}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({ html, yaml }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not save the source files.');

                        buf.saved = { html, yaml };
                        this.refreshDirty();
                        if (this.error?.file === 'save') this.error = null;
                        ok = true;

                        window.Studio.toast('Section saved — every page using it is updated');
                        window.dispatchEvent(new CustomEvent('studio:files-changed', { detail: { paths: Object.values(buf.paths) } }));
                        // EditorPanel re-syncs, then reloads the canvas
                        window.Livewire?.dispatch('studio:code-saved');
                    } catch (e) {
                        this.error = { file: 'save', message: e.message };
                    }

                    this.saving = false;

                    return ok;
                },

                discard() {
                    const buf = this.buf();
                    if (!buf) return;

                    this.endEdit(false);
                    if (buf.html.getValue() !== buf.saved.html) buf.html.setValue(buf.saved.html);
                    if (buf.yaml.getValue() !== buf.saved.yaml) buf.yaml.setValue(buf.saved.yaml);
                    this.refreshDirty();
                },

                askLeave(next) {
                    S.next = next;
                    this.leaving = true;
                    this.$nextTick(() => this.$refs.leaveSave?.focus());
                },

                leave() {
                    const next = S.next;
                    S.next = null;
                    this.leaving = false;
                    next?.();
                },

                // Files written elsewhere (the Assistant, the Code view):
                // a buffer without edits follows them, one with edits is kept
                async syncFromDisk() {
                    for (const buf of Object.values(S.bufs)) {
                        const data = await this.fetchSource(buf.ref).catch(() => null);
                        if (!data || (data.html === buf.saved.html && data.yaml === buf.saved.yaml)) continue;

                        if (isDirty(buf)) {
                            if (buf.ref === S.ref) this.note = 'These files changed on disk — your unsaved edits are kept.';
                            continue;
                        }

                        buf.saved = { html: data.html, yaml: data.yaml };
                        buf.html.setValue(data.html);
                        buf.yaml.setValue(data.yaml);
                    }
                },

                /* --- the tree ------------------------------------------ */

                drawTree() {
                    const buf = this.buf();
                    if (!buf || S.editing) { S.stale = true; return; }

                    S.stale = false;
                    buf.tree = source().parse(buf.html.getValue());
                    buf.rows = source().rows(buf.tree, buf.open);

                    this.$refs.tree.innerHTML = buf.rows.length
                        ? buf.rows.map((row, index) => this.rowHtml(buf, row, index)).join('')
                        : '<p class="s-sc-empty">This section has no markup yet — write some in Code.</p>';

                    this.alignTails();
                },

                /**
                 * A row too long for the column wraps under itself, one step
                 * in. A closing tag that lands at the start of such a line is
                 * not a continuation, though: it goes back under its opening
                 * `<`. Whether it wrapped is only known once it is laid out,
                 * so it is measured — after a draw, and when the column's
                 * width changes.
                 */
                alignTails() {
                    const tree = this.$refs.tree;
                    if (!tree.offsetParent) return;

                    S.resize ??= new ResizeObserver(() => this.alignTails());
                    S.resize.observe(tree);

                    const tails = [...tree.querySelectorAll('.s-sc-tail')];
                    // Wrapped = it starts a line: what comes before it ends on the line above
                    const wrapped = tails.map((tail) => {
                        const before = [...(tail.previousElementSibling?.previousElementSibling?.getClientRects() || [])].pop();
                        return !!before && tail.getBoundingClientRect().top - before.top > 9;
                    });

                    tails.forEach((tail, at) => tail.classList.toggle('is-wrapped', wrapped[at]));
                },

                rowHtml(buf, row, index) {
                    const node = row.node;
                    const indent = row.depth * 14;
                    const tag = (close) => `<span class="s-sc-p">&lt;${close ? '/' : ''}</span><span class="s-sc-tag">${escape(node.name)}</span>`;
                    // What follows an element on its own row — the fold's … and the
                    // closing tag — stays in one piece (see alignTails)
                    const closer = '<wbr><span class="s-sc-tail">' + tag(true) + '<span class="s-sc-p">&gt;</span></span>';
                    const folded = '<wbr><span class="s-sc-tail"><span class="s-sc-more" data-fold>…</span>' + tag(true) + '<span class="s-sc-p">&gt;</span></span>';
                    let body = '';

                    if (row.kind === 'open') {
                        if (row.foldable) body += `<span class="s-sc-fold${row.isOpen ? ' is-open' : ''}" data-fold style="left:${indent + 6}px"></span>`;

                        body += tag(false);

                        node.attrs.forEach((attr, at) => {
                            if (attr.kind === 'code') {
                                body += ` <span class="s-sc-code">${escape(clip(attr.raw.replace(/\s+/g, ' '), 120))}</span>`;
                                return;
                            }

                            body += ` <span class="s-sc-attr">${escape(attr.name)}</span>`;

                            if (attr.value !== null) {
                                body += `<span class="s-sc-p">=${attr.quote}</span>`
                                    + `<span class="s-sc-val" data-edit="attr" data-at="${at}">${words(clip(attr.value, 220))}</span>`
                                    + `<span class="s-sc-p">${attr.quote}</span>`;
                            }
                        });

                        body += '<span class="s-sc-p">&gt;</span>';

                        if (row.inline) body += `<span class="s-sc-text" data-edit="text">${blade(row.inline.value)}</span>` + closer;
                        else if (node.raw || (row.foldable && !row.isOpen)) body += folded;
                        else if (!node.leaf && !row.isOpen) body += closer;
                    } else if (row.kind === 'close') {
                        body = tag(true) + '<span class="s-sc-p">&gt;</span>';
                    } else if (row.kind === 'text') {
                        body = `<span class="s-sc-text" data-edit="text">${blade(clip(node.value.replace(/\s+/g, ' '), 400))}</span>`;
                    } else if (row.kind === 'directive') {
                        const first = node.value.split('\n')[0];
                        body = `<span class="s-sc-code">${escape(clip(first, 160))}${node.block && first !== node.value ? ' …' : ''}</span>`;
                    } else {
                        body = `<span class="s-sc-comment">${escape(clip(node.value.replace(/\s+/g, ' '), 160))}</span>`;
                    }

                    return `<div class="s-sc-row${rowKey(row) === buf.sel ? ' is-selected' : ''}" role="treeitem" data-i="${index}" style="padding-left:${indent + 36}px">${body}</div>`;
                },

                rowAt(event) {
                    const el = event.target.closest?.('[data-i]');
                    const buf = this.buf();
                    return el && buf ? { el, index: Number(el.dataset.i), row: buf.rows[Number(el.dataset.i)] } : null;
                },

                // The pointer on a row lights what that tag draws on the canvas
                treeOver(event) {
                    const hit = this.rowAt(event);
                    const n = hit?.row.node.type === 'element' ? hit.row.node.n : null;

                    if (n === S.hover) return;
                    S.hover = n;
                    toIframe('studio:node-hover', n === null ? { on: false } : { on: true, ref: S.ref, n });
                },

                treeLeave() {
                    S.hover = null;
                    toIframe('studio:node-hover', { on: false });
                },

                treeClick(event) {
                    if (S.editing) return;

                    const hit = this.rowAt(event);
                    if (!hit) return;

                    if (event.target.closest('[data-fold]')) this.toggle(hit.row);
                    this.select(hit.index);
                },

                treeDoubleClick(event) {
                    if (S.editing) return;

                    const hit = this.rowAt(event);
                    if (!hit) return;

                    const target = event.target.closest('[data-edit]');

                    if (target) this.edit(hit.row, target);
                    else if (hit.row.foldable && !event.target.closest('[data-fold]')) { this.toggle(hit.row); this.select(hit.index); }
                    // @@props, @@if, a comment: nothing to edit in place — its line in the code
                    else if (hit.row.kind === 'directive' || hit.row.kind === 'comment') this.showCode(hit.row.node.start);
                },

                treeKey(event) {
                    const buf = this.buf();
                    if (!buf || S.editing) return;

                    const meta = event.metaKey || event.ctrlKey;

                    // The buffer's own history, from the tree
                    if (meta && (event.key === 'z' || event.key === 'Z')) {
                        event.preventDefault();
                        event.shiftKey ? buf.html.redo() : buf.html.undo();
                        return;
                    }

                    // ⌘A here would select the whole editor
                    if (meta && (event.key === 'a' || event.key === 'A')) event.preventDefault();

                    if (meta || event.altKey) return;

                    const at = buf.rows.findIndex((row) => rowKey(row) === buf.sel);
                    const row = buf.rows[at];
                    const step = (to) => { if (buf.rows[to]) this.select(to); };

                    switch (event.key) {
                        case 'ArrowDown': step(at + 1); break;
                        case 'ArrowUp': step(at === -1 ? 0 : at - 1); break;
                        case 'Home': step(0); break;
                        case 'End': step(buf.rows.length - 1); break;
                        case 'ArrowRight':
                            if (row?.foldable && !row.isOpen) { this.toggle(row); this.select(at); }
                            else step(at + 1);
                            break;
                        case 'ArrowLeft':
                            if (row?.isOpen) { this.toggle(row); this.select(at); break; }
                            for (let up = at - 1; up >= 0; up--) {
                                if (buf.rows[up].kind === 'open' && buf.rows[up].depth < row.depth) { step(up); break; }
                            }
                            break;
                        case 'Enter': {
                            const el = this.$refs.tree.querySelector(`[data-i="${at}"]`);
                            const target = el?.querySelector('[data-edit]');
                            if (row && target) this.edit(row, target);
                            break;
                        }
                        default: return;
                    }

                    event.preventDefault();
                },

                toggle(row) {
                    const buf = this.buf();
                    if (!row.foldable) return;

                    buf.open.has(row.node.key) ? buf.open.delete(row.node.key) : buf.open.add(row.node.key);
                    this.drawTree();
                },

                select(index, { reveal = true } = {}) {
                    const buf = this.buf();
                    const row = buf?.rows[index];
                    if (!row) return;

                    buf.sel = rowKey(row);

                    this.$refs.tree.querySelector('.is-selected')?.classList.remove('is-selected');
                    const el = this.$refs.tree.querySelector(`[data-i="${index}"]`);
                    el?.classList.add('is-selected');
                    el?.scrollIntoView({ block: 'nearest' });

                    if (reveal && row.node.type === 'element' && row.node.n !== null) {
                        toIframe('studio:node-reveal', { ref: buf.ref, n: row.node.n });
                    }
                },

                /* --- picking from the canvas --------------------------- */

                setPick(on) {
                    if (on === this.picking) return;
                    this.picking = on;
                    window.Studio.nodePicking = on;
                    toIframe('studio:node-pick', { on });
                },

                // An element was picked (or double-clicked) on the canvas: show its row
                picked({ ref, n }) {
                    const buf = this.buf();
                    if (!buf || ref !== S.ref || !S.shown) return;

                    this.setPick(false);
                    if (!this.endEdit(true)) return;

                    buf.tree = source().parse(buf.html.getValue());
                    const hit = source().pathTo(buf.tree, n);
                    if (!hit) return;

                    // In the code, the pick is the tag's line with its name selected
                    if (this.tab === 'html' && this.view === 'code') {
                        const from = buf.html.getPositionAt(hit.target.start + 1);
                        const to = buf.html.getPositionAt(hit.target.nameEnd);

                        S.editor?.setSelection(new window.StudioMonaco.monaco.Range(from.lineNumber, from.column, to.lineNumber, to.column));
                        S.editor?.revealLineInCenter(from.lineNumber);
                        S.editor?.focus();
                        toIframe('studio:node-reveal', { ref: buf.ref, n });
                        return;
                    }

                    if (!this.tree) { this.tab = 'html'; this.setView('tree'); }

                    hit.path.forEach((key) => buf.open.add(key));
                    this.drawTree();

                    const index = buf.rows.findIndex((row) => row.kind === 'open' && row.node.n === n);
                    if (index === -1) return;

                    this.select(index);
                    this.$refs.tree.querySelector(`[data-i="${index}"]`)?.scrollIntoView({ block: 'center' });
                    this.$refs.tree.focus({ preventScroll: true });
                },

                /* --- editing in place ---------------------------------- */

                /**
                 * A double click on an attribute's value or on text: the
                 * span becomes editable, holding the source exactly as
                 * written. What is typed shows on the canvas at once and
                 * reaches the buffer on Enter (or when the caret leaves);
                 * Esc puts everything back.
                 */
                edit(row, el) {
                    const buf = this.buf();
                    const node = row.node;
                    let edit;

                    if (el.dataset.edit === 'attr') {
                        const attr = node.attrs[Number(el.dataset.at)];
                        edit = { range: [attr.valueStart, attr.valueEnd], original: attr.value, name: attr.name, quote: attr.quote, n: node.n };
                    } else {
                        const text = row.kind === 'open' ? row.inline : node;
                        edit = { range: [text.start, text.end], original: text.value, name: null };
                    }

                    S.editing = { ...edit, el, sent: edit.original, plain: source().isStatic(edit.original), touched: false };

                    el.textContent = edit.original;
                    el.classList.add('is-editing');
                    el.setAttribute('spellcheck', 'false');
                    try { el.contentEditable = 'plaintext-only'; } catch { el.contentEditable = 'true'; }
                    el.focus();
                    document.getSelection().selectAllChildren(el);

                    el.addEventListener('input', S.editing.onInput = () => this.typing());
                    el.addEventListener('blur', S.editing.onBlur = () => this.endEdit(true, { strict: false }));
                    el.addEventListener('keydown', S.editing.onKey = (event) => {
                        event.stopPropagation();

                        if (event.key === 'Escape') {
                            event.preventDefault();
                            this.endEdit(false);
                            this.$refs.tree.focus({ preventScroll: true });
                        } else if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            if (this.endEdit(true)) this.$refs.tree.focus({ preventScroll: true });
                        } else if (event.key === 'Tab') {
                            event.preventDefault();
                            this.editNext(event.shiftKey ? -1 : 1);
                        }
                    });
                    el.addEventListener('paste', S.editing.onPaste = (event) => {
                        event.preventDefault();
                        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
                    });
                },

                value() {
                    const edit = S.editing;
                    const text = edit.el.textContent;
                    return edit.name ? text.replace(/\s*\n\s*/g, ' ') : text;
                },

                typing() {
                    const buf = this.buf();
                    const edit = S.editing;
                    const value = this.value();
                    const text = buf.html.getValue();

                    edit.touched = true;
                    edit.el.classList.remove('is-refused');

                    // A plain attribute lands on the page before the server answers
                    if (edit.name && edit.n !== null && edit.plain && source().isStatic(value)) {
                        toIframe('studio:node-attr', { ref: buf.ref, n: edit.n, name: edit.name, value, previous: edit.sent });
                        edit.sent = value;
                    }

                    this.push(text.slice(0, edit.range[0]) + value + text.slice(edit.range[1]));
                },

                // A value its own quotes cannot hold would break the tag around it
                refused(edit, value) {
                    if (!edit.name) return '';
                    if (edit.quote) return source().isStatic(value) && value.includes(edit.quote) ? `That value holds a ${edit.quote} — it would end the attribute. Change it in Code.` : '';
                    return /[\s"'=<>`]/.test(value) || value === '' ? 'An unquoted value cannot hold spaces or quotes — add quotes in Code.' : '';
                },

                /**
                 * Finish the edit in progress: write it to the buffer
                 * (`commit`) or put the source back. Returns false when the
                 * value was refused and the edit stays open; a blur is not
                 * `strict`, so a refused value is dropped rather than
                 * trapping the caret.
                 */
                endEdit(commit, { strict = true } = {}) {
                    const edit = S.editing;
                    const buf = this.buf();
                    if (!edit || !buf) return true;

                    const value = this.value();

                    if (commit && value !== edit.original) {
                        const reason = this.refused(edit, value);

                        if (reason && strict) {
                            edit.el.classList.remove('is-refused');
                            void edit.el.offsetWidth;
                            edit.el.classList.add('is-refused');
                            this.note = reason;
                            return false;
                        }

                        if (reason) commit = false;
                    }

                    S.editing = null;
                    edit.el.removeEventListener('input', edit.onInput);
                    edit.el.removeEventListener('blur', edit.onBlur);
                    edit.el.removeEventListener('keydown', edit.onKey);
                    edit.el.removeEventListener('paste', edit.onPaste);
                    edit.el.removeAttribute('contenteditable');

                    if (commit && value !== edit.original) {
                        // One step in the Blade model's history: ⌘Z undoes it from either tab
                        const from = buf.html.getPositionAt(edit.range[0]);
                        const to = buf.html.getPositionAt(edit.range[1]);

                        buf.html.pushStackElement();
                        buf.html.pushEditOperations([], [{
                            range: new window.StudioMonaco.monaco.Range(from.lineNumber, from.column, to.lineNumber, to.column),
                            text: value,
                        }], () => null);
                        buf.html.pushStackElement();

                        return true;
                    }

                    // Nothing kept: the canvas goes back to the buffer
                    if (edit.touched) {
                        if (edit.name && edit.n !== null && edit.plain) {
                            toIframe('studio:node-attr', { ref: buf.ref, n: edit.n, name: edit.name, value: edit.original, previous: edit.sent });
                        }
                        this.push(null, true);
                    }

                    if (this.tree) this.drawTree();

                    return true;
                },

                // Tab: keep this value and edit the next one in the tree
                editNext(by) {
                    const buf = this.buf();
                    const all = [...this.$refs.tree.querySelectorAll('[data-edit]')];
                    const at = all.indexOf(S.editing.el);
                    const mark = (el) => el && { index: Number(el.closest('[data-i]').dataset.i), order: [...el.closest('[data-i]').querySelectorAll('[data-edit]')].indexOf(el) };
                    const next = mark(all[at + by]);

                    if (!this.endEdit(true) || !next) return;

                    // The tree was redrawn from the new source
                    const el = this.$refs.tree.querySelector(`[data-i="${next.index}"]`)?.querySelectorAll('[data-edit]')[next.order];
                    const row = buf.rows[next.index];

                    if (el && row) { this.select(next.index, { reveal: false }); this.edit(row, el); }
                },
            }));
        });
    })();
</script>
@endonce
