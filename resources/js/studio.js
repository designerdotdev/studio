import blade from './blade.js';
import Sortable from 'sortablejs';
import collapse from '@alpinejs/collapse';

// Client-side Blade renderer (used by the preview iframe)
window.blade = blade;

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

function toast(message, type = 'success', duration = 3200) {
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

    requestAnimationFrame(() => el.classList.add('is-visible'));

    let dismissed = false;
    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        el.classList.remove('is-visible');
        setTimeout(() => el.remove(), 220);
    };

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
                    window.Livewire?.dispatch('studio:select-section', { id: data.sectionId });
                    break;

                case 'studio:deselected':
                    this.selectedId = null;
                    window.Livewire?.dispatch('studio:deselect-section');
                    break;

                case 'studio:add-section':
                    window.dispatchEvent(new CustomEvent('studio:open-library', { detail: { index: data.index ?? null } }));
                    break;

                case 'studio:section-action':
                    window.Livewire?.dispatch('studio:section-action', { id: data.sectionId, action: data.action });
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

        // Structural changes persisted → reload the preview document
        window.addEventListener('studio:refresh-preview', () => this.refreshPreview());

        // Panel tracks selection too (list clicks)
        window.addEventListener('studio:selection-changed', (event) => {
            this.selectedId = event.detail?.id ?? null;
        });
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
        // Cmd/Ctrl+S — everything autosaves; reassure instead of a browser dialog
        if (meta && (key === 's' || key === 'S')) {
            preventDefault();
            toast('All changes save automatically', 'info', 2200);
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

        if (key === 'Backspace' || key === 'Delete') {
            preventDefault();
            if (window.confirm('Delete this section?')) {
                window.Livewire?.dispatch('studio:section-action', { id: this.selectedId, action: 'delete' });
                this.selectedId = null;
            }
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
    templates: {},
    selectedId: null,

    init({ variables, templates }) {
        this.variables = variables || {};
        this.templates = templates || {};

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
            }
        });

        // Editing mode: links and forms inside sections must never navigate
        document.addEventListener('click', (event) => {
            const link = event.target.closest ? event.target.closest('a') : null;
            if (link) event.preventDefault();
        }, true);

        document.addEventListener('submit', (event) => event.preventDefault(), true);

        // Click on empty canvas space deselects
        document.addEventListener('click', () => {
            this.clearSelection();
            this.post('studio:deselected');
        });

        // Forward keyboard shortcuts to the editor
        document.addEventListener('keydown', (event) => {
            const relevant = event.key === 'Escape'
                || event.key === 'Backspace'
                || event.key === 'Delete'
                || ((event.metaKey || event.ctrlKey) && ['d', 'D', 's', 'S'].includes(event.key));

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

        if (action === 'delete' && !window.confirm('Delete this section?')) {
            return;
        }

        this.post('studio:section-action', { sectionId, action });
    },

    addAt(index, event) {
        if (event) event.stopPropagation();
        this.post('studio:add-section', { index });
    },

    /* --- live re-rendering ----------------------------------------- */

    updateVariable(sectionId, key, value) {
        if (!this.variables[sectionId] || Array.isArray(this.variables[sectionId])) {
            this.variables[sectionId] = {};
        }

        this.variables[sectionId][key] = value;
        this.render(sectionId);
    },

    updateVariables(sectionId, variables) {
        this.variables[sectionId] = { ...(this.variables[sectionId] || {}), ...variables };
        this.render(sectionId);
    },

    render(sectionId) {
        const el = document.querySelector(`[data-section="${sectionId}"] [data-section-content]`);
        const template = this.templates[sectionId];

        if (!el || !template) return;

        el.innerHTML = blade.renderBladeTemplate(template, this.variables[sectionId] || {});

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
     */
    async upload(file, { url, csrf }) {
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
            const message = data.message || data.error || 'Upload failed';
            throw new Error(message);
        }

        return data.url;
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

export { blade };
