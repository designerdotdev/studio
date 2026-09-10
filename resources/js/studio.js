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

const StudioPreview = {
    variables: {},
    refs: {},
    blocks: {},
    selectedId: null,
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

    init({ variables, bindings, refs, blocks, renderUrl, csrf }) {
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

            const relevant = event.key === 'Escape'
                || event.key === 'Backspace'
                || event.key === 'Delete'
                || ((event.metaKey || event.ctrlKey) && ['d', 'D', 's', 'S', 'k', 'K', 'ArrowUp', 'ArrowDown'].includes(event.key));

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
    },
};

/* ------------------------------------------------------------------ */
/*  Shared utilities                                                   */
/* ------------------------------------------------------------------ */

window.Studio = {
    toast,

    editor: StudioEditor,
    preview: StudioPreview,

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
     * Export the current page as a Blade file. Shows result toasts.
     * Returns true on success.
     */
    async exportBlade(url) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                },
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                toast('Blade file exported to ' + (data.relative_path || data.path));
                return true;
            }

            toast(data.error || 'Export failed', 'error');
        } catch (e) {
            toast('Export failed', 'error');
        }

        return false;
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

