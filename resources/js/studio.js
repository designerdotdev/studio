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
                    // Selecting on the canvas always lands in the Sections panel
                    window.Alpine?.store('studio')?.setRail?.('sections', true);
                    window.Livewire?.dispatch('studio:select-section', { id: data.sectionId });
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

                case 'studio:open-code':
                    window.dispatchEvent(new CustomEvent('studio:open-code-editor', {
                        detail: { ref: data.ref, title: data.title },
                    }));
                    break;

                case 'studio:element-selected':
                    // The Assistant composer listens for this and shows a chip
                    window.dispatchEvent(new CustomEvent('studio:element-selected', { detail: data }));
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
                meta: event.metaKey || event.ctrlKey,
                typing: isTyping(),
                preventDefault: () => event.preventDefault(),
            });
        });
    },

    handleShortcut({ key, meta, typing, preventDefault = () => {} }) {
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

        if (typing) return;

        // Cmd/Ctrl+B — collapse the sidebar for a full-width canvas (after
        // the typing check: rich-text fields own ⌘B as bold)
        if (meta && (key === 'b' || key === 'B')) {
            preventDefault();
            window.Alpine?.store('studio')?.toggleSidebar();
            return;
        }

        if (key === 'Escape') {
            if (this.selectedId) {
                this.selectedId = null;
                this.send('studio:deselect');
                window.Livewire?.dispatch('studio:deselect-section');
            }
            return;
        }

        if (!this.selectedId) return;

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

            if (value.startsWith('sf:')) {
                const at = value.lastIndexOf('@');
                open.push({ raw: value.slice(3, at), line: Number(value.slice(at + 1)) || 0, start: node });
                continue;
            }

            if (value === '/sf' && open.length) {
                const { raw, line, start } = open.pop();
                const range = document.createRange();

                try {
                    range.setStartAfter(start);
                    range.setEndBefore(node);
                } catch (e) {
                    continue;
                }

                entries.push({ ...this.parsePath(raw), line, kind: 'text', range, el: null });
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
    hovered: null,
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

                case 'studio:mode':
                    // Code hides the canvas entirely; while it is on screen at
                    // all (the split) it stays selectable, like Edit.
                    this.setMode(data.mode === 'preview' ? 'preview' : 'edit');
                    break;

                case 'studio:menu-close':
                    this.closeMenu();
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

        document.addEventListener('mousemove', (event) => this.hoverAt(event), { passive: true });
        document.addEventListener('mouseleave', () => this.clearHover());

        // Click on empty canvas space deselects (and closes the context menu)
        document.addEventListener('click', () => {
            if (this.mode === 'preview') return;

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
    },

    post(type, payload = {}) {
        window.parent.postMessage({ type, ...payload }, window.location.origin);
    },

    /* --- selection ------------------------------------------------ */

    select(sectionId, event) {
        if (event) event.stopPropagation();

        // Preview mode has no selection — the chrome is hidden, and the
        // inline onclick handlers must not reach past it.
        if (this.mode === 'preview') return;

        // A click resolves to the deepest tier under the pointer; only a
        // click on section chrome selects the section itself.
        if (event) {
            const hit = this.tierAt(sectionId, event.clientX, event.clientY);

            if (hit.tier === 'field') {
                this.applySelection(sectionId, false);
                this.selectField(hit.entry, sectionId);
                this.post('studio:section-selected', { sectionId });

                return;
            }

            if (hit.tier === 'item') {
                this.applySelection(sectionId, false);
                this.selectItem(hit.item, sectionId);
                this.post('studio:section-selected', { sectionId });

                return;
            }
        }

        this.selection = { tier: 'section', sectionId, path: null, key: null, index: null };
        this.applySelection(sectionId, false);
        this.post('studio:section-selected', { sectionId });
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
        const wrapper = target.closest?.('[data-section]');

        if (!wrapper) return this.clearHover();

        const sectionId = wrapper.dataset.section;
        const hit = this.tierAt(sectionId, x, y);

        if (hit.tier === 'section') {
            // Inside the rendered markup but on nothing Studio owns
            const inContent = !!target.closest?.('[data-section-content]');

            return inContent ? this.paintHalo(null, 'code', { target }) : this.clearHover();
        }

        this.paintHalo(hit, hit.tier, { target });
    },

    /**
     * Resolve the halo/chip geometry for whatever is under the pointer.
     * Everything here is a READ (StudioFields.box()/getBoundingClientRect
     * touch layout) — nothing here writes to the DOM. The result is handed
     * to queuePaint(), which is the only place that ever assigns style or
     * className, batched into a single requestAnimationFrame. This keeps
     * mousemove — read layout, read layout, ... — from ever being
     * interleaved with a write that would force a synchronous reflow.
     */
    paintHalo(hit, kind, event) {
        let box = null;
        let label = 'Set in code';
        let source = '';

        if (kind === 'field') {
            const sectionId = this.sectionIdAt(event);
            box = StudioFields.box(hit.entry);
            label = this.labelFor(hit.entry, sectionId);
            const path = StudioFields.sourceFor(sectionId);
            source = path ? path.split('/').pop() + ':' + hit.entry.line : '';
        } else if (kind === 'item') {
            const rect = hit.item.el.getBoundingClientRect();
            box = { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
            label = this.itemLabel(hit.item);
        } else {
            const rect = event.target.getBoundingClientRect?.();
            if (rect) box = { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
        }

        if (!box || box.width === 0) return this.clearHover();

        this.hovered = { kind, hit };
        this.queuePaint({ kind, box, label, source });
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

    /** The single place that writes halo/chip style, class and text. */
    flushPaint() {
        const halo = document.getElementById('studio-fhalo');
        const chip = document.getElementById('studio-fchip');

        if (!halo || !chip) return;

        const job = this.pendingPaint;
        this.pendingPaint = undefined;

        if (!job) {
            halo.classList.remove('is-on');
            chip.classList.remove('is-on');
            return;
        }

        const { kind, box, label, source } = job;

        halo.className = 'studio-fhalo is-on' + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : '');
        halo.style.left = box.left + 'px';
        halo.style.top = box.top + 'px';
        halo.style.width = box.width + 'px';
        halo.style.height = box.height + 'px';

        chip.className = 'studio-fchip is-on' + (kind === 'item' ? ' is-item' : kind === 'code' ? ' is-code' : '');
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
    },

    clearHover() {
        this.hovered = null;
        this.queuePaint(null);
    },

    sectionIdAt(event) {
        return event.target.closest?.('[data-section]')?.dataset.section || null;
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

    /* --- context menu ---------------------------------------------- */

    menu: null,
    menuCloseTimer: null,

    MENU_ICONS: {
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
        document.addEventListener('scroll', () => this.rehover(), { capture: true, passive: true });
        window.addEventListener('resize', () => this.rehover(), { passive: true });
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

                this.post('studio:element-selected', {
                    sectionId: section?.dataset.section || null,
                    ref: section?.dataset.ref || null,
                    path: path.join(' > '),
                    tag: event.target.tagName.toLowerCase(),
                    text: (event.target.innerText || '').trim().slice(0, 160),
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

