import Sortable from 'sortablejs';
import collapse from '@alpinejs/collapse';

// Client-side Blade renderer (used by the preview iframe)

// Register Alpine plugins used by the editor chrome (Livewire bundles Alpine core only)
document.addEventListener('alpine:init', () => {
    window.Alpine?.plugin(collapse);
});

/* ------------------------------------------------------------------ */
/*  Toasts                                                             */
/* ------------------------------------------------------------------ */

const TOAST_ICONS = {
    success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>',
    error: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>',
    info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd"/></svg>',
};

function toast(message, type = 'success', duration = 3200, action = null) {
    let container = document.getElementById('studio-toasts');

    if (!container) {
        container = document.createElement('div');
        container.id = 'studio-toasts';
        document.body.appendChild(container);
    }

    const el = document.createElement('div');
    el.className = `studio-toast studio-toast--${type}`;
    el.innerHTML = `<span class="studio-toast__icon">${TOAST_ICONS[type] || TOAST_ICONS.info}</span><span>${message}</span>`;
    el.addEventListener('click', () => dismiss());
    container.appendChild(el);

    let dismissed = false;
    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        el.classList.remove('is-visible');
        setTimeout(() => el.remove(), 220);
    };

    // Optional action button (e.g. Undo) — actionable toasts linger longer
    if (action?.label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'studio-toast__action';
        button.textContent = action.label;
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            action.onClick?.();
            dismiss();
        });
        el.appendChild(button);
        duration = Math.max(duration, 6000);
    }

    requestAnimationFrame(() => el.classList.add('is-visible'));

    setTimeout(dismiss, duration);
}

/* ------------------------------------------------------------------ */
/*  Editor runtime (parent window)                                     */
/* ------------------------------------------------------------------ */

const StudioEditor = {
    iframe: null,
    selectedId: null,

    init() {
        this.iframe = document.getElementById('studio-canvas-frame');

        if (!this.iframe) return;

        this.listenToIframe();
        this.listenToPanel();
        this.bindShortcuts();
        this.trackSaveStatus();
    },

    send(type, payload = {}) {
        if (this.iframe?.contentWindow) {
            this.iframe.contentWindow.postMessage({ type, ...payload }, window.location.origin);
        }
    },

    listenToIframe() {
        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) return;
            if (!event.data || typeof event.data.type !== 'string') return;

            const { type, ...data } = event.data;

            switch (type) {
                case 'studio:section-selected':
                    this.selectedId = data.sectionId;
                    // Selecting never opens the panel — the inspector is on
                    // request (the toolbar's Edit fields, E). Livewire still
                    // tracks the selection so an open panel follows it.
                    window.Livewire?.dispatch('studio:select-section', { id: data.sectionId });
                    break;

                case 'studio:open-inspector':
                    this.selectedId = data.sectionId;
                    window.Livewire?.dispatch('studio:select-section', { id: data.sectionId });
                    window.Alpine?.store('studio')?.openInspector?.();
                    break;

                case 'studio:deselected':
                    this.selectedId = null;
                    window.Livewire?.dispatch('studio:deselect-section');
                    break;

                case 'studio:add-section':
                    window.dispatchEvent(new CustomEvent('studio:open-library', {
                        detail: { index: data.index ?? null, scope: data.scope || 'page' },
                    }));
                    break;

                case 'studio:section-action':
                    window.Livewire?.dispatch('studio:section-action', { id: data.sectionId, action: data.action });
                    break;

                case 'studio:item-action':
                    // add-before | add-after | remove | move — relayed
                    // as-is to EditorPanel::handleItemAction(), which
                    // composes the existing addRepeaterItem/
                    // removeRepeaterItem/moveRepeaterItem methods. The
                    // canvas already refused to send this for a
                    // collections.*-bound repeater (isCollectionBound), and
                    // saveVariables() drops a bound key regardless.
                    window.Livewire?.dispatch('studio:item-action', {
                        sectionId: data.sectionId,
                        key: data.key,
                        index: data.index,
                        action: data.action,
                        toIndex: data.toIndex ?? null,
                    });
                    break;

                case 'studio:open-code':
                    window.dispatchEvent(new CustomEvent('studio:open-code-editor', {
                        detail: { ref: data.ref, title: data.title },
                    }));
                    break;

                case 'studio:promote-field':
                    this.promoteField(data);
                    break;

                case 'studio:element-selected':
                    // The Assistant composer listens for this and shows a chip
                    window.dispatchEvent(new CustomEvent('studio:element-selected', { detail: data }));
                    break;

                case 'studio:field-committed':
                    // A plain text commit (no `echo`) must not repaint — it
                    // would destroy the caret — so it keeps going through
                    // setFieldFromCanvas(), byte-identical to before. A
                    // resolved non-text value (link/select/colour, from the
                    // canvas control) sets `echo: true`: the canvas already
                    // shows the new value, so this only needs to persist —
                    // through the same repainting path resolveFieldAction()
                    // uses for an image pick, which already branches on a
                    // repeater sub-field the same way.
                    if (data.echo) {
                        if (data.index !== null && data.subKey) {
                            window.Livewire?.dispatch('studio:set-repeater-sub-field', {
                                sectionId: data.sectionId,
                                fieldKey: data.key,
                                index: data.index,
                                subField: data.subKey,
                                value: data.value,
                            });
                        } else {
                            window.Livewire?.dispatch('studio:set-field-value', {
                                sectionId: data.sectionId,
                                key: data.key,
                                value: data.value,
                            });
                        }
                        break;
                    }

                    window.Livewire?.dispatch('studio:set-field', {
                        sectionId: data.sectionId,
                        key: data.key,
                        value: data.value,
                        index: data.index,
                        subKey: data.subKey,
                    });
                    break;

                case 'studio:field-selected':
                    window.dispatchEvent(new CustomEvent('studio:field-focus', { detail: data }));
                    break;

                case 'studio:field-action':
                    this.resolveFieldAction(data);
                    break;

                case 'studio:field-upload':
                    this.uploadFieldFile(data);
                    break;

                case 'studio:open-code-at':
                    window.Alpine?.store('studio')?.setMode('code');
                    window.Alpine?.store('code')?.openFileAt(data.path, data.line);
                    break;

                case 'studio:navigate':
                    this.navigate(data.path);
                    break;

                case 'studio:key':
                    this.handleShortcut(data);
                    break;
            }
        });
    },

    listenToPanel() {
        // Livewire panel (or field inputs) → iframe
        window.addEventListener('studio:to-iframe', (event) => {
            const { type, ...payload } = event.detail || {};
            if (type) this.send(type, payload);
        });

        // Clicking anywhere in the editor chrome closes the canvas context menu
        document.addEventListener('mousedown', () => this.send('studio:menu-close'), true);

        // Structural changes persisted → reload the preview document
        window.addEventListener('studio:refresh-preview', () => this.refreshPreview());

        // Panel tracks selection too (list clicks)
        window.addEventListener('studio:selection-changed', (event) => {
            this.selectedId = event.detail?.id ?? null;
        });
    },

    /**
     * A link followed in Preview mode. The editor is bound to one page, so
     * moving to another means moving the whole window to that page's editor
     * URL — which keeps the panel, the page pill and the canvas in step for
     * free. Paths Studio doesn't own are opened in a new tab instead.
     */
    navigate(path) {
        const pages = window.__studioPages || [];
        const slug = (path || '/').replace(/^\/+|\/+$/g, '');
        const match = pages.find((page) => page.path === slug);

        if (!match) {
            window.open(path, '_blank', 'noopener');
            return;
        }

        if (match.slug === window.__studioPageSlug) {
            // Already here — a link back to the current page just scrolls up
            try { this.iframe.contentWindow.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { /* ignore */ }
            return;
        }

        window.location.href = window.__studioEditorUrl + '?page=' + encodeURIComponent(match.slug);
    },

    refreshPreview() {
        if (!this.iframe) return;

        let scrollY = 0;
        try {
            scrollY = this.iframe.contentWindow.scrollY || 0;
        } catch (e) { /* ignore */ }

        this.iframe.classList.add('is-loading');

        const onLoad = () => {
            this.iframe.removeEventListener('load', onLoad);
            try {
                this.iframe.contentWindow.scrollTo(0, scrollY);
                if (this.selectedId) {
                    this.send('studio:select', { sectionId: this.selectedId, scroll: false });
                }
            } catch (e) { /* ignore */ }
            this.iframe.classList.remove('is-loading');
        };

        this.iframe.addEventListener('load', onLoad);
        this.iframe.contentWindow.location.reload();
    },

    bindShortcuts() {
        document.addEventListener('keydown', (event) => {
            this.handleShortcut({
                key: event.key,
                code: event.code,
                meta: event.metaKey || event.ctrlKey,
                alt: event.altKey,
                typing: isTyping(),
                preventDefault: () => event.preventDefault(),
            });
        });
    },

    handleShortcut({ key, code = '', meta, alt = false, typing, preventDefault = () => {} }) {
        // The dev-mode code modal owns the keyboard while open (its own
        // window-level handlers run after this document-level one)
        if (window.Studio?.codeModalOpen) return;

        // Cmd/Ctrl+S — everything autosaves, so this is only reassurance.
        // Code mode is the exception: files there save explicitly, and the
        // code pane's own handler owns the key.
        if (meta && (key === 's' || key === 'S')) {
            if (window.Alpine?.store('studio')?.mode === 'code') return;
            preventDefault();
            toast('All changes save automatically', 'info', 2200);
            return;
        }

        // Cmd/Ctrl+K — the command palette (Add Section is its first entry)
        if (meta && (key === 'k' || key === 'K')) {
            preventDefault();
            window.dispatchEvent(new CustomEvent('studio:open-palette'));
            return;
        }

        // Cmd/Ctrl+. — hide or show the dock (the site, and nothing else)
        if (meta && key === '.') {
            preventDefault();
            window.Alpine?.store('studio')?.toggleDock?.();
            return;
        }

        // Option+1/2/3 — canvas width (⌘1-3 belong to the browser's tabs;
        // `code` because Option changes `key` on a Mac keyboard)
        if (alt && !meta && /^Digit[123]$/.test(code)) {
            preventDefault();
            const studio = window.Alpine?.store('studio');
            if (studio) studio.device = { Digit1: 'desktop', Digit2: 'tablet', Digit3: 'mobile' }[code];
            return;
        }

        if (typing) return;

        // Cmd/Ctrl+B — collapse the sidebar for a full-width canvas (after
        // the typing check: rich-text fields own ⌘B as bold)
        if (meta && (key === 'b' || key === 'B')) {
            preventDefault();
            window.Alpine?.store('studio')?.toggleSidebar();
            return;
        }

        if (key === 'Escape') {
            // An open panel closes first; the next Escape deselects
            const studio = window.Alpine?.store('studio');
            if (studio?.sidebar) {
                studio.closePanel();
                return;
            }
            if (this.selectedId) {
                this.selectedId = null;
                this.send('studio:deselect');
                window.Livewire?.dispatch('studio:deselect-section');
            }
            return;
        }

        if (!this.selectedId) return;

        // E — the inspector for the selected section
        if (!meta && (key === 'e' || key === 'E')) {
            preventDefault();
            window.Livewire?.dispatch('studio:select-section', { id: this.selectedId });
            window.Alpine?.store('studio')?.openInspector?.();
            return;
        }

        if (meta && (key === 'd' || key === 'D')) {
            preventDefault();
            window.Livewire?.dispatch('studio:section-action', { id: this.selectedId, action: 'duplicate' });
            return;
        }

        // Cmd/Ctrl+↑/↓ — reorder the selected section
        if (meta && (key === 'ArrowUp' || key === 'ArrowDown')) {
            preventDefault();
            window.Livewire?.dispatch('studio:section-action', {
                id: this.selectedId,
                action: key === 'ArrowUp' ? 'move-up' : 'move-down',
            });
            return;
        }

        if (key === 'Backspace' || key === 'Delete') {
            preventDefault();
            // Undoable via the toast — no confirm needed
            window.Livewire?.dispatch('studio:section-action', { id: this.selectedId, action: 'delete' });
            this.selectedId = null;
        }
    },

    trackSaveStatus() {
        document.addEventListener('livewire:init', () => {
            window.Livewire.hook('commit', ({ component, succeed, fail }) => {
                if (component.name !== 'studio::editor-panel') return;

                setStatus('saving');

                succeed(() => setStatus('saved'));
                fail(() => {
                    setStatus('error');
                    toast('Could not save changes', 'error');
                });
            });
        });
    },

    /**
     * Declare an undeclared canvas echo as a real field — the source-file
     * write happens server-side; this only asks for it and reacts to the
     * result, exactly as the dev-mode code modal's own save() does.
     */
    async promoteField({ ref, key }) {
        if (!ref || !key) return;

        try {
            const response = await fetch(`${window.__studioEditorUrl}/api/dev/components/${ref}/field`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ key }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) throw new Error(data.message || 'Could not add that field.');

            toast(`Added "${key}" as a field`);
            this.refreshPreview();
            if (data.synced) window.Livewire?.dispatch('studio:code-saved');
        } catch (e) {
            toast(e.message || 'Could not add that field.', 'error');
        }
    },

    /**
     * Resolve a canvas field action in the editor window, then persist it
     * and hand the value back to the canvas.
     *
     * Unlike a text edit, these types want the repaint — so this goes
     * through EditorPanel::setVariable / updateRepeaterSubField, which
     * persist AND echo to the iframe.
     */
    async resolveFieldAction({ sectionId, key, index, subKey, action }) {
        let value = null;

        if (action === 'pick-media') {
            value = await window.Studio.mediaPick();
        }

        if (value === null || value === undefined) return;

        if (index !== null && subKey) {
            window.Livewire?.dispatch('studio:set-repeater-sub-field', { sectionId, fieldKey: key, index, subField: subKey, value });
        } else {
            window.Livewire?.dispatch('studio:set-field-value', { sectionId, key, value });
        }

        this.send('studio:field-value', { sectionId, key, index, subKey, value });
    },

    /** A file dropped straight onto an image field — upload, then treat it
     * exactly like a resolved field action once the URL comes back. */
    async uploadFieldFile({ sectionId, key, index, subKey, file }) {
        try {
            const url = await window.Studio.upload(file, {
                url: window.__studioUploadUrl,
                csrf: document.querySelector('meta[name=csrf-token]').content,
            });

            if (index !== null && subKey) {
                window.Livewire?.dispatch('studio:set-repeater-sub-field', { sectionId, fieldKey: key, index, subField: subKey, value: url });
            } else {
                window.Livewire?.dispatch('studio:set-field-value', { sectionId, key, value: url });
            }

            this.send('studio:field-value', { sectionId, key, index, subKey, value: url });
        } catch (e) {
            window.Studio.toast(e.message || 'Upload failed', 'error');
        }
    },
};

let statusTimeout = null;

function setStatus(state) {
    clearTimeout(statusTimeout);
    window.dispatchEvent(new CustomEvent('studio:status', { detail: { state } }));

    if (state === 'saved') {
        statusTimeout = setTimeout(() => {
            window.dispatchEvent(new CustomEvent('studio:status', { detail: { state: 'idle' } }));
        }, 2000);
    }
}

function isTyping(doc = document) {
    const el = doc.activeElement;
    if (!el) return false;
    const tag = el.tagName?.toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
}

/* ------------------------------------------------------------------ */
/*  Preview runtime (inside the canvas iframe)                         */
/* ------------------------------------------------------------------ */

/**
 * The canvas field map.
 *
 * Section markup arrives carrying comment sentinels around every echo of a
 * declared field (`<!--sf:headingStart@41-->…<!--/sf-->`), plus
 * `data-sf-attr` / `data-sf-when` on tags. This walks them into live Ranges
 * and elements, so the DOM itself becomes the index: a field's box comes
 * from `Range.getBoundingClientRect()`, which fits the text exactly even
 * when three fields share one <h1>.
 *
 * Anything with no entry is, by definition, set in code.
 */
const StudioFields = {
    maps: {},       // sectionId → { entries: [...], items: [...] }
    paths: {},      // ref → 'sections/hero'
    contracts: {},  // ref → { key: { label, type } }

    init(paths, contracts) {
        this.paths = paths || {};
        this.contracts = contracts || {};
        this.indexAll();
    },

    indexAll() {
        this.maps = {};
        document.querySelectorAll('[data-section]').forEach((wrapper) => this.index(wrapper));
    },

    /** (Re)build the map for one section. Called after every paint. */
    index(wrapper) {
        const sectionId = wrapper.dataset.section;
        const content = wrapper.querySelector('[data-section-content]');

        if (!sectionId || !content) return;

        const entries = [];
        const open = [];
        const walker = document.createTreeWalker(content, NodeFilter.SHOW_COMMENT);

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const value = node.nodeValue || '';

            // `sf?:` is an undeclared echo — same closing comment, but it
            // never resolves to a declared field (see editabilityOf()).
            if (value.startsWith('sf:') || value.startsWith('sf?:')) {
                const undeclared = value.startsWith('sf?:');
                const prefixLen = undeclared ? 4 : 3;
                const at = value.lastIndexOf('@');
                open.push({ raw: value.slice(prefixLen, at), line: Number(value.slice(at + 1)) || 0, start: node, undeclared });
                continue;
            }

            if (value === '/sf' && open.length) {
                const { raw, line, start, undeclared } = open.pop();
                const range = document.createRange();

                try {
                    range.setStartAfter(start);
                    range.setEndBefore(node);
                } catch (e) {
                    continue;
                }

                entries.push({
                    ...this.parsePath(raw),
                    line,
                    kind: undeclared ? 'undeclared' : 'text',
                    range,
                    el: null,
                    // A raw echo (`{!! !!}`) is scanned the same as an
                    // escaped one and gets the same `kind: 'text'` sentinel,
                    // but when it renders markup (an <svg> icon, say) rather
                    // than a text node, editing it would read an empty
                    // innerText and wipe the field. Computed once here,
                    // not per hover/click — see editabilityOf().
                    textHost: this.isTextRange(range),
                });
            }
        }

        // Attribute and toggle markers live on the tag itself
        content.querySelectorAll('[data-sf-attr]').forEach((el) => {
            el.getAttribute('data-sf-attr').split(';').forEach((pair) => {
                const [attribute, rest] = pair.split(':');
                if (!rest) return;
                const at = rest.lastIndexOf('@');
                entries.push({
                    ...this.parsePath(rest.slice(0, at)),
                    line: Number(rest.slice(at + 1)) || 0,
                    kind: 'attr',
                    attribute,
                    range: null,
                    el,
                });
            });
        });

        content.querySelectorAll('[data-sf-when]').forEach((el) => {
            const raw = el.getAttribute('data-sf-when');
            const at = raw.lastIndexOf('@');
            entries.push({
                ...this.parsePath(raw.slice(0, at)),
                line: Number(raw.slice(at + 1)) || 0,
                kind: 'when',
                range: null,
                el,
            });
        });

        this.maps[sectionId] = { entries, items: this.groupItems(entries) };
    },

    /** `people.0.name` → {path, key: 'people', index: 0, subKey: 'name'} */
    parsePath(raw) {
        const parts = raw.split('.');

        if (parts.length >= 3 && /^\d+$/.test(parts[1])) {
            return { path: raw, key: parts[0], index: Number(parts[1]), subKey: parts.slice(2).join('.') };
        }

        return { path: raw, key: parts[0], index: null, subKey: null };
    },

    /**
     * Repeater items are derived, not instrumented: entries sharing a
     * `key.index` prefix are grouped and their nearest common ancestor
     * element becomes the item's box.
     */
    groupItems(entries) {
        const groups = {};

        entries.forEach((entry) => {
            if (entry.index === null) return;
            const id = entry.key + '.' + entry.index;
            (groups[id] = groups[id] || []).push(entry);
        });

        return Object.entries(groups)
            .map(([id, members]) => ({
                id,
                key: members[0].key,
                index: members[0].index,
                el: this.commonAncestor(members),
            }))
            .filter((item) => item.el);
    },

    commonAncestor(entries) {
        const elementOf = (entry) => {
            const node = entry.range ? entry.range.commonAncestorContainer : entry.el;
            return node && node.nodeType === 1 ? node : node?.parentElement || null;
        };

        let el = elementOf(entries[0]);

        for (const entry of entries.slice(1)) {
            const other = elementOf(entry);
            while (el && other && !el.contains(other)) el = el.parentElement;
        }

        return el;
    },

    entriesFor(sectionId) {
        return this.maps[sectionId]?.entries || [];
    },

    /**
     * The `href` attr entry, if any, carried by `el` itself or the nearest
     * ancestor still inside this section — i.e. "is this text sitting
     * inside a link". A link's own text entry always wins the hit test
     * against its href attr entry (the smallest box wins, and the text
     * range nests inside the anchor's box), so this is how the link
     * popover finds its href without depending on winning that hit test.
     */
    hrefEntryFor(sectionId, el) {
        const entries = this.entriesFor(sectionId);

        while (el) {
            const entry = entries.find((e) => e.kind === 'attr' && e.attribute === 'href' && e.el === el);
            if (entry) return entry;

            // Reached the section wrapper without finding one — entries
            // never live outside it, so there is nothing further up worth
            // walking to.
            if (el.dataset && el.dataset.section === sectionId) break;

            el = el.parentElement;
        }

        return null;
    },

    /** Every rect a field occupies — text wraps, so there may be several. */
    rects(entry) {
        if (entry.kind === 'text' && entry.range) {
            return Array.from(entry.range.getClientRects());
        }

        return entry.el ? [entry.el.getBoundingClientRect()] : [];
    },

    /** The union box, for drawing a halo around a whole wrapped heading. */
    box(entry) {
        const rects = this.rects(entry);

        if (!rects.length) return null;

        const left = Math.min(...rects.map((r) => r.left));
        const top = Math.min(...rects.map((r) => r.top));
        const right = Math.max(...rects.map((r) => r.right));
        const bottom = Math.max(...rects.map((r) => r.bottom));

        return { left, top, width: right - left, height: bottom - top };
    },

    /**
     * The field under a point. Text entries win over attribute ones (an
     * image's alt text and its src share an element), and the smallest
     * matching box wins so a nested field beats its container.
     */
    at(sectionId, x, y) {
        let best = null;
        let bestArea = Infinity;

        for (const entry of this.entriesFor(sectionId)) {
            if (entry.kind === 'when') continue;

            for (const rect of this.rects(entry)) {
                if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) continue;

                const area = rect.width * rect.height;

                if (area < bestArea) {
                    best = entry;
                    bestArea = area;
                }
            }
        }

        return best;
    },

    itemAt(sectionId, x, y) {
        let best = null;
        let bestArea = Infinity;

        for (const item of this.maps[sectionId]?.items || []) {
            const rect = item.el.getBoundingClientRect();

            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) continue;

            const area = rect.width * rect.height;

            if (area < bestArea) {
                best = item;
                bestArea = area;
            }
        }

        return best;
    },

    /**
     * The toggle governing the point, when nothing more specific is there.
     *
     * `StudioFields.at()` deliberately never returns a `when` entry — a
     * toggle governs a whole container and would shadow every field inside
     * it. So a toggle is only offered where the pointer is over its
     * governed element but over no field and no repeater item, which is
     * exactly where the grey "set in code" state would otherwise paint.
     */
    toggleAt(target, sectionId) {
        const el = target?.closest?.('[data-sf-when]');

        if (!el) return null;

        const wrapper = el.closest('[data-section]');

        if (!wrapper || wrapper.dataset.section !== sectionId) return null;

        const raw = el.getAttribute('data-sf-when');
        const at = raw.lastIndexOf('@');

        return { key: raw.slice(0, at), line: Number(raw.slice(at + 1)) || 0, el, index: null, subKey: null, kind: 'when' };
    },

    refFor(sectionId) {
        return document.querySelector(`[data-section="${sectionId}"]`)?.dataset.ref || null;
    },

    /** The source file behind a section wrapper, for the provenance chip. */
    sourceFor(sectionId) {
        const ref = this.refFor(sectionId);

        return ref && this.paths[ref] ? this.paths[ref] : null;
    },

    /** The declared `{label, type}` for a field, or null when undeclared. */
    contractFor(sectionId, key) {
        const ref = this.refFor(sectionId);

        return (ref && this.contracts[ref]?.[key]) || null;
    },

    /**
     * The declared type for one entry — a repeater's own contract type
     * ("repeater") says nothing about a specific sub-field, so a repeater
     * entry (entry.index !== null) resolves through its parent's
     * `sub_fields` instead. Returns null when nothing is declared (an
     * undeclared field, or a sub-field the yml doesn't list), which
     * editabilityOf() treats as "no type to conflict with".
     */
    typeFor(sectionId, entry) {
        const contract = this.contractFor(sectionId, entry.key);

        if (!contract) return null;

        if (entry.index !== null) {
            return contract.sub_fields?.[entry.subKey]?.type || null;
        }

        return contract.type || null;
    },

    /**
     * Whether a Range's contents are a plain text host — no child element
     * — rather than markup (`{!! !!}` echoing an `<svg>` icon, say).
     * cloneContents() is cheap here: it runs once per field per paint
     * (from index()), never per hover/click.
     */
    isTextRange(range) {
        try {
            return !range.cloneContents().querySelector('*');
        } catch (e) {
            return true;
        }
    },

    debug() {
        const rows = [];

        Object.entries(this.maps).forEach(([sectionId, map]) => {
            map.entries.forEach((entry) => rows.push({
                section: sectionId,
                path: entry.path,
                kind: entry.kind + (entry.attribute ? ':' + entry.attribute : ''),
                line: entry.line,
                text: entry.kind === 'text' ? (entry.range?.toString() || '').slice(0, 40) : '',
            }));
            map.items.forEach((item) => rows.push({ section: sectionId, path: item.id, kind: 'item', line: '', text: '' }));
        });

        console.table(rows);

        return rows.length;
    },
};

const StudioPreview = {
    variables: {},
    refs: {},
    blocks: {},
    selectedId: null,
    // Three tiers: the section (today's behaviour), a repeater item, and a
    // single field. Esc walks up one tier at a time.
    selection: { tier: 'section', sectionId: null, path: null, key: null, index: null },
    // The last { source, line } highlightLine() painted — lets a Monaco
    // cursor move that only advances the column skip the rescan/scroll.
    lastHighlight: null,
    // Hover geometry is read (getClientRects/getBoundingClientRect) on every
    // mousemove, but the halo/chip are only ever written from a single
    // rAF-batched flush below — see queuePaint()/flushPaint() — so a flurry
    // of pointer events never forces more than one layout write per frame.
    pendingPaint: undefined,
    paintFrame: null,
    // The last pointer position hoverAt() saw, in viewport coordinates — so
    // scroll/resize (which move content under a pointer that never itself
    // moved) can re-resolve the hover without a fresh mousemove.
    lastPointer: null,
    renderUrl: null,
    csrf: null,
    // Per-section render state: an in-flight request, plus the newest
    // values that arrived while it was out (only the last one matters).
    renderInFlight: new Set(),
    renderQueued: new Set(),
    renderTimer: null,
    renderPending: new Set(),

    // 'preview' | 'edit' | 'code' — mirrors $store.studio.mode in the editor
    // window. The canvas document is rebuilt on every refresh, so the mode is
    // read straight from localStorage at boot rather than waited on.
    mode: 'edit',

    setMode(mode) {
        this.mode = mode;
        document.documentElement.classList.toggle('studio-preview', mode === 'preview');

        if (mode === 'preview') {
            this.closeMenu();
            this.clearSelection();
        }
    },

    init({ variables, bindings, refs, blocks, renderUrl, csrf, paths, contracts }) {
        this.variables = variables || {};
        // Per-section {field: 'collections.<name>'} — sent with every render
        // so bound repeaters keep reading the collection, not stale values
        this.bindings = bindings || {};
        this.refs = refs || {};
        this.blocks = blocks || {};
        this.renderUrl = renderUrl || null;
        this.csrf = csrf || null;

        // Dev-mode chrome (Edit-code buttons) follows the editor's toggle —
        // on by default, sticky once the user turns it off
        document.documentElement.classList.toggle(
            'studio-devmode',
            localStorage.getItem('studio.devmode') !== '0'
        );

        // Preview is the default, matching the editor window's own fallback.
        // Code mode hides the canvas, so as far as this document is concerned
        // it behaves exactly like Edit.
        const savedMode = localStorage.getItem('studio.mode');
        this.setMode(savedMode === 'preview' || savedMode === null ? 'preview' : 'edit');

        this.setupContextMenu();

        StudioFields.init(paths, contracts);

        this.cursor.mount();
        this.control.mount();

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) return;
            if (!event.data || typeof event.data.type !== 'string') return;

            const { type, ...data } = event.data;

            switch (type) {
                case 'studio:update-variable':
                    this.updateVariable(data.sectionId, data.key, data.value);
                    break;

                case 'studio:update-variables':
                    this.updateVariables(data.sectionId, data.variables);
                    break;

                case 'studio:select':
                    this.applySelection(data.sectionId, data.scroll !== false);
                    break;

                case 'studio:hover':
                    this.applyHover(data.sectionId, data.on);
                    break;

                case 'studio:deselect':
                    this.clearSelection();
                    break;

                case 'studio:element-select':
                    this.setElementSelect(!!data.on);
                    break;

                case 'studio:devmode':
                    document.documentElement.classList.toggle('studio-devmode', !!data.on);
                    break;

                case 'studio:highlight-field':
                    this.highlightLine(data.source, data.line);
                    break;

                case 'studio:mode':
                    // Code hides the canvas entirely; while it is on screen at
                    // all (the split) it stays selectable, like Edit.
                    this.setMode(data.mode === 'preview' ? 'preview' : 'edit');
                    break;

                case 'studio:menu-close':
                    this.closeMenu();
                    break;

                case 'studio:field-value':
                    this.applyIncomingValue(data);
                    break;
            }
        });

        // A link inside the canvas never navigates the iframe itself: in Edit
        // it does nothing, and in Preview it asks the editor to move to that
        // page (the editor is bound to one page, so the whole window goes).
        document.addEventListener('click', (event) => {
            const link = event.target.closest ? event.target.closest('a') : null;
            if (!link) return;

            event.preventDefault();

            if (this.mode !== 'preview') return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#')) return;

            let url;
            try {
                url = new URL(href, window.location.href);
            } catch (e) {
                return;
            }

            // Anything off-site opens where it belongs — a new tab
            if (url.origin !== window.location.origin) {
                window.open(url.href, '_blank', 'noopener');
                return;
            }

            this.post('studio:navigate', { path: url.pathname });
        }, true);

        document.addEventListener('submit', (event) => event.preventDefault(), true);

        document.addEventListener('mousemove', (event) => {
            this.cursor.track(event);
            this.hoverAt(event);
        }, { passive: true });

        document.addEventListener('mouseleave', () => this.clearHover());

        // Click on empty canvas space deselects (and closes the context menu).
        // A click inside the floating control (its input/select isn't a
        // section wrapper, so nothing upstream stopped it) must not count
        // as "empty canvas" — that would close the control the instant a
        // click focuses its own input.
        document.addEventListener('click', (event) => {
            if (this.mode === 'preview') return;
            if (this.control.el && this.control.el.contains(event.target)) return;

            this.closeMenu();
            this.clearSelection();
            this.post('studio:deselected');
        });

        // Forward keyboard shortcuts to the editor
        document.addEventListener('keydown', (event) => {
            // While the context menu is open, Escape only closes it
            if (event.key === 'Escape' && this.menu) {
                event.preventDefault();
                this.closeMenu();
                return;
            }

            // Then Escape walks up the selection tiers before the editor
            // ever sees it as "deselect the section".
            if (event.key === 'Escape' && this.walkUp()) {
                event.preventDefault();
                return;
            }

            const relevant = event.key === 'Escape'
                || event.key === 'Backspace'
                || event.key === 'Delete'
                // E opens the inspector for the selection (outside a field)
                || (!(event.metaKey || event.ctrlKey) && (event.key === 'e' || event.key === 'E') && !isTyping(document))
                || ((event.metaKey || event.ctrlKey) && ['b', 'B', 'd', 'D', 's', 'S', 'k', 'K', 'ArrowUp', 'ArrowDown'].includes(event.key));

            if (!relevant) return;

            if ((event.metaKey || event.ctrlKey) || event.key === 'Backspace' || event.key === 'Delete') {
                if (!isTyping(document)) event.preventDefault();
            }

            this.post('studio:key', {
                key: event.key,
                meta: event.metaKey || event.ctrlKey,
                typing: isTyping(document),
            });
        });

        // Drop an image file straight onto an image field. A file drag
        // must never be allowed to fall through to the browser's own
        // handling anywhere on the canvas — that navigates the iframe to
        // the dropped file — so both listeners swallow it unconditionally
        // the moment a file is being dragged, regardless of whether the
        // point under the pointer is a real image-field hit; only a real
        // hit gets the drop-target affordance / actually uploads.
        document.addEventListener('dragover', (event) => {
            if (this.mode === 'preview') return;
            if (!event.dataTransfer?.types?.includes('Files')) return;

            event.preventDefault();

            const hit = this.imageHitAt(event.target, event.clientX, event.clientY);
            this.markDropTarget(hit || null);
        });

        document.addEventListener('dragleave', () => this.markDropTarget(null));

        document.addEventListener('drop', (event) => {
            if (this.mode === 'preview') return;
            if (!event.dataTransfer?.types?.includes('Files')) return;

            event.preventDefault();

            const hit = this.imageHitAt(event.target, event.clientX, event.clientY);
            this.markDropTarget(null);

            if (!hit) return;

            const file = event.dataTransfer?.files?.[0];
            if (!file) return;

            this.post('studio:field-upload', {
                sectionId: hit.sectionId,
                key: hit.entry.key,
                index: hit.entry.index,
                subKey: hit.entry.subKey,
                file,
            });
        });
    },

    /**
     * The image-typed field entry (if any) under a point — mirrors
     * resolveHover()'s structure rather than scanning every section on
     * every tick: `dragover` fires continuously and unthrottled while the
     * pointer moves (no rAF coalescing here), so a brute-force
     * querySelectorAll+getBoundingClientRect over every section would
     * visibly jank on a page with many of them. Start from the DOM
     * ancestor section — a single bounded hit test — and only fall back to
     * the elements actually stacked at the point (still bounded: dedupe +
     * stop at first hit, same shape as tierAtPoint()) when that misses.
     */
    imageHitAt(target, x, y) {
        const wrapper = target?.closest?.('[data-section]');
        const nearId = wrapper?.dataset.section;

        if (nearId) {
            const hit = this.imageEntryAt(nearId, x, y);
            if (hit) return hit;
        }

        const tried = new Set([nearId]);

        for (const el of document.elementsFromPoint(x, y)) {
            const w = el.closest?.('[data-section]');
            if (!w) continue;

            const sectionId = w.dataset.section;
            if (tried.has(sectionId)) continue;
            tried.add(sectionId);

            const hit = this.imageEntryAt(sectionId, x, y);
            if (hit) return hit;
        }

        return null;
    },

    /** One section's image-field hit test, shared by imageHitAt()'s
     * near-ancestor check and its elementsFromPoint() fallback. */
    imageEntryAt(sectionId, x, y) {
        const entry = StudioFields.at(sectionId, x, y);

        if (!entry) return null;
        if (this.editabilityOf(entry, sectionId) === 'code') return null;
        if (!this.isImageField(entry, sectionId)) return null;

        return { sectionId, entry };
    },

    /** The one drop-target highlight, a class on the hit element itself —
     * not a second overlay writer alongside queuePaint()/flushPaint(). */
    markDropTarget(hit) {
        document.querySelectorAll('.studio-drop-target').forEach((el) => el.classList.remove('studio-drop-target'));

        const el = hit?.entry?.el;
        if (el) el.classList.add('studio-drop-target');
    },

    post(type, payload = {}) {
        window.parent.postMessage({ type, ...payload }, window.location.origin);
    },

    /**
     * The single decision point for how a 'field'-tier hit behaves —
     * inline editing inherited the field contract but not the binding
     * contract, so both are folded in here (plus the text-host check)
     * rather than scattered across select()/resolveHover(). Both call
     * sites must agree, so hover and click never disagree about a field.
     *
     * Returns:
     *   'code'   — bound in the section's own PHP/Blade (`php:`/`blade:`).
     *              Not a Studio-owned field at all: no select, no edit.
     *   'select' — selectable (inspector focus / "Edit in Content"), but
     *              never opens for inline typing: a collection-bound
     *              field (the value lives in Content, not on the page),
     *              a non-text-typed field (select/colorpicker/image/…),
     *              or a raw echo whose host isn't plain text (an <svg>
     *              icon, say — committing would read an empty innerText
     *              and wipe the field).
     *   'edit'   — genuinely inline-editable: no binding, or a `site.*`
     *              binding (EditorPanel::saveSiteValues persists those
     *              for real), a declared text/textarea type (or no
     *              declared type at all), and a plain text host.
     *
     * An undeclared echo (`kind: 'undeclared'` — a bare `{{ $x }}` with no
     * yml entry yet) is always 'code': there is no field to write to until
     * it is promoted, so this must never fall into 'select' or 'edit'
     * however the checks below would otherwise resolve it.
     */
    editabilityOf(entry, sectionId) {
        if (entry.kind === 'undeclared') return 'code';

        const binding = this.bindings[sectionId]?.[entry.key];

        if (binding) {
            if (binding.startsWith('php:') || binding.startsWith('blade:')) return 'code';
            if (binding.startsWith('collections.')) return 'select';
            // 'site.*' falls through — fully editable, not gated here.
        }

        if (entry.kind !== 'text') return 'select';

        const type = StudioFields.typeFor(sectionId, entry);

        if (type && type !== 'text' && type !== 'textarea') return 'select';

        if (entry.textHost === false) return 'select';

        return 'edit';
    },

    /**
     * Whether a field's value comes from a collection row rather than this
     * instance — `collections.*` bindings are read-only from the canvas
     * (the value lives in Content), so no action here may ever write one.
     * editabilityOf() already collapses this into its 'select' tier
     * alongside plain non-editable-type fields; this reads the same
     * `bindings` it does, for callers that need the finer answer.
     */
    isCollectionBound(sectionId, key) {
        return !!this.bindings[sectionId]?.[key]?.startsWith('collections.');
    },

    /**
     * Whether a field entry is image-typed and actionable from the canvas:
     * an `<img src>`/`<source srcset>` attribute, or a field whose declared
     * type is `image`. Excludes a collections-bound field — it still
     * selects (via editabilityOf's 'select' tier) but pick-media must
     * never fire for it.
     */
    isImageField(entry, sectionId) {
        if (this.isCollectionBound(sectionId, entry.key)) return false;
        if (entry.kind === 'attr' && (entry.attribute === 'src' || entry.attribute === 'srcset')) return true;

        return StudioFields.typeFor(sectionId, entry) === 'image';
    },

    /**
     * A non-text field asking the editor window to resolve a value.
     *
     * Text fields edit in place; every other type needs something the
     * canvas iframe cannot host — the media library, an upload, a colour
     * input. So the canvas posts the request, the editor resolves it, and
     * the value comes back through `studio:field-value`.
     */
    fieldAction(entry, sectionId, action) {
        if (this.mode === 'preview') return;
        if (this.editabilityOf(entry, sectionId) === 'code') return;

        this.post('studio:field-action', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            type: StudioFields.typeFor(sectionId, entry),
            action,
        });
    },

    /** The editor resolved a value for a non-text field. */
    applyIncomingValue({ sectionId, key, index, subKey, value }) {
        if (value === null || value === undefined) return;

        const entry = { key, index, subKey };

        for (const id of this.siblingIds(sectionId)) {
            this.applyFieldValue(id, entry, value);
            this.render(id);
        }
    },

    /**
     * A small floating control for field types that need a widget rather
     * than typing: a link's href, a select's options, a colour swatch.
     *
     * Deliberately its own element with its own lifecycle — the chip is
     * pointer-events:none by design (it must never block the hover it
     * describes), so an interactive control cannot live inside it.
     */
    control: {
        el: null,
        entry: null,
        sectionId: null,

        mount() { this.el = document.getElementById('studio-control'); },

        open(kind, entry, sectionId, box, options, { placement = 'above', focus = true } = {}) {
            if (!this.el) return;

            this.entry = entry;
            this.sectionId = sectionId;
            this.el.innerHTML = '';

            const commit = (value) => {
                StudioPreview.fieldValue(entry, sectionId, value);
                this.close();
            };

            if (kind === 'url') {
                const input = document.createElement('input');
                input.type = 'text';
                input.value = StudioPreview.currentValue(sectionId, entry) || '';
                input.placeholder = 'https://… or /path';
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); commit(input.value); }
                    if (e.key === 'Escape') { e.preventDefault(); this.close(); }
                });
                this.el.appendChild(input);
            }

            if (kind === 'select') {
                const select = document.createElement('select');
                for (const [value, label] of Object.entries(options || {})) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = label;
                    select.appendChild(opt);
                }
                select.value = StudioPreview.currentValue(sectionId, entry) || '';
                select.addEventListener('change', () => commit(select.value));
                this.el.appendChild(select);
            }

            if (kind === 'color') {
                const input = document.createElement('input');
                input.type = 'color';
                input.value = StudioPreview.currentValue(sectionId, entry) || '#000000';
                input.addEventListener('change', () => commit(input.value));
                this.el.appendChild(input);
            }

            // An undeclared echo (dev mode only) — no value to edit yet,
            // just a button asking the parent window to write the field
            // contract. The endpoint is dev-mode-gated server-side too.
            if (kind === 'add-field') {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = `Add "${entry.key}" as a field`;
                button.addEventListener('click', () => {
                    StudioPreview.post('studio:promote-field', { ref: StudioPreview.refs[sectionId], key: entry.key });
                    this.close();
                });
                this.el.appendChild(button);
            }

            this.el.classList.add('is-on');
            this.el.style.left = Math.max(8, box.left) + 'px';
            // Anchored to a link that's being edited in place, the popover
            // sits below the anchor instead of above it — above would cover
            // the very text the user is typing into.
            this.el.style.top = placement === 'below'
                ? (box.top + box.height + 8) + 'px'
                : Math.max(8, box.top - 38) + 'px';

            // A standalone open (the field itself was clicked) can safely
            // steal focus. Anchored to a live text edit it must not — the
            // caret is mid-edit in the host, and stealing focus here would
            // blur it (committing/ending the edit) before the user typed a
            // single character. The user reaches this input with a
            // deliberate click instead, which commits the text edit via the
            // normal blur path (see editBlur's relatedTarget check) without
            // tearing the popover down.
            if (focus) this.el.querySelector('input,select')?.focus();
        },

        close() {
            this.entry = null;
            this.sectionId = null;
            this.el?.classList.remove('is-on');
            if (this.el) this.el.innerHTML = '';
        },
    },

    /** The value the canvas currently holds for a field. */
    currentValue(sectionId, entry) {
        const vars = this.variables[sectionId] || {};

        if (entry.index === null) return vars[entry.key];

        return (vars[entry.key] || [])[entry.index]?.[entry.subKey];
    },

    /** Persist a resolved non-text value (shared by the control and Task 1). */
    fieldValue(entry, sectionId, value) {
        for (const id of this.siblingIds(sectionId)) {
            this.applyFieldValue(id, entry, value);
            this.render(id);
        }

        this.post('studio:field-committed', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            value,
            echo: true,
        });
    },

    /**
     * Switch a toggle off from the canvas — the only direction a canvas
     * click can take one. When a `when` block is false its governed
     * element isn't rendered at all, so there is nothing left on the
     * canvas to hover or click (toggleAt() finds no marker); turning one
     * back on stays an inspector action.
     *
     * Hiding a whole block on a single click reads as destructive, so it's
     * undoable via a toast — the same affordance section deletion uses
     * (`studio:toast`'s `{label, dispatch}` action), just resolved locally:
     * the value and the render call needed to restore it both live on
     * this side of the canvas already, so there's no server round trip to
     * wire up.
     */
    openToggle(entry, sectionId) {
        const on = String(this.currentValue(sectionId, entry) ?? '1') !== '0';

        // The canvas can only turn a toggle OFF: when it is false the
        // governed element is not rendered, so there is nothing to hover.
        // Turning one back on stays an inspector action.
        if (!on) return;

        this.fieldValue(entry, sectionId, '0');

        toast(`“${this.labelFor(entry, sectionId)}” turned off`, 'success', 3200, {
            label: 'Undo',
            onClick: () => this.fieldValue(entry, sectionId, '1'),
        });
    },

    /* --- selection ------------------------------------------------ */

    // `clickedSectionId` (the DOM ancestor of the click) is deliberately
    // NOT named `sectionId` here — every downstream consumer in the hit
    // branches below must key off the hit's OWN resolved section
    // (`hitSectionId`), never the ancestor a cross-section fallback may
    // have overridden, so a stale read is a visible rename mismatch
    // rather than a silent wrong-section bug.
    select(clickedSectionId, event) {
        if (event) event.stopPropagation();

        // The mouseup ending an item drag almost always lands off the
        // toolbar, so the browser's own click synthesis re-enters here on
        // whatever section wrapper is underneath — reopening a caret or
        // control the instant the item is dropped. One-shot: consumed by
        // the very next click, wherever it lands (see dragItemEnd()).
        if (this.suppressNextClick) {
            this.suppressNextClick = false;

            return;
        }

        // Preview mode has no selection — the chrome is hidden, and the
        // inline onclick handlers must not reach past it.
        if (this.mode === 'preview') return;

        // Every fresh click starts from a closed control — the branch
        // below reopens it when the new hit warrants one, so a click that
        // lands on a different field (or a text/image field, or the
        // section itself) never leaves a stale control from the last one.
        this.control.close();

        // The section the resolved hit actually belongs to — starts as
        // the clicked ancestor, but tierAtPoint()'s cross-section fallback
        // below can point it at a different section entirely. Read by the
        // trailing "just select the section" fallback too (outside the
        // `if (event)` block), so it's declared out here rather than
        // inside it.
        let hitSectionId = clickedSectionId;

        // A click resolves to the deepest tier under the pointer; only a
        // click on section chrome selects the section itself.
        if (event) {
            let hit = this.tierAt(clickedSectionId, event.clientX, event.clientY);

            // Mirror resolveHover()'s cross-section fallback: the DOM
            // ancestor's own box can come up empty while a *different*
            // section's field geometrically covers this same point, and a
            // click must never resolve more conservatively than the hover
            // that preceded it — editabilityOf() is consulted identically
            // on both paths, but only agrees when fed the same hit (a
            // destructive click, like turning a toggle off, must never
            // fire just because the click-side hit test gave up early).
            // tierAtPoint() reports which section it actually found the
            // hit in — every check and dispatch below must key off THAT
            // section, not the ancestor of the click, or the gate ends up
            // consulted against the wrong section's bindings/contract.
            if (hit.tier === 'section') {
                const fallback = this.tierAtPoint(event.clientX, event.clientY, clickedSectionId);

                if (fallback) {
                    hit = fallback;
                    hitSectionId = fallback.sectionId;
                }
            }

            if (hit.tier === 'field') {
                // ⌥-click jumps to the line of Blade that rendered this
                // value — a source lookup, not an edit, so it fires
                // regardless of how the field is bound.
                if (event.altKey && document.documentElement.classList.contains('studio-devmode')) {
                    const source = StudioFields.sourceFor(hitSectionId);

                    if (source) {
                        event.preventDefault();
                        this.post('studio:open-code-at', {
                            path: 'resources/designer/views/components/' + source + '.blade.php',
                            line: hit.entry.line,
                        });

                        return;
                    }
                }

                const editability = this.editabilityOf(hit.entry, hitSectionId);

                // Dev mode only: clicking an undeclared echo offers to
                // declare it as a field. Outside dev mode (or once the
                // control is dismissed) it falls through to the plain
                // section selection below, exactly like any other
                // code-owned content.
                if (hit.entry.kind === 'undeclared' && document.documentElement.classList.contains('studio-devmode')) {
                    this.applySelection(hitSectionId, false);
                    this.selectField(hit.entry, hitSectionId);
                    this.post('studio:section-selected', { sectionId: hitSectionId });

                    const box = StudioFields.box(hit.entry);
                    if (box) this.control.open('add-field', hit.entry, hitSectionId, box);

                    return;
                }

                // A php:/blade:-bound value is set in code — Studio never
                // owns it as a field, so a click here falls through to a
                // plain section selection, same as clicking any other
                // code-rendered content.
                if (editability !== 'code') {
                    this.applySelection(hitSectionId, false);
                    this.selectField(hit.entry, hitSectionId);
                    this.post('studio:section-selected', { sectionId: hitSectionId });

                    // Only a genuinely inline-editable field opens for
                    // typing — a collection-bound or non-text-typed field
                    // still selects (inspector focus / "Edit in Content")
                    // but never takes free text on the canvas.
                    if (editability === 'edit') {
                        const started = this.beginEdit(hit.entry, hitSectionId, this.isMultiline(hit.entry, hitSectionId), event.clientX, event.clientY);

                        // A link's own text always wins the hit test against
                        // its href attr entry (the smallest box wins, and the
                        // text nests inside the anchor) — so the href input
                        // opens here, anchored to the link, rather than
                        // depending on a click ever resolving to the href
                        // entry itself. Not focused: the caret is mid-edit in
                        // the text host, and stealing focus would blur (and
                        // so commit/end) the edit before it began.
                        if (started) {
                            const hrefEntry = StudioFields.hrefEntryFor(hitSectionId, this.editing.host);

                            if (hrefEntry
                                && this.editabilityOf(hrefEntry, hitSectionId) !== 'code'
                                && !this.isCollectionBound(hitSectionId, hrefEntry.key)
                            ) {
                                const hrefBox = StudioFields.box(hrefEntry);

                                if (hrefBox) {
                                    // The 'url' kind never reads an options
                                    // list (only 'select' does) — nothing to
                                    // pass here but the placement config.
                                    this.control.open(
                                        'url',
                                        hrefEntry,
                                        hitSectionId,
                                        hrefBox,
                                        undefined,
                                        { placement: 'below', focus: false }
                                    );
                                }
                            }
                        }
                    } else if (this.isImageField(hit.entry, hitSectionId)) {
                        this.fieldAction(hit.entry, hitSectionId, 'pick-media');
                    } else if (!this.isCollectionBound(hitSectionId, hit.entry.key)) {
                        // A collections.*-bound field only ever selects
                        // (inspector focus / "Edit in Content") — its value
                        // lives in Content, and EditorPanel::saveVariables()
                        // silently discards a canvas-side write to it, so
                        // the control must never open for one.
                        const type = (hit.entry.kind === 'attr' && hit.entry.attribute === 'href')
                            ? 'url'
                            : StudioFields.typeFor(hitSectionId, hit.entry);
                        const box = StudioFields.box(hit.entry);

                        if (box && (type === 'url' || type === 'select' || type === 'colorpicker')) {
                            this.control.open(
                                type === 'colorpicker' ? 'color' : type,
                                hit.entry,
                                hitSectionId,
                                box,
                                StudioFields.contractFor(hitSectionId, hit.entry.key)?.options
                            );
                        }
                    }

                    return;
                }
            } else if (hit.tier === 'item') {
                this.applySelection(hitSectionId, false);
                this.selectItem(hit.item, hitSectionId);
                this.post('studio:section-selected', { sectionId: hitSectionId });

                return;
            } else {
                // Nothing more specific was hit — the same position
                // resolveHover() offers the toggle halo/cursor at. Same gate
                // as the hover path: a php:/blade:-bound or collection-bound
                // toggle offers nothing, and the click falls through to a
                // plain section selection below, same as any other
                // code-rendered content. hit.tier is still 'section' here
                // (the fallback found nothing either), so hitSectionId is
                // exactly clickedSectionId — using it keeps this branch
                // consistent with the rest rather than reaching past it.
                const toggleEntry = StudioFields.toggleAt(event.target, hitSectionId);

                if (toggleEntry
                    && this.editabilityOf(toggleEntry, hitSectionId) !== 'code'
                    && !this.isCollectionBound(hitSectionId, toggleEntry.key)
                ) {
                    this.openToggle(toggleEntry, hitSectionId);
                }
            }
        }

        this.selection = { tier: 'section', sectionId: hitSectionId, path: null, key: null, index: null };
        this.applySelection(hitSectionId, false);
        this.post('studio:section-selected', { sectionId: hitSectionId });
    },

    applySelection(sectionId, scroll = true) {
        this.selectedId = sectionId;

        document.querySelectorAll('[data-section].is-selected').forEach((el) => {
            el.classList.remove('is-selected');
        });

        const el = document.querySelector(`[data-section="${sectionId}"]`);
        if (!el) return;

        el.classList.add('is-selected');

        if (scroll) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },

    applyHover(sectionId, on) {
        document.querySelectorAll('[data-section].is-hinted').forEach((el) => {
            el.classList.remove('is-hinted');
        });

        if (on) {
            document.querySelector(`[data-section="${sectionId}"]`)?.classList.add('is-hinted');
        }
    },

    clearSelection() {
        this.selectedId = null;
        document.querySelectorAll('[data-section].is-selected').forEach((el) => {
            el.classList.remove('is-selected');
        });

        // A deselect drops every tier — otherwise a stale field/item
        // selection (and its halo) could survive a mode switch or an
        // empty-canvas click, and Esc would then walk up from state that
        // no longer matches anything on screen.
        this.selection = { tier: 'section', sectionId: null, path: null, key: null, index: null };
        this.clearHover();
        this.control.close();
    },

    /* --- field + item tiers --------------------------------------- */

    /** The tier under a point: a field beats an item beats the section. */
    tierAt(sectionId, x, y) {
        const entry = StudioFields.at(sectionId, x, y);

        if (entry) return { tier: 'field', entry };

        const item = StudioFields.itemAt(sectionId, x, y);

        if (item) return { tier: 'item', item };

        return { tier: 'section' };
    },

    /** Paint the hover halo + chip for whatever is under the pointer. */
    hoverAt(event) {
        if (this.mode === 'preview') return this.clearHover();
        // A drag in progress owns the halo/chip write queue itself
        // (dragItemMove) — the normal hover resolution must stand down for
        // the duration, or the two would fight over the same overlay.
        if (this.itemDrag.active) return;

        this.lastPointer = { x: event.clientX, y: event.clientY };

        this.resolveHover(event.target, event.clientX, event.clientY);
    },

    /**
     * Re-resolve whatever is now under the last known pointer position.
     * Scroll and resize move content under a pointer that never itself
     * moved, so no `mousemove` fires to re-sync the fixed-position halo —
     * without this it stays glued to its last screen coordinates while the
     * field underneath it scrolls away. Re-resolving (rather than just
     * clearing) means a stationary pointer during a wheel-scroll keeps
     * tracking whatever field is now under it.
     */
    rehover() {
        if (this.mode === 'preview') return;
        if (!this.lastPointer) return;

        const { x, y } = this.lastPointer;
        const target = document.elementFromPoint(x, y);

        if (!target) return this.clearHover();

        this.resolveHover(target, x, y);
    },

    /** Shared by hoverAt() (from a real pointer event) and rehover() (from
     * scroll/resize, which have no target of their own — elementFromPoint
     * stands in for event.target). */
    resolveHover(target, x, y) {
        // The item toolbar/flanks are fixed elements outside any
        // [data-section] — without this they'd read as "off the canvas
        // content" the instant the pointer reaches them, clearing the very
        // controls being approached. Leave whatever was last painted alone;
        // their own click/mousedown handlers own what happens next.
        if (this.isOverItemControls(target)) return;

        const wrapper = target.closest?.('[data-section]');

        if (!wrapper) return this.clearHover();

        const sectionId = wrapper.dataset.section;
        let hit = this.tierAt(sectionId, x, y);

        // The section a resolved hit actually belongs to — the DOM
        // ancestor by default, but tierAtPoint() below can find one
        // elsewhere (e.g. an absolutely positioned image whose ancestor
        // section doesn't lay out over it). paintHalo() derives
        // itemControls.sectionId from whatever is passed here, so an item
        // resolved that way must anchor its toolbar to the section it was
        // actually found in, not the nearest ancestor's.
        let hitSectionId = sectionId;

        // The DOM-ancestor section is usually right, but its box can come
        // up empty while a *different* section's field geometrically
        // covers this same point. Before calling it code, check every
        // other section actually stacked at this point — still a read,
        // still bounded (dedupe + stop at first hit).
        if (hit.tier === 'section') {
            const fallback = this.tierAtPoint(x, y, sectionId);

            if (fallback) {
                hit = fallback;
                hitSectionId = fallback.sectionId;
            }
        }

        if (hit.tier === 'section') {
            // Nothing more specific is under the pointer — exactly where the
            // grey "set in code" halo would otherwise paint. Offer the
            // toggle governing this position instead, when there is one and
            // it isn't bound in code or to a collection row (editabilityOf/
            // isCollectionBound are the same gate select() consults, so
            // hover and click never disagree).
            const toggleEntry = StudioFields.toggleAt(target, sectionId);

            if (toggleEntry
                && this.editabilityOf(toggleEntry, sectionId) !== 'code'
                && !this.isCollectionBound(sectionId, toggleEntry.key)
            ) {
                return this.paintHalo({ tier: 'field', entry: toggleEntry }, 'field', { target, sectionId });
            }

            // Inside the rendered markup but on nothing Studio owns
            const inContent = !!target.closest?.('[data-section-content]');

            return inContent ? this.paintHalo(null, 'code', { target }) : this.clearHover();
        }

        // An undeclared echo gets its own dashed hint — but only in dev
        // mode, matching the click side (select()) and the constraint that
        // this affordance never appears outside it. Outside dev mode it
        // hovers exactly like any other code-owned content, below.
        if (hit.tier === 'field' && hit.entry.kind === 'undeclared') {
            const devMode = document.documentElement.classList.contains('studio-devmode');

            return this.paintHalo(hit, devMode ? 'undeclared' : 'code', { target, sectionId: hitSectionId });
        }

        // A php:/blade:-bound field hovers exactly like code-owned content
        // — the click and the hover must agree on this (editabilityOf is
        // the single source both consult).
        if (hit.tier === 'field' && this.editabilityOf(hit.entry, sectionId) === 'code') {
            return this.paintHalo(hit, 'code', { target, sectionId: hitSectionId });
        }

        this.paintHalo(hit, hit.tier, { target, sectionId: hitSectionId });
    },

    /**
     * Fallback for resolveHover()/select(): walk every element actually
     * stacked at (x, y) — elementsFromPoint(), the plural, returns the
     * full z-order — and try tierAt() for each distinct [data-section]
     * among them, in front-to-back order, skipping the section already
     * tried. Keeps the first hit, plus the section it belongs to (needed
     * so paintHalo()'s itemControls anchor to the section that actually
     * owns the item, not whichever one the caller started from — see
     * resolveHover()). Section-scoping itself stays (it protects against
     * occluded entries from an off-screen section whose rects still lay
     * out over the hero, e.g. a collapsed nav menu) — this only widens the
     * search to sections genuinely present at the point when the nearest
     * ancestor's own box misses.
     */
    tierAtPoint(x, y, skipSectionId) {
        const stack = document.elementsFromPoint(x, y);
        const tried = new Set([skipSectionId]);

        for (const el of stack) {
            const wrapper = el.closest?.('[data-section]');

            if (!wrapper) continue;

            const sectionId = wrapper.dataset.section;

            if (tried.has(sectionId)) continue;
            tried.add(sectionId);

            const hit = this.tierAt(sectionId, x, y);

            if (hit.tier !== 'section') return { ...hit, sectionId };
        }

        return null;
    },

    /**
     * Resolve the halo/chip geometry for whatever is under the pointer.
     * Everything here is a READ (StudioFields.box()/getBoundingClientRect
     * touch layout) — nothing here writes to the DOM. The result is handed
     * to queuePaint(), which is the only place that ever assigns style or
     * className, batched into a single requestAnimationFrame. This keeps
     * mousemove — read layout, read layout, ... — from ever being
     * interleaved with a write that would force a synchronous reflow.
     *
     * `event.sectionId` — when the caller passed one — is the section the
     * hit actually belongs to and is authoritative: resolveHover()/select()
     * can resolve a hit in a section other than the DOM ancestor of the
     * pointer/click (tierAtPoint()'s cross-section fallback), and
     * itemControls.sectionId (the item toolbar's anchor) must follow that,
     * not fall back to re-deriving the DOM ancestor from `event.target`.
     */
    paintHalo(hit, kind, event) {
        let box = null;
        let label = 'Set in code';
        let source = '';
        let sectionId = null;

        if (kind === 'field') {
            sectionId = event.sectionId;
            box = StudioFields.box(hit.entry);
            label = this.labelFor(hit.entry, sectionId);
            const path = StudioFields.sourceFor(sectionId);
            source = path ? path.split('/').pop() + ':' + hit.entry.line : '';
        } else if (kind === 'item') {
            sectionId = event.sectionId;
            const rect = hit.item.el.getBoundingClientRect();
            box = { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
            label = this.itemLabel(hit.item);
        } else if (kind === 'code' && hit?.entry) {
            // A code-bound field routed here by resolveHover()/select() —
            // halo its own precise box (not the event target's, which can
            // be a whole paragraph around several fields), keep the default
            // "Set in code" label.
            box = StudioFields.box(hit.entry);
        } else if (kind === 'undeclared') {
            sectionId = event.sectionId;
            box = StudioFields.box(hit.entry);
            label = `Add "${hit.entry.key}" as a field`;
        } else {
            const rect = event.target.getBoundingClientRect?.();
            if (rect) box = { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
        }

        if (!box || box.width === 0) return this.clearHover();

        // The item toolbar/flanks ride the same rAF write queue as the halo
        // — one read phase (here), one write phase (flushPaint) — so an
        // animated section (Pilot's marquee) never has its controls pinned
        // to a rect from a stale frame. Only a genuine, non-suppressed item
        // hover carries this; every other kind leaves it undefined, which
        // flushPaint() treats as "hide".
        const itemControls = (kind === 'item' && sectionId && this.itemControlsAllowed(hit.item, sectionId))
            ? { sectionId, key: hit.item.key, index: hit.item.index, box }
            : null;

        this.queuePaint({ kind, box, label, source, cursorKind: this.cursorKind(hit || {}, kind, sectionId), itemControls });
    },

    /**
     * Queue a halo/chip write for the next animation frame. At most one
     * frame is ever pending: a burst of mousemove events during that frame
     * just replaces `pendingPaint` with the latest geometry, so the DOM is
     * touched once per frame no matter how fast the pointer moves.
     */
    queuePaint(job) {
        this.pendingPaint = job;

        if (this.paintFrame) return;

        this.paintFrame = requestAnimationFrame(() => {
            this.paintFrame = null;
            this.flushPaint();
        });
    },

    /** The single place that writes halo/chip/cursor style, class and text. */
    flushPaint() {
        const halo = document.getElementById('studio-fhalo');
        const chip = document.getElementById('studio-fchip');

        if (!halo || !chip) return;

        const job = this.pendingPaint;
        this.pendingPaint = undefined;

        if (!job) {
            halo.classList.remove('is-on');
            chip.classList.remove('is-on');
            this.cursor.hide();
            this.paintItemControls(null);
            return;
        }

        const { kind, box, label, source, cursorKind, itemControls } = job;

        this.paintItemControls(itemControls || null);

        halo.className = 'studio-fhalo is-on' + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : kind === 'undeclared' ? ' is-undeclared' : '');
        halo.style.left = box.left + 'px';
        halo.style.top = box.top + 'px';
        halo.style.width = box.width + 'px';
        halo.style.height = box.height + 'px';

        chip.className = 'studio-fchip is-on' + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : kind === 'undeclared' ? ' is-undeclared' : '');
        chip.innerHTML = '';
        chip.appendChild(document.createTextNode(label));

        if (source && document.documentElement.classList.contains('studio-devmode')) {
            const span = document.createElement('span');
            span.className = 'studio-fchip-src';
            span.textContent = source;
            chip.appendChild(span);
        }

        chip.style.left = box.left + 'px';
        chip.style.top = Math.max(0, box.top - 18) + 'px';

        // A code-triggered highlight has no pointer position to show a type
        // cursor at (cursorKind is null), so it's a genuine skip — leave
        // whatever the badge was already doing alone. A real hover/select
        // job always supplies a truthy kind, so this never affects those.
        if (cursorKind) {
            this.cursor.show(cursorKind);
        }
    },

    clearHover() {
        this.queuePaint(null);
    },

    /* --- repeater item controls (add/reorder/delete) ---------------- */

    // The {sectionId, key, index} the flanking + buttons and toolbar are
    // currently anchored to — set only by paintItemControls() below, read
    // by itemAction() when a button is actually clicked. null whenever
    // nothing is showing (preview mode, a code/collection-bound repeater,
    // or the two-@foreach case — see itemControlsAllowed()).
    itemControlsInfo: null,

    // In-progress pointer drag state; see startItemDrag().
    itemDrag: { active: false, sectionId: null, key: null, fromIndex: null, overIndex: null, overBefore: false },

    // Set by dragItemEnd() when a real drag just ended — consumed once by
    // select() to swallow the phantom click a drop's mouseup synthesizes
    // on whatever's underneath it (see dragItemEnd()).
    suppressNextClick: false,

    /**
     * Whether an item may offer add/reorder/delete at all. Three
     * independent reasons to say no, all previously-fixed bugs:
     *  - preview mode is fully inert (belt-and-suspenders — hoverAt()
     *    already bails before this is ever reached)
     *  - a `collections.*`-bound repeater's rows live in the Content panel,
     *    not this instance — EditorPanel::saveVariables() silently drops a
     *    write to a bound key, so offering the controls would look like it
     *    worked and do nothing
     *  - a `php:`/`blade:`-bound repeater is set in code, same as any other
     *    code-owned field
     * checked against the repeater KEY directly (not a specific entry —
     * an item may hold several sub-fields, and the binding governs the
     * whole array, not one of them).
     */
    itemControlsAllowed(item, sectionId) {
        if (this.mode === 'preview') return false;
        if (this.isSectionSpanningItem(sectionId, item)) return false;
        if (this.isCollectionBound(sectionId, item.key)) return false;

        const binding = this.bindings[sectionId]?.[item.key];

        if (binding && (binding.startsWith('php:') || binding.startsWith('blade:'))) return false;

        return true;
    },

    /**
     * Pilot's `logos` marquee renders the same repeater in two `@foreach`
     * loops (a visual duplicate for the scrolling effect), so entries with
     * the same `key.index` exist in both copies. groupItems()'s
     * commonAncestor() then has to walk up through both copies to find one
     * element containing both — which can reach all the way to the section
     * wrapper itself. An item whose box IS the section would show a toolbar
     * that covers the whole section and, worse, a working "delete" that
     * reads as "delete this section" — so controls are suppressed whenever
     * the item's element carries the section's own `data-section` (i.e. the
     * common ancestor climbed past every real container).
     */
    isSectionSpanningItem(sectionId, item) {
        return !!(item.el && item.el.dataset && item.el.dataset.section === sectionId);
    },

    /** Fixed-position elements, moved/shown from the same rAF write phase
     * as the halo/chip (see paintHalo()'s itemControls + flushPaint()) —
     * but their own visibility is independent of the halo's: hovering the
     * buttons themselves must not hide them (see resolveHover()'s guard),
     * where hovering off the halo's own box normally would. */
    paintItemControls(info) {
        const before = document.getElementById('studio-item-before');
        const after = document.getElementById('studio-item-after');
        const toolbar = document.getElementById('studio-item-toolbar');

        if (!before || !after || !toolbar) return;

        if (!info) {
            before.classList.remove('is-on');
            after.classList.remove('is-on');
            toolbar.classList.remove('is-on');
            this.itemControlsInfo = null;

            return;
        }

        this.itemControlsInfo = info;

        const { box } = info;
        const midX = box.left + box.width / 2;

        before.style.left = midX + 'px';
        before.style.top = box.top + 'px';
        before.classList.add('is-on');

        after.style.left = midX + 'px';
        after.style.top = (box.top + box.height) + 'px';
        after.classList.add('is-on');

        toolbar.style.left = (box.left + box.width) + 'px';
        toolbar.style.top = box.top + 'px';
        toolbar.classList.add('is-on');
    },

    /** target is over one of the item control elements — themselves fixed
     * elements outside any [data-section], so resolveHover() would
     * otherwise read them as "off the canvas content" and clear the very
     * controls being hovered/clicked. */
    isOverItemControls(target) {
        return !!(target?.closest
            && (target.closest('#studio-item-before') || target.closest('#studio-item-after') || target.closest('#studio-item-toolbar')));
    },

    /** add-before / add-after / remove, from the flanking buttons/toolbar. */
    itemAction(action, event) {
        if (event) event.stopPropagation();

        const info = this.itemControlsInfo;
        if (!info || this.mode === 'preview') return;

        this.post('studio:item-action', { sectionId: info.sectionId, key: info.key, index: info.index, action });

        // The item this was anchored to is about to move/disappear under a
        // re-render — hide rather than leave a stale box on screen.
        this.queuePaint(null);
    },

    /** Every item belonging to the same repeater (sectionId + key) — the
     * pool a drag is allowed to land in. Section-spanning duplicates (the
     * two-@foreach case) are excluded, same as itemControlsAllowed(). */
    itemsForKey(sectionId, key) {
        return (StudioFields.maps[sectionId]?.items || [])
            .filter((item) => item.key === key && !this.isSectionSpanningItem(sectionId, item));
    },

    /** The same-repeater item under a point, smallest box wins (mirrors
     * StudioFields.itemAt(), scoped to one field). */
    itemAtForKey(sectionId, key, x, y) {
        let best = null;
        let bestArea = Infinity;

        for (const item of this.itemsForKey(sectionId, key)) {
            const rect = item.el.getBoundingClientRect();

            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) continue;

            const area = rect.width * rect.height;

            if (area < bestArea) {
                best = item;
                bestArea = area;
            }
        }

        return best;
    },

    /**
     * Drag-to-reorder from the toolbar's handle. SortableJS (the helper
     * `window.Studio.sortable()` wraps for the sidebar's own lists) assumes
     * every draggable is a direct DOM child of one shared container —
     * true for a flat sidebar list, not for a repeater's items, whose
     * markup shape is whatever the section's own Blade wrote (a grid, a
     * table, items separated by other markup). So this drags by geometry
     * instead: track the pointer, find which sibling item it's over
     * (itemAtForKey — never cached across frames, so an animated section
     * stays correct), halo that item as the drop target, and commit a
     * single moveRepeaterItem() on release.
     */
    startItemDrag(event) {
        event.preventDefault();
        event.stopPropagation();

        const info = this.itemControlsInfo;
        if (!info || this.mode === 'preview') return;

        // Handlers are stored on the drag itself (rather than as bare
        // closures) so a mouseleave — the pointer released outside the
        // iframe, which never delivers this document a mouseup — can tear
        // the same listeners down through the one shared teardown path
        // instead of leaking them.
        const onMove = (e) => this.dragItemMove(e);
        const onUp = () => this.dragItemEnd(true);
        const onCancel = () => this.dragItemEnd(false);

        this.itemDrag = {
            active: true,
            sectionId: info.sectionId,
            key: info.key,
            fromIndex: info.index,
            overIndex: info.index,
            overBefore: false,
            onMove,
            onUp,
            onCancel,
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        document.addEventListener('mouseleave', onCancel);
    },

    dragItemMove(event) {
        const drag = this.itemDrag;
        if (!drag.active) return;

        // A mode switch mid-drag (⌘K to the palette, say) must not let a
        // drag that started in Edit still commit once Preview is inert.
        if (this.mode === 'preview') return this.dragItemEnd(false);

        const target = this.itemAtForKey(drag.sectionId, drag.key, event.clientX, event.clientY);

        if (!target) return;

        const rect = target.el.getBoundingClientRect();

        drag.overIndex = target.index;
        drag.overBefore = event.clientY < rect.top + rect.height / 2;

        // Halo the prospective drop target through the normal write queue —
        // a plain read (getBoundingClientRect) feeding the one write phase,
        // same as every other hover.
        this.queuePaint({
            kind: 'item',
            box: { left: rect.left, top: rect.top, width: rect.width, height: rect.height },
            label: this.itemLabel(target),
            source: '',
            cursorKind: 'item',
            itemControls: null,
        });
    },

    dragItemEnd(commit) {
        const drag = this.itemDrag;
        if (!drag.active) return;

        document.removeEventListener('mousemove', drag.onMove);
        document.removeEventListener('mouseup', drag.onUp);
        document.removeEventListener('mouseleave', drag.onCancel);

        this.itemDrag = { active: false, sectionId: null, key: null, fromIndex: null, overIndex: null, overBefore: false };

        // Only a genuine drop (mouseup, commit === true) synthesizes a
        // phantom click on whatever's underneath afterward — a cancelled
        // drag (mouseleave, or a mode switch mid-drag) never produces one,
        // so arming the flag there would leave it lingering with nothing
        // to consume it, silently swallowing the user's next unrelated
        // click. select() must not treat the drop's own phantom click as
        // real (it would open a caret or control the instant the item is
        // dropped).
        if (commit) {
            this.suppressNextClick = true;
        }

        this.queuePaint(null);

        if (!commit || drag.overIndex === null) return;

        // Splice-target arithmetic: dropping "before" a later item lands at
        // that item's own index; "after" lands one past it; either way,
        // removing the dragged item first shifts every later index down by
        // one, so a target past the source needs that correction.
        let to = drag.overBefore ? drag.overIndex : drag.overIndex + 1;
        if (drag.fromIndex < to) to -= 1;

        if (to === drag.fromIndex) return;

        this.post('studio:item-action', {
            sectionId: drag.sectionId,
            key: drag.key,
            index: drag.fromIndex,
            toIndex: to,
            action: 'move',
        });
    },

    /**
     * The oversized type cursor: one badge that follows the pointer and
     * names what is under it. The native cursor is kept — an I-beam over
     * text is correct while editing — so this reads as a type indicator
     * rather than a cursor replacement.
     *
     * show()/hide() are writes (innerHTML, className) and are only ever
     * called from flushPaint() — the same single rAF-batched write phase
     * that owns the halo/chip — so the badge never touches the DOM more
     * than once per frame, and stays in lockstep with whatever the halo/
     * chip are showing (including a scroll-driven rehover() re-resolve,
     * which flows through the same paintHalo -> queuePaint -> flushPaint
     * path). track() is the one exception: it only ever writes a single
     * `transform`, coalesced through its own rAF at pointer frequency, so
     * merging it into queuePaint would gain nothing.
     */
    cursor: {
        el: null,
        kind: null,
        x: 0,
        y: 0,
        queued: false,

        glyphs: {
            text: '<span>T</span>',
            image: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h9A2.5 2.5 0 0 1 17 5.5v9a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 3 14.5v-9Zm3 1.25a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm8.5 7.75-3.6-4.5-2.6 3.1-1.4-1.6L5 15h9.5Z"/></svg>',
            url: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4h6a1 1 0 0 1 0 2H8.9l6.8 6.8a1 1 0 0 1-1.4 1.4L7.5 7.4v3.6a1 1 0 1 1-2 0V5a1 1 0 0 1 1-1Z"/></svg>',
            select: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.2 7.7a1 1 0 0 1 1.4 0L10 11.1l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 9.1a1 1 0 0 1 0-1.4Z"/></svg>',
            color: '<svg viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="6"/></svg>',
            item: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 4.5h13v3h-13v-3Zm0 4.75h13v3h-13v-3Zm0 4.75h13v3h-13v-3Z"/></svg>',
            code: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M7.6 5.2a1 1 0 0 1 .2 1.4L5.25 10l2.55 3.4a1 1 0 1 1-1.6 1.2l-3-4a1 1 0 0 1 0-1.2l3-4a1 1 0 0 1 1.4-.2Zm4.8 0a1 1 0 0 1 1.4.2l3 4a1 1 0 0 1 0 1.2l-3 4a1 1 0 1 1-1.6-1.2L14.75 10 12.2 6.6a1 1 0 0 1 .2-1.4Z"/></svg>',
            toggle: '<svg viewBox="0 0 20 20" fill="currentColor"><rect x="2" y="7" width="16" height="6" rx="3" opacity="0.35"/><circle cx="13" cy="10" r="4"/></svg>',
        },

        mount() {
            this.el = document.getElementById('studio-cursor');
        },

        show(kind) {
            if (!this.el) return;

            if (kind !== this.kind) {
                this.kind = kind;
                this.el.innerHTML = this.glyphs[kind] || this.glyphs.text;
                this.el.className = 'studio-cursor is-on'
                    + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : kind === 'toggle' ? ' is-toggle' : '');
            }

            this.el.classList.add('is-on');
        },

        hide() {
            this.kind = null;
            this.el?.classList.remove('is-on');
        },

        track(event) {
            this.x = event.clientX;
            this.y = event.clientY;

            if (this.queued || !this.el) return;

            this.queued = true;
            requestAnimationFrame(() => {
                this.queued = false;
                this.el.style.transform = `translate3d(${this.x}px, ${this.y}px, 0) scale(1)`;
            });
        },
    },

    /**
     * Which cursor glyph a hovered field deserves — named after what a
     * click will actually do, not just "text" for everything else.
     *
     * `sectionId` is passed in explicitly rather than read off
     * `this.selection.sectionId`: this is called from paintHalo() (the
     * read phase, driven by mousemove), so `selection` still names
     * whatever was last *selected*, not what's under the pointer right
     * now — reading it here would show the wrong glyph whenever the
     * hover and the selection are on different sections.
     */
    cursorKind(hit, kind, sectionId) {
        if (kind === 'item') return 'item';
        if (kind === 'code' || kind === 'undeclared') return 'code';

        const entry = hit.entry;

        if (entry.kind === 'when') return 'toggle';

        if (entry.kind === 'attr') {
            if (entry.attribute === 'src' || entry.attribute === 'srcset') return 'image';
            if (entry.attribute === 'href') return 'url';
        }

        const type = StudioFields.typeFor(sectionId, entry);

        if (type === 'image') return 'image';
        if (type === 'url') return 'url';
        if (type === 'select') return 'select';
        if (type === 'colorpicker') return 'color';

        return 'text';
    },

    /**
     * The chip's wording. The author's own yml label wins — it is what the
     * inspector shows, so the canvas and the panel name the same thing the
     * same way. A repeater sub-field and an undeclared key fall back to a
     * humanised key.
     */
    labelFor(entry, sectionId) {
        if (entry.index === null) {
            const contract = StudioFields.contractFor(sectionId, entry.key);

            if (contract?.label) return contract.label;
        }

        const key = entry.subKey || entry.key;
        const words = key.replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/[_-]+/g, ' ');

        return words.charAt(0).toUpperCase() + words.slice(1);
    },

    itemLabel(item) {
        const singular = item.key.replace(/ies$/, 'y').replace(/s$/, '');

        return singular.charAt(0).toUpperCase() + singular.slice(1);
    },

    selectField(entry, sectionId) {
        this.selection = {
            tier: 'field',
            sectionId,
            path: entry.path,
            key: entry.key,
            index: entry.index,
        };

        this.post('studio:field-selected', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            path: entry.path,
            line: entry.line,
            source: StudioFields.sourceFor(sectionId),
            label: this.labelFor(entry, sectionId),
        });
    },

    selectItem(item, sectionId) {
        this.selection = { tier: 'item', sectionId, path: item.id, key: item.key, index: item.index };
    },

    /**
     * The caret moved onto a line of a section file — halo whatever that
     * line renders, so the code and the page point at each other.
     *
     * This runs on every Monaco cursor move, so it stays cheap: the caller
     * (home.blade.php) already bails before posting unless the active file
     * matches the section-source path regex. Typing only advances the
     * column on the same line, so the (source, line) pair is memoised —
     * an unchanged pair bails before any DOM work at all, which is also
     * what makes leaving and returning to a file "just work": the source
     * (or the line within it) differs from what was last recorded, so the
     * memo never masks a real move. Once past that guard, each iteration
     * is just a dataset read + one string compare against
     * StudioFields.paths (no DOM re-query per section — see sourceFor())
     * until a section's source matches; only then is that one section's
     * entries array scanned. Nothing here re-scans every section's fields.
     */
    highlightLine(source, line) {
        if (this.mode === 'preview') return;

        if (this.lastHighlight && this.lastHighlight.source === source && this.lastHighlight.line === line) {
            return;
        }

        this.lastHighlight = { source, line };

        for (const wrapper of document.querySelectorAll('[data-section]')) {
            const ref = wrapper.dataset.ref;

            if (!ref || StudioFields.paths[ref] !== source) continue;

            const sectionId = wrapper.dataset.section;
            const entry = StudioFields.entriesFor(sectionId).find((candidate) => candidate.line === line);

            if (!entry) continue;

            const box = StudioFields.box(entry);

            if (!box) continue;

            this.queuePaint({
                kind: 'field',
                box,
                label: this.labelFor(entry, sectionId),
                source: '',
                // No pointer position stands behind a code-triggered
                // highlight, so there is no type-cursor kind to show.
                cursorKind: null,
            });

            // Only pull the canvas back into view when the target field
            // itself isn't already usably visible — a developer who
            // scrolled elsewhere on the canvas shouldn't get yanked back
            // just because the caret moved to a line whose *section* (often
            // taller than the viewport, e.g. a hero) still has some edge
            // onscreen. Tested against the field's own box, not the section
            // wrapper, and a sliver at the very edge doesn't count.
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            const visibleHeight = Math.min(box.top + box.height, viewportHeight) - Math.max(box.top, 0);
            const threshold = Math.min(40, box.height);
            const inView = visibleHeight >= threshold;

            if (!inView) {
                // Scroll to the field's own box, not the section wrapper: a
                // section taller than the viewport (a hero, say) can have a
                // field near either end that scrollIntoView on the wrapper
                // would still leave off-screen. box.top is already viewport-
                // relative, and text entries are Range-based with no element
                // of their own, so this works for every entry kind alike.
                const target = window.scrollY + box.top - (window.innerHeight / 2) + (box.height / 2);
                window.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
            }

            return;
        }
    },

    /* --- inline text editing -------------------------------------- */

    // The section whose markup must not be repainted: its DOM is the truth
    // while the caret is in it.
    editing: null,

    // sectionId → markup: a render that landed while that section was being
    // edited, deferred by paint() and applied by teardownEdit() once
    // editing ends.
    pendingMarkup: {},

    /**
     * Make a text field editable in place.
     *
     * When the sentinel range is the whole content of its parent (the common
     * case, `<p>{{ $body }}</p>`) the parent becomes editable directly.
     * Otherwise the range is wrapped in a transient span — safe, because it
     * exists only while the caret is in it and is unwrapped on exit.
     */
    beginEdit(entry, sectionId, multiline, x, y) {
        if (this.editing) this.commitEdit();

        if (entry.kind !== 'text' || !entry.range) return false;

        const parent = entry.range.commonAncestorContainer.nodeType === 1
            ? entry.range.commonAncestorContainer
            : entry.range.commonAncestorContainer.parentElement;

        if (!parent) return false;

        let host = parent;
        let wrapper = null;

        // Does the range already cover everything the parent contains?
        const whole = document.createRange();
        whole.selectNodeContents(parent);

        const sameStart = whole.compareBoundaryPoints(Range.START_TO_START, entry.range) === 0;
        const sameEnd = whole.compareBoundaryPoints(Range.END_TO_END, entry.range) === 0;

        if (!sameStart || !sameEnd) {
            wrapper = document.createElement('span');
            wrapper.setAttribute('data-sf-edit', '');

            try {
                entry.range.surroundContents(wrapper);
            } catch (e) {
                return false;   // the range crosses an element boundary
            }

            host = wrapper;
        }

        host.setAttribute('contenteditable', this.plaintextMode());
        host.focus();

        // Put the caret where the user clicked rather than selecting all
        let caret = null;

        if (typeof x === 'number' && typeof y === 'number') {
            if (document.caretRangeFromPoint) {
                caret = document.caretRangeFromPoint(x, y);            // Chrome/Safari
            } else if (document.caretPositionFromPoint) {
                const pos = document.caretPositionFromPoint(x, y);     // Firefox

                if (pos) {
                    caret = document.createRange();
                    caret.setStart(pos.offsetNode, pos.offset);
                }
            }
        }

        // The point can resolve to nothing, or to a node outside this host
        // (a neighbouring field sharing the same line) — collapsing to the
        // end is the safe fallback: worst case the caret lands in the wrong
        // spot, never that the field's contents get selected and replaced.
        if (!caret || !host.contains(caret.startContainer)) {
            caret = document.createRange();
            caret.selectNodeContents(host);
            caret.collapse(false);
        } else {
            caret.collapse(true);
        }

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(caret);

        this.editing = {
            sectionId,
            entry,
            host,
            wrapper,
            multiline,
            // Two copies, deliberately not one: `originalRaw` is the literal
            // pre-edit DOM text, restored verbatim by cancelEdit() so Escape
            // is a true no-op even when the real content has a leading
            // space, an nbsp, or 3+ blank lines. `original` is normalised
            // the same way commitEdit() reads the final value back, so
            // that comparison stays like-for-like instead of flagging a
            // mere click-and-blur as a change.
            originalRaw: host.innerText,
            original: this.readValue(host),
        };

        document.documentElement.classList.add('studio-editing');

        host.addEventListener('keydown', this.editKeydown);
        host.addEventListener('paste', this.editPaste);
        host.addEventListener('blur', this.editBlur);

        return true;
    },

    /** `plaintext-only` where supported; plain contenteditable plus a paste
     *  handler everywhere else. */
    plaintextMode() {
        if (this._plaintext === undefined) {
            const probe = document.createElement('div');
            probe.setAttribute('contenteditable', 'plaintext-only');
            this._plaintext = probe.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true';
        }

        return this._plaintext;
    },

    editKeydown(event) {
        const state = StudioPreview.editing;

        if (!state) return;

        if (event.key === 'Enter' && !state.multiline) {
            event.preventDefault();
            StudioPreview.commitEdit();

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            StudioPreview.cancelEdit();
        }
    },

    editPaste(event) {
        // Only needed on the fallback path — plaintext-only handles itself
        if (StudioPreview.plaintextMode() === 'plaintext-only') return;

        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    },

    editBlur(event) {
        // Moving focus into the link popover's own href input (a deliberate
        // click there — it's never auto-focused while anchored) blurs the
        // host like any other blur, so the text still commits normally.
        // But this blur must not tear the popover down with it: that would
        // destroy the very input the click just landed in, before the user
        // typed anything into it.
        const intoControl = !!(event?.relatedTarget && StudioPreview.control.el?.contains(event.relatedTarget));

        StudioPreview.commitEdit(intoControl);
    },

    /** Read the value back out of the DOM and hand it to the editor. */
    commitEdit(keepControl = false) {
        const state = this.editing;

        if (!state) return;

        this.editing = null;

        const value = this.readValue(state.host);

        this.teardownEdit(state, keepControl);

        if (value === state.original) return;

        const { entry, sectionId } = state;

        // Keep the client's copy current so a later re-render is correct
        this.applyFieldValue(sectionId, entry, value);

        // A global block's other placements on this page share the same
        // data but don't hear about this edit any other way — the whole
        // point of setFieldFromCanvas() is that it does NOT dispatch back
        // to the iframe. Re-render every sibling except the one just
        // edited: its DOM already shows the truth, and repainting it is
        // exactly the caret-destroying write this feature exists to avoid.
        for (const siblingId of this.siblingIds(sectionId)) {
            if (siblingId === sectionId) continue;

            this.applyFieldValue(siblingId, entry, value);
            this.render(siblingId);
        }

        this.post('studio:field-committed', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            value,
        });
    },

    /**
     * Write a committed value into the client-side copy for one section —
     * shared by the edited section itself and, for a global block, every
     * other placement that needs re-rendering to catch up.
     */
    applyFieldValue(sectionId, entry, value) {
        this.variables[sectionId] = this.variables[sectionId] || {};

        if (entry.index === null) {
            this.variables[sectionId][entry.key] = value;

            return;
        }

        const items = this.variables[sectionId][entry.key];

        if (Array.isArray(items) && items[entry.index]) {
            items[entry.index][entry.subKey] = value;
        }
    },

    cancelEdit() {
        const state = this.editing;

        if (!state) return;

        this.editing = null;
        state.host.innerText = state.originalRaw;
        state.host.blur();
        this.teardownEdit(state);
    },

    teardownEdit(state, keepControl = false) {
        state.host.removeEventListener('keydown', this.editKeydown);
        state.host.removeEventListener('paste', this.editPaste);
        state.host.removeEventListener('blur', this.editBlur);
        state.host.removeAttribute('contenteditable');

        if (state.wrapper && state.wrapper.parentNode) {
            const parent = state.wrapper.parentNode;
            while (state.wrapper.firstChild) parent.insertBefore(state.wrapper.firstChild, state.wrapper);
            parent.removeChild(state.wrapper);
            parent.normalize();
        }

        document.documentElement.classList.remove('studio-editing');
        window.getSelection()?.removeAllRanges();

        // Ending the text edit closes any link popover it opened — unless
        // this end was itself "focus moved into that popover", which
        // editBlur() already told us to keep open.
        if (!keepControl) this.control.close();

        // A render that landed while the caret was in this section was
        // stashed by paint() rather than discarded — apply it now that
        // editing is over. applyMarkup() already reindexes the section, so
        // the plain reindex below only runs when nothing was queued.
        const pending = this.pendingMarkup[state.sectionId];

        if (pending !== undefined) {
            delete this.pendingMarkup[state.sectionId];
            this.applyMarkup(state.sectionId, pending);
        } else {
            // The DOM moved under the map — rebuild it for this section
            StudioFields.index(document.querySelector(`[data-section="${state.sectionId}"]`));
        }
    },

    /**
     * contenteditable produces <br>/<div> for line breaks and leaves
     * non-breaking spaces behind where it padded the caret; normalise both
     * so the saved value is the text the user believes they typed.
     */
    readValue(host) {
        return host.innerText
            .replace(/\u00a0/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    },

    /**
     * A textarea field takes Enter as a newline; a text field commits on it.
     * This reads the declared type rather than sniffing the current value,
     * so an empty textarea still behaves like one.
     */
    isMultiline(entry, sectionId) {
        if (entry.index !== null) return false;   // repeater sub-fields are single-line in v1

        return StudioFields.contractFor(sectionId, entry.key)?.type === 'textarea';
    },

    /** Esc: field → item (when the field is in one) → section → nothing. */
    walkUp() {
        const { tier, sectionId, key, index } = this.selection;

        if (tier === 'field' && index !== null) {
            const item = (StudioFields.maps[sectionId]?.items || []).find((i) => i.key === key && i.index === index);

            if (item) {
                this.selectItem(item, sectionId);

                return true;
            }
        }

        if (tier === 'field' || tier === 'item') {
            this.selection = { tier: 'section', sectionId, path: null, key: null, index: null };
            this.clearHover();

            return true;
        }

        return false;   // already at section tier — the editor deselects
    },

    /* --- section actions (overlay buttons) ------------------------ */

    action(sectionId, action, event) {
        if (event) event.stopPropagation();

        // Deletion needs no confirm — it's undoable from the toast
        this.post('studio:section-action', { sectionId, action });
    },

    addAt(scope, index, event) {
        if (event) event.stopPropagation();
        if (this.mode === 'preview') return;
        this.post('studio:add-section', { scope, index });
    },

    openCode(ref, title, event) {
        if (event) event.stopPropagation();
        this.post('studio:open-code', { ref, title });
    },

    // The inspector, on request: the toolbar's Edit fields button, the
    // context menu, or E. Selecting alone never opens it.
    openInspector(sectionId, event) {
        if (event) event.stopPropagation();
        if (this.mode === 'preview' || !sectionId) return;
        this.post('studio:open-inspector', { sectionId });
    },

    /* --- context menu ---------------------------------------------- */

    menu: null,
    menuCloseTimer: null,

    MENU_ICONS: {
        fields: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 6h9M15 6h2M3 14h2M8 14h9"/><circle cx="13" cy="6" r="2"/><circle cx="6" cy="14" r="2"/></svg>',
        up: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd"/></svg>',
        down: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.53 13.53a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 1.06-1.06L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25Z" clip-rule="evenodd"/></svg>',
        plusAbove: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 6.75a.75.75 0 0 0-1.5 0v2.5h-2.5a.75.75 0 0 0 0 1.5h2.5v2.5a.75.75 0 0 0 1.5 0v-2.5h2.5a.75.75 0 0 0 0-1.5h-2.5v-2.5Z"/><path d="M3.75 2a.75.75 0 0 0 0 1.5h12.5a.75.75 0 0 0 0-1.5H3.75Z"/></svg>',
        plusBelow: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.25a.75.75 0 0 0-1.5 0v2.5h-2.5a.75.75 0 0 0 0 1.5h2.5v2.5a.75.75 0 0 0 1.5 0v-2.5h2.5a.75.75 0 0 0 0-1.5h-2.5v-2.5Z"/><path d="M3.75 16.5a.75.75 0 0 0 0 1.5h12.5a.75.75 0 0 0 0-1.5H3.75Z"/></svg>',
        duplicate: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>',
        global: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.196 12.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 12.87Z"/><path d="M3.196 8.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 8.87Z"/><path d="M10.38 1.103a.75.75 0 0 0-.76 0l-7.25 4.25a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .76 0l7.25-4.25a.75.75 0 0 0 0-1.294l-7.25-4.25Z"/></svg>',
        code: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>',
        show: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>',
        hide: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>',
        trash: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193v-.443A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Zm-1.586 4.914a.75.75 0 1 0-1.498.086l.5 8.5a.75.75 0 0 0 1.498-.086l-.5-8.5Zm4.67.086a.75.75 0 1 0-1.498-.086l-.5 8.5a.75.75 0 0 0 1.498.086l.5-8.5Z" clip-rule="evenodd"/></svg>',
        library: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>',
    },

    setupContextMenu() {
        // Close on ANY click that isn't inside the menu. Capture phase, so
        // section handlers that stopPropagation() can't keep it open.
        document.addEventListener('click', (event) => {
            if (this.menu && !this.menu.contains(event.target)) {
                this.closeMenu();
            }
        }, true);

        // Clicking the editor chrome moves focus out of the iframe
        window.addEventListener('blur', () => this.closeMenu());

        document.addEventListener('contextmenu', (event) => {
            // In Preview the page is the page — leave the browser's own menu
            if (this.mode === 'preview') return;

            // Right-clicking the menu itself keeps it open
            if (event.target.closest && event.target.closest('.studio-menu')) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            this.closeMenu(true);

            const wrapper = event.target.closest ? event.target.closest('[data-section]') : null;

            if (wrapper) {
                this.select(wrapper.dataset.section);
                this.openMenu(event.clientX, event.clientY, this.sectionMenuItems(wrapper));
            } else {
                this.openMenu(event.clientX, event.clientY, [
                    { header: 'Page' },
                    { label: 'Add section…', icon: 'library', kbd: '⌘K', onClick: () => this.addAt('page', null) },
                ]);
            }
        });

        window.addEventListener('scroll', () => this.closeMenu(), { passive: true });
        window.addEventListener('resize', () => this.closeMenu());

        // The canvas scrolls via the iframe's own documentElement, and
        // scroll doesn't bubble — capture it at the document so a nested
        // scroll container re-syncs the halo/chip too. Both re-resolve
        // through the same rAF-batched queuePaint(), never writing directly.
        //
        // An open #studio-control (the url/select/color popover) isn't
        // part of that repaint — it's a fixed-position element pinned to
        // the box it opened against — so scroll/resize would otherwise
        // leave it floating over whatever used to be there. Closing it
        // here, from the same path, is simpler and safer than
        // repositioning a popover mid-interaction.
        document.addEventListener('scroll', () => { this.rehover(); this.control.close(); }, { capture: true, passive: true });
        window.addEventListener('resize', () => { this.rehover(); this.control.close(); }, { passive: true });
    },

    sectionMenuItems(wrapper) {
        const d = wrapper.dataset;
        const id = d.section;
        const scope = d.scope || 'page';
        const docIndex = parseInt(d.docIndex || '0', 10);
        const isBlock = d.block === '1';
        const isLayout = scope === 'layout';
        const hidden = d.hidden === '1';
        const devMode = document.documentElement.classList.contains('studio-devmode');
        const scopeTag = isBlock ? 'Global block' : (isLayout ? 'Layout' : 'Section');

        const items = [
            { header: `${d.title} — ${scopeTag}` },
            { label: 'Edit fields', icon: 'fields', kbd: 'E', onClick: () => this.openInspector(id) },
            'sep',
            { label: 'Move up', icon: 'up', kbd: '⌘↑', disabled: d.docFirst === '1', onClick: () => this.action(id, 'move-up') },
            { label: 'Move down', icon: 'down', kbd: '⌘↓', disabled: d.docLast === '1', onClick: () => this.action(id, 'move-down') },
            'sep',
            { label: isLayout ? 'Add to layout above' : 'Add section above', icon: 'plusAbove', onClick: () => this.addAt(scope, docIndex) },
            { label: isLayout ? 'Add to layout below' : 'Add section below', icon: 'plusBelow', onClick: () => this.addAt(scope, docIndex + 1) },
            'sep',
            { label: 'Duplicate', icon: 'duplicate', kbd: '⌘D', onClick: () => this.action(id, 'duplicate') },
        ];

        if (!isBlock && !isLayout) {
            items.push({ label: 'Make global', icon: 'global', onClick: () => this.action(id, 'make-global') });
        }

        if (devMode) {
            items.push({ label: 'Edit code', icon: 'code', onClick: () => this.openCode(d.ref, d.title) });
        }

        items.push(
            { label: hidden ? 'Show section' : 'Hide section', icon: hidden ? 'show' : 'hide', onClick: () => this.action(id, 'toggle-hidden') },
            'sep',
            { label: 'Delete', icon: 'trash', kbd: '⌫', danger: true, onClick: () => this.action(id, 'delete') },
        );

        return items;
    },

    openMenu(x, y, items) {
        clearTimeout(this.menuCloseTimer);

        const menu = document.createElement('div');
        menu.className = 'studio-menu';

        for (const item of items) {
            if (item === 'sep') {
                const sep = document.createElement('div');
                sep.className = 'studio-menu-sep';
                menu.appendChild(sep);
                continue;
            }

            if (item.header) {
                const header = document.createElement('div');
                header.className = 'studio-menu-header';
                header.textContent = item.header;
                menu.appendChild(header);
                continue;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'studio-menu-item' + (item.danger ? ' studio-menu-danger' : '');
            button.disabled = !!item.disabled;
            button.innerHTML = (this.MENU_ICONS[item.icon] || '')
                + `<span>${item.label}</span>`
                + (item.kbd ? `<span class="studio-menu-kbd">${item.kbd}</span>` : '');
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                this.closeMenu();
                item.onClick?.();
            });
            menu.appendChild(button);
        }

        document.body.appendChild(menu);
        this.menu = menu;

        // Clamp inside the viewport, flipping the grow direction (and the
        // elastic transform origin) when the pointer is near an edge
        const rect = menu.getBoundingClientRect();
        const pad = 8;
        const flipX = x + rect.width + pad > window.innerWidth;
        const flipY = y + rect.height + pad > window.innerHeight;
        const left = flipX ? Math.max(pad, x - rect.width) : x;
        const top = flipY ? Math.max(pad, y - rect.height) : y;

        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        menu.style.transformOrigin = `${flipX ? 'right' : 'left'} ${flipY ? 'bottom' : 'top'}`;

        requestAnimationFrame(() => menu.classList.add('is-open'));
    },

    closeMenu(instant = false) {
        if (!this.menu) return;

        const menu = this.menu;
        this.menu = null;

        if (instant) {
            menu.remove();
            return;
        }

        menu.classList.add('is-closing');
        this.menuCloseTimer = setTimeout(() => menu.remove(), 130);
    },

    /* --- element select (Assistant context) ------------------------- */

    /**
     * Crosshair mode: the next click inside a section reports the clicked
     * element (its path from the section root, tag, and text) to the
     * editor instead of selecting the section.
     */
    setElementSelect(on) {
        document.documentElement.classList.toggle('studio-element-select', on);

        if (on && !this.elementSelectHandler) {
            this.elementSelectHandler = (event) => {
                const content = event.target.closest?.('[data-section-content]');
                if (!content) return;

                event.preventDefault();
                event.stopPropagation();

                const section = content.closest('[data-section]');
                const path = [];
                let node = event.target;

                while (node && node !== content) {
                    path.unshift(node.tagName.toLowerCase());
                    node = node.parentElement;
                }

                const sectionId = section?.dataset.section || null;
                const entry = sectionId ? StudioFields.at(sectionId, event.clientX, event.clientY) : null;
                const source = sectionId ? StudioFields.sourceFor(sectionId) : null;

                this.post('studio:element-selected', {
                    sectionId,
                    ref: section?.dataset.ref || null,
                    path: path.join(' > '),
                    tag: event.target.tagName.toLowerCase(),
                    text: (event.target.innerText || '').trim().slice(0, 160),
                    field: entry ? entry.key : null,
                    itemIndex: entry ? entry.index : null,
                    subKey: entry ? entry.subKey : null,
                    source: entry && source ? source + '.blade.php:' + entry.line : null,
                });

                this.setElementSelect(false);
            };

            document.addEventListener('click', this.elementSelectHandler, true);
        }

        if (!on && this.elementSelectHandler) {
            document.removeEventListener('click', this.elementSelectHandler, true);
            this.elementSelectHandler = null;
        }
    },

    /* --- live re-rendering ----------------------------------------- */

    // A global block placement mirrors every sibling placement of the
    // same block on this page — they all render from the shared data.
    siblingIds(sectionId) {
        const block = this.blocks[sectionId];
        if (!block) return [sectionId];

        return Object.keys(this.blocks).filter((id) => this.blocks[id] === block);
    },

    updateVariable(sectionId, key, value) {
        for (const id of this.siblingIds(sectionId)) {
            if (!this.variables[id] || Array.isArray(this.variables[id])) {
                this.variables[id] = {};
            }

            this.variables[id][key] = value;
            this.render(id);
        }
    },

    updateVariables(sectionId, variables) {
        for (const id of this.siblingIds(sectionId)) {
            this.variables[id] = { ...(this.variables[id] || {}), ...variables };
            this.render(id);
        }
    },

    /**
     * Queue a section for re-rendering. Keystrokes arrive far faster than a
     * round trip, so edits collect for a beat and then go out together; a
     * section already waiting on a response is re-queued rather than
     * double-requested, and only its newest values are ever sent.
     */
    render(sectionId) {
        if (!this.renderUrl || !this.refs[sectionId]) return;

        this.renderPending.add(sectionId);

        clearTimeout(this.renderTimer);
        this.renderTimer = setTimeout(() => this.flushRenders(), 90);
    },

    flushRenders() {
        const ready = [];

        for (const id of this.renderPending) {
            if (this.renderInFlight.has(id)) {
                this.renderQueued.add(id);
            } else {
                ready.push(id);
            }
        }

        this.renderPending.clear();

        if (!ready.length) return;

        const sections = ready.map((id) => ({
            id,
            ref: this.refs[id],
            variables: this.variables[id] || {},
            bindings: this.bindings[id] || {},
        }));

        ready.forEach((id) => this.renderInFlight.add(id));

        fetch(this.renderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ sections }),
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
            .then(({ html }) => {
                for (const [id, markup] of Object.entries(html || {})) {
                    this.paint(id, markup);
                }
            })
            .catch(() => {
                // A failed render leaves the last good markup in place — the
                // next keystroke retries, and the editor itself is already
                // telling the user if the connection is gone.
            })
            .finally(() => {
                let requeue = false;

                for (const id of ready) {
                    this.renderInFlight.delete(id);

                    if (this.renderQueued.delete(id)) {
                        this.renderPending.add(id);
                        requeue = true;
                    }
                }

                if (requeue) {
                    clearTimeout(this.renderTimer);
                    this.renderTimer = setTimeout(() => this.flushRenders(), 0);
                }
            });
    },

    paint(sectionId, markup) {
        // The caret is in this section — its DOM already shows the truth,
        // and replacing innerHTML would destroy the selection mid-keystroke.
        // The markup isn't lost, just deferred: stash it and teardownEdit()
        // applies it once editing ends, so a render that lands mid-type
        // isn't stale forever.
        if (this.editing && this.editing.sectionId === sectionId) {
            this.pendingMarkup[sectionId] = markup;
            return;
        }

        this.applyMarkup(sectionId, markup);
    },

    /** The one place that actually writes rendered section markup into the DOM. */
    applyMarkup(sectionId, markup) {
        const el = document.querySelector(`[data-section="${sectionId}"] [data-section-content]`);

        if (!el) return;

        el.innerHTML = markup;

        // Boot Alpine behaviors inside re-rendered markup
        if (window.Alpine?.initTree) {
            window.Alpine.initTree(el);
        }

        // The sentinels came with the new markup — rebuild this section's map
        StudioFields.index(el.closest('[data-section]'));
    },
};

/* ------------------------------------------------------------------ */
/*  Shared utilities                                                   */
/* ------------------------------------------------------------------ */

window.Studio = {
    toast,

    editor: StudioEditor,
    preview: StudioPreview,
    fields: StudioFields,

    // Set by the dev-mode code modal so global shortcuts stand down
    codeModalOpen: false,

    /**
     * Lazy-loading Monaco factory for the dev-mode source editor.
     * Injects the slim Monaco bundle + CSS on first use — studio.js
     * itself carries no editor code. Resolves { editor, getValue, setValue }.
     * Asset URLs come from window.__studioMonacoAssets, rendered by the
     * dev-mode block in home.blade.php.
     */
    async codeEditor(parent, { language = 'html', doc = '' } = {}) {
        const assets = window.__studioMonacoAssets;

        if (!assets) {
            throw new Error('The code editor is only available in dev mode.');
        }

        if (!window._studioMonacoPromise) {
            window._studioMonacoPromise = new Promise((resolve, reject) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = assets.css;
                document.head.appendChild(link);

                const script = document.createElement('script');
                script.src = assets.script;
                script.onload = resolve;
                script.onerror = () => reject(new Error('Could not load the code editor.'));
                document.head.appendChild(script);
            }).catch((error) => {
                // Allow the next open to retry a failed load
                window._studioMonacoPromise = null;
                throw error;
            });
        }

        await window._studioMonacoPromise;

        const editor = window.StudioMonaco.create(parent, {
            language,
            value: doc,
            workers: assets.workers,
        });

        return {
            editor,
            getValue: () => editor.getValue(),
            setValue(value) {
                editor.setValue(value);
            },
        };
    },

    /**
     * Drag-to-reorder that plays nice with Livewire's DOM morphing:
     * after a drop we revert the DOM to the server-known order and let
     * Livewire re-render the authoritative result.
     */
    sortable(el, handle, onSorted) {
        return Sortable.create(el, {
            handle,
            animation: 150,
            ghostClass: 'is-ghost',
            chosenClass: 'is-chosen',
            direction: 'vertical',
            onEnd(evt) {
                const { item, oldIndex, newIndex } = evt;

                if (oldIndex === newIndex) return;

                const ids = Array.from(el.children)
                    .map((child) => child.dataset.sectionId)
                    .filter(Boolean);

                // Revert to pre-drag order so morphing starts from known state
                const children = Array.from(el.children);
                children.splice(newIndex, 1);
                children.splice(oldIndex, 0, item);
                children.forEach((child) => el.appendChild(child));

                onSorted(ids);
            },
        });
    },


    /**
     * Upload an image through the Studio upload endpoint.
     * Returns the public URL of the stored file.
     * Max size must match the `max:` rule in StudioController::upload().
     */
    maxUploadMb: 5,

    /**
     * Ask the Media panel for an image. Opens the panel in picker mode and
     * resolves with the chosen URL, or null when the pick is cancelled.
     */
    mediaPick() {
        return new Promise((resolve) => {
            const id = (window.crypto?.randomUUID?.() || String(Date.now() + Math.random()));

            const onPicked = (event) => {
                if (event.detail?.id !== id) return;
                window.removeEventListener('studio:media-picked', onPicked);
                resolve(event.detail.url ?? null);
            };

            window.addEventListener('studio:media-picked', onPicked);
            window.dispatchEvent(new CustomEvent('studio:media-pick', { detail: { id } }));
        });
    },

    async upload(file, { url, csrf }) {
        const sizeMb = file.size / (1024 * 1024);
        if (sizeMb > this.maxUploadMb) {
            throw new Error(`This image is ${sizeMb.toFixed(1)} MB — the maximum upload size is ${this.maxUploadMb} MB.`);
        }

        const body = new FormData();
        body.append('file', file);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
            throw new Error(this.uploadErrorMessage(response.status, data));
        }

        return data.url;
    },

    uploadErrorMessage(status, data) {
        const serverMessage = data.errors?.file?.[0] || data.message || data.error;

        if (status === 413) {
            return `The server rejected this image as too large before it reached Studio (HTTP 413). Raise the web server body limit (e.g. nginx \`client_max_body_size\`) and php.ini \`upload_max_filesize\`/\`post_max_size\` to at least ${this.maxUploadMb}M.`;
        }
        if (status === 419) {
            return 'Your session expired — refresh the page and try uploading again.';
        }
        if (serverMessage) {
            return serverMessage;
        }
        return status ? `Upload failed (HTTP ${status}).` : 'Upload failed — the server did not respond.';
    },
};

// Auto-boot depending on which document loaded the bundle
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

function boot() {
    if (window.__studioPreview) {
        StudioPreview.init(window.__studioPreview);
    } else if (document.getElementById('studio-canvas-frame')) {
        StudioEditor.init();
    }
}

