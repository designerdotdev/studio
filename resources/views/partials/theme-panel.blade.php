{{-- The Theme flyout: a palette, an accent, corners and a type pairing for
     the whole site, laid over the template's own. A choice restyles the
     canvas at once (the Designer global variables, set on the framed page's
     <html>) and is saved into the draft site document (PUT api/theme) — so it
     is published, discarded and undone with everything else, and reaches
     css/site.css on Publish (Support\SiteTheme). The lists and the arithmetic
     are resources/js/theme.js (`Studio.theme`). --}}
<div
    class="flex h-full min-h-0 flex-col"
    x-data="{
        state: { palette: 'own', accent: 'own', radius: 'own', type: 'own', ...(window.__studioTheme || {}) },
        // The template's own values, read from the canvas with the theme lifted off
        own: null,
        timer: null,
        fade: null,
        get T() { return window.Studio.theme },
        get resolved() { return this.T.resolve(this.state, this.own) },
        get changed() { return ['palette', 'accent', 'radius', 'type'].some((k) => this.state[k] !== 'own') },
        doc() { try { return document.getElementById('studio-canvas-frame').contentDocument } catch (e) { return null } },

        // Every canvas load is a new document: read the template's values, put the theme back
        loaded() {
            const doc = this.doc();
            if (!doc?.documentElement) return;

            // What the server laid over the stylesheet comes off first — from here the panel holds it
            doc.getElementById('studio-theme')?.remove();
            this.T.ALL.forEach((n) => doc.documentElement.style.removeProperty(n));

            const cs = getComputedStyle(doc.documentElement);
            const v = (n) => cs.getPropertyValue(n).trim();
            this.own = { background: v('--background'), foreground: v('--foreground'), card: v('--card'), muted: v('--muted'), border: v('--border'), accent: v('--accent') };
            this.apply(false);
        },

        apply(animate = true) {
            const doc = this.doc();
            if (!doc?.documentElement) return;

            const html = doc.documentElement;
            const { vars, scheme, font } = this.resolved;

            if (animate) {
                // One crossfade for the whole page; the site's own transitions come back right after
                if (!doc.getElementById('studio-theme-fade')) {
                    const style = doc.createElement('style');
                    style.id = 'studio-theme-fade';
                    style.textContent = 'html.studio-theme-fading, html.studio-theme-fading *, html.studio-theme-fading *::before, html.studio-theme-fading *::after { transition: background-color .45s ease, color .45s ease, border-color .45s ease, fill .45s ease, stroke .45s ease, box-shadow .45s ease, border-radius .45s ease !important; }';
                    doc.head.appendChild(style);
                }
                html.classList.add('studio-theme-fading');
                clearTimeout(this.fade);
                this.fade = setTimeout(() => html.classList.remove('studio-theme-fading'), 500);
            }

            this.T.ALL.forEach((n) => html.style.removeProperty(n));
            Object.entries(vars).forEach(([n, value]) => html.style.setProperty(n, value));
            html.style.colorScheme = scheme || '';

            // Anything the page draws itself (a WebGL backdrop, a chart) can follow the change
            try { doc.dispatchEvent(new doc.defaultView.CustomEvent('designer:theme', { detail: vars })); } catch (e) {}

            if (font && !doc.getElementById('studio-theme-font-' + font.id)) {
                const link = doc.createElement('link');
                link.id = 'studio-theme-font-' + font.id;
                link.rel = 'stylesheet';
                link.href = 'https://fonts.googleapis.com/css2?family=' + font.load + '&display=swap';
                doc.head.appendChild(link);
            }
        },

        pick(kind, id) {
            if (this.state[kind] === id) return;
            this.state[kind] = id;
            this.apply();
            this.save();
        },

        reset() {
            Object.assign(this.state, { palette: 'own', accent: 'own', radius: 'own', type: 'own' });
            this.apply();
            this.save();
        },

        // A moment after the last choice, so stepping through palettes is one save
        save() {
            clearTimeout(this.timer);
            window.dispatchEvent(new CustomEvent('studio:status', { detail: { state: 'saving' } }));
            this.timer = setTimeout(async () => {
                const { vars, scheme, font } = this.resolved;
                try {
                    const response = await fetch(@js(route('studio.api.theme')), {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify({ theme: { ...this.state, vars, scheme, font: font?.load || null } }),
                    });
                    if (!response.ok) throw new Error(String(response.status));
                    window.dispatchEvent(new CustomEvent('studio:status', { detail: { state: 'saved' } }));
                } catch (e) {
                    window.dispatchEvent(new CustomEvent('studio:status', { detail: { state: 'error' } }));
                    window.Studio.toast({ title: 'The theme was not saved', description: 'Check your connection and pick it again.' }, 'error');
                }
            }, 350);
        },

        // Undo or redo may have changed the theme under the panel
        async restored() {
            try {
                const response = await fetch(@js(route('studio.api.theme')), { headers: { 'Accept': 'application/json' } });
                const { theme } = await response.json();
                Object.assign(this.state, { palette: 'own', accent: 'own', radius: 'own', type: 'own' }, theme || {});
                delete this.state.vars; delete this.state.font; delete this.state.scheme;
            } catch (e) { /* the canvas reload still shows what was restored */ }
        },

        // A palette tile's colours: the scheme's, or the template's own for the first tile
        tile(p) {
            const c = p.id === 'own' ? (this.own || { background: '#f4f4f5', foreground: '#18181b', accent: '#3b6ff0', card: '#ffffff', border: 'rgba(0,0,0,.1)' }) : p;
            return `--p-bg:${c.background};--p-fg:${c.foreground};--p-accent:${c.accent};--p-card:${c.card};--p-border:${c.border}`;
        },
        dot(a) { return a.id === 'own' ? (this.own?.accent || '#3b6ff0') : a.ink ? (this.resolved.vars['--foreground'] || this.own?.foreground || '#111111') : a.value },
        name(list, id) { return (list.find((x) => x.id === id) || list[0]).name },
    }"
    x-init="if (doc()?.readyState === 'complete') loaded()"
    @studio:canvas-loaded.window="loaded()"
    @studio:history-restored.window="restored()"
>
    <div class="s-panel-head">
        <p class="s-microlabel flex-1">Theme</p>
        <button type="button" class="s-icon-btn" title="Back to the template's own" aria-label="Reset the theme" :disabled="!changed" :class="!changed && 'opacity-30'" @click="reset()">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2.75 8A5.25 5.25 0 1 0 4.3 4.3M2.75 2.5v3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        @include('studio::partials.float-close')
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <section class="s-theme-sec">
            <h3>Palette <span x-text="name(T.PALETTES, state.palette)"></span></h3>
            <div class="s-theme-palettes">
                <template x-for="p in T.PALETTES" :key="p.id">
                    <button type="button" class="s-theme-pal" :style="tile(p)" :title="p.name" :aria-label="p.name" :aria-pressed="state.palette === p.id" @click="pick('palette', p.id)"><i class="h"></i><i class="t"></i><i class="a"></i><i class="c"></i></button>
                </template>
            </div>
        </section>

        <section class="s-theme-sec">
            <h3>Accent <span x-text="name(T.ACCENTS, state.accent)"></span></h3>
            <div class="s-theme-accents">
                <template x-for="a in T.ACCENTS" :key="a.id">
                    <button type="button" class="s-theme-acc" :class="a.id === 'own' && 'is-own'" :style="`--a:${dot(a)}`" :title="a.name" :aria-label="a.name" :aria-pressed="state.accent === a.id" @click="pick('accent', a.id)"></button>
                </template>
            </div>
        </section>

        <section class="s-theme-sec">
            <h3>Corners <span x-text="name(T.RADII, state.radius)"></span></h3>
            <div class="s-theme-seg">
                <template x-for="r in T.RADII" :key="r.id">
                    <button type="button" :title="r.name" :aria-pressed="state.radius === r.id" @click="pick('radius', r.id)">
                        <i x-show="r.id !== 'own'" class="corner" :style="`--r:${({ sharp: 0, soft: 3, round: 5, pill: 8 })[r.id] || 0}px`"></i>
                        <span x-text="r.name"></span>
                    </button>
                </template>
            </div>
        </section>

        <section class="s-theme-sec">
            <h3>Type <span x-text="name(T.TYPES, state.type)"></span></h3>
            <div class="s-theme-types">
                <template x-for="t in T.TYPES" :key="t.id">
                    <button type="button" class="s-theme-type" :title="t.name + (t.who ? ' ' + t.who : '')" :aria-pressed="state.type === t.id" @click="pick('type', t.id)">
                        <span class="sample" :style="`font-family:${t.css || 'inherit'};${t.serif ? '' : 'font-weight:600'}`" x-text="t.id === 'own' ? 'Template' : t.name"></span>
                        <span class="who" x-text="t.id === 'own' ? 'as shipped' : (t.who || 'everywhere')"></span>
                    </button>
                </template>
            </div>
        </section>
    </div>

    <p class="s-theme-foot">
        <span x-show="changed">Saved to the draft — it reaches the live site, and <code>site.css</code>, on Publish.</span>
        <span x-show="!changed" x-cloak>The template as it shipped. Pick anything to restyle the whole site.</span>
    </p>
</div>
