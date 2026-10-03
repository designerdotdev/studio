<x-studio::layouts.app>
    <x-slot:title>Welcome — Designer Studio</x-slot:title>

    <style>
        /* The welcome screen opens on the mark alone: guides draw around
           it, a compass arm sweeps the circle, the square closes, then the
           fill wipes up as the mark scales into its place and the text
           follows. Everything is drawn in the ink colour, so it holds in
           either theme. */
        .intro {
            --e: cubic-bezier(.25, 1, .5, 1);
            --io: cubic-bezier(.65, 0, .35, 1);
            --mv: cubic-bezier(.7, 0, .2, 1);   /* the wipe and the move into place share this curve */
            --mark-n: 40;                        /* the mark's resting height, in px */
            --big: 2.1;                          /* its size while it is drawn, as a multiple of that (84px) */
            --ts: 2.15s;                         /* when the wipe and the move start */
            --sd: .95s;                          /* how long they take */
            --t0: 2.95s;                         /* when the text starts */
            --guide: .45;                        /* the compass arm: ink at this opacity */
            --grid: .22;                         /* the guide lines of the grid: half of that */
            --dot: color-mix(in srgb, var(--color-ink) 24%, transparent);   /* a dot at the middle of the spotlight */
        }

        /* The dot grid is only seen through one soft spotlight, which
           follows the pointer (--x / --y, set by the script below) and
           stays where the pointer last was. Until the pointer has moved it
           sits behind the text. */
        .intro-dots {
            --x: 50%;
            --y: 42%;
            --spot: radial-gradient(circle 420px at var(--x) var(--y), #000, rgb(0 0 0 / .55) 35%, rgb(0 0 0 / .16) 68%, transparent);
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background: radial-gradient(circle, var(--dot) 1px, transparent 1.5px) center / 22px 22px;
            -webkit-mask-image: var(--spot);
            mask-image: var(--spot);
        }

        .intro-mark {
            /* Line widths in viewBox units: about 1.2px on screen while the mark is at its opening size. */
            --sw: calc(94px / var(--mark-n) / var(--big));
            position: relative;
            isolation: isolate;
            height: calc(var(--mark-n) * 1px);
            aspect-ratio: 72 / 75;
        }
        .intro-plan, .intro-art { position: absolute; inset: 0; }
        .intro-mark svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }

        /* The drawing sheet: the guides stay where the mark was drawn (the
           middle of the screen, --lift below its resting place) and fade
           as it lifts away. */
        .intro-plan { transform: translateY(var(--lift, 150px)) scale(var(--big)); }
        .intro-plan .intro-guides-svg { inset: -32% auto auto -36.11%; width: 172.22%; height: 164%; }   /* viewBox -26 -24 124 123 over the mark's 72 x 75 */
        .intro-guides { opacity: 0; }
        .intro-g { fill: none; stroke: currentColor; stroke-opacity: var(--grid); stroke-width: calc(var(--sw) * .7); stroke-dasharray: 1 2; }

        .intro-o, .intro-radius { fill: none; stroke: currentColor; stroke-width: var(--sw); }
        .intro-o { stroke-dasharray: 1 2; opacity: 0; }   /* the long gap keeps a closed shape from leaving a speck at its start point */
        .intro-radius { stroke-opacity: var(--guide); opacity: 0; transform-origin: 47px 25px; }

        .intro-headline { overflow: hidden; padding-bottom: .12em; margin-bottom: -.12em; }
        .intro-w { display: block; }

        @keyframes intro-draw { from { stroke-dashoffset: 1; } }
        @keyframes intro-fade-in { from { opacity: 0; } }
        @keyframes intro-rise { from { opacity: 0; translate: 0 14px; } }
        @keyframes intro-slide { from { translate: 0 118%; } }
        @keyframes intro-blip { 0% { opacity: 0; } 8%, 88% { opacity: 1; } 100% { opacity: 0; } }
        @keyframes intro-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes intro-o-life { 0%, 85% { opacity: .85; } 100% { opacity: 0; } }
        @keyframes intro-settle { from { transform: translateY(var(--lift, 150px)) scale(var(--big)); } }
        @keyframes intro-wipe { from { clip-path: inset(102% -2% -2% -2%); } to { clip-path: inset(-2% -2% -2% -2%); } }
        @keyframes intro-guides { 0%, 83% { opacity: 1; } 100% { opacity: 0; } }

        /* Guides, compass, square, then the wipe and the move together, then the text */
        .intro.is-playing .intro-guides { animation: intro-guides 2.6s linear both; }
        .intro.is-playing .intro-g { animation: intro-draw .7s calc(var(--i) * 45ms) var(--io) backwards; }
        .intro.is-playing .intro-radius { animation: intro-spin .95s .55s var(--io) backwards, intro-blip .95s .55s linear both; }
        .intro.is-playing .intro-o-circle { animation: intro-draw .95s .55s var(--io) backwards, intro-o-life calc(var(--ts) + var(--sd)) linear both; }
        .intro.is-playing .intro-o-square { animation: intro-draw .75s 1.35s var(--io) backwards, intro-o-life calc(var(--ts) + var(--sd)) linear both; }
        .intro.is-playing .intro-art { animation: intro-settle var(--sd) var(--ts) var(--mv) backwards; }
        .intro.is-playing .intro-fill { animation: intro-wipe var(--sd) var(--ts) var(--mv) backwards; }
        .intro.is-playing .intro-dots { animation: intro-fade-in 1.4s calc(var(--ts) + .3s) var(--e) backwards; }
        .intro.is-playing .intro-w { animation: intro-slide .8s var(--t0) var(--e) backwards; }
        .intro.is-playing .intro-t { animation: intro-rise .7s calc(var(--t0) + var(--i) * 90ms) var(--e) backwards; }

        /* The pinned action bar owns the bottom edge of step 2 */
        #studio-toasts { --studio-toast-bottom: 84px; }
    </style>

    <div
        class="s-canvas relative flex h-full w-full flex-col overflow-y-auto"
        x-data="{
            step: 1,
            selected: @js(array_key_first(array_filter($templates, fn ($t) => $t['active'])) ?? array_key_first($templates)),
            filter: 'all',
            theme: 'all',
            query: '',
            showInactive: false,
            applying: false,
            templates: @js(array_map(fn ($key, $t) => [
                'key' => $key,
                'title' => $t['title'],
                'category' => $t['category'],
                'theme' => $t['theme'],
                'active' => $t['active'],
                'search' => \Illuminate\Support\Str::lower($t['title'] . ' ' . $key . ' ' . $t['category'] . ' ' . $t['theme'] . ' ' . $t['description']),
            ], array_keys($templates), $templates)),

            showTemplates() {
                this.step = 2;
            },

            matches(template) {
                const needle = this.query.trim().toLowerCase();

                return (template.active || this.showInactive)
                    && (this.filter === 'all' || template.category === this.filter)
                    && (this.theme === 'all' || template.theme === this.theme)
                    && (!needle || template.search.includes(needle));
            },

            get shown() {
                return this.templates.filter((t) => this.matches(t)).length;
            },

            /* What the count is out of: the offered templates, or all of them. */
            get offered() {
                return this.showInactive ? this.templates.length : this.templates.filter((t) => t.active).length;
            },

            get selectedTitle() {
                return this.templates.find((t) => t.key === this.selected)?.title ?? '—';
            },

            reset() {
                this.filter = 'all';
                this.theme = 'all';
                this.query = '';
            },

            async apply() {
                if (!this.selected || this.applying) return;
                this.applying = true;
                try {
                    const response = await fetch(@js(route('studio.api.onboarding.apply')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ template: this.selected }),
                    });
                    const data = await response.json();
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    window.Studio?.toast(data.message || 'The template could not be installed', 'error', 8000);
                } catch (e) {
                    window.Studio?.toast('Something went wrong — please try again', 'error');
                }
                this.applying = false;
            }
        }"
    >
        {{-- Step 1 — Welcome. It plays the intro once; a click or a key
             skips to the end of it. --}}
        <div x-show="step === 1" data-intro class="intro relative isolate flex min-h-full shrink-0 flex-col items-center justify-center overflow-hidden px-6 py-16 text-center">
            <div class="intro-dots" aria-hidden="true"></div>

            <div class="intro-mark text-ink" aria-hidden="true">
                <div class="intro-plan">
                    <svg class="intro-guides-svg" viewBox="-26 -24 124 123">
                        <g class="intro-guides">
                            <path class="intro-g" style="--i:0" pathLength="1" d="M-26 0H98"/>
                            <path class="intro-g" style="--i:1" pathLength="1" d="M-26 25H98"/>
                            <path class="intro-g" style="--i:2" pathLength="1" d="M-26 50H98"/>
                            <path class="intro-g" style="--i:3" pathLength="1" d="M-26 75H98"/>
                            <path class="intro-g" style="--i:4" pathLength="1" d="M0-24V99"/>
                            <path class="intro-g" style="--i:5" pathLength="1" d="M22-24V99"/>
                            <path class="intro-g" style="--i:6" pathLength="1" d="M50-24V99"/>
                            <path class="intro-g" style="--i:7" pathLength="1" d="M72-24V99"/>
                        </g>
                    </svg>
                </div>

                <div class="intro-art">
                    <svg viewBox="0 0 72 75">
                        <path class="intro-radius" d="M47 25H72"/>
                        <circle class="intro-o intro-o-circle" cx="47" cy="25" r="25" pathLength="1"/>
                        <rect class="intro-o intro-o-square" x="0" y="25" width="50" height="50" rx="5" pathLength="1"/>
                    </svg>
                    <svg class="intro-fill" viewBox="0 0 72 75">
                        <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z"/>
                    </svg>
                </div>
            </div>

            <h1 class="intro-headline mt-8 text-4xl font-semibold tracking-tight text-ink">
                <span class="intro-w">Welcome to Designer Studio</span>
            </h1>
            <p class="intro-t mt-4 max-w-md text-[15px] leading-relaxed text-soft" style="--i:2">
                The visual designer for your Laravel site. Developers define the sections — anyone on the team edits the pages.
            </p>

            <div class="intro-t mt-10" style="--i:3">
                <button @click="showTemplates()" class="s-btn-primary !h-11 !px-7 !text-sm">
                    Choose a template
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
                </button>
            </div>

            <p class="intro-t mt-16 text-xs text-faint" style="--i:4">
                Everything can be changed later — templates are just a starting point.
            </p>
        </div>

        {{-- The spotlight on the dot grid trails the pointer by a few
             frames, and rests wherever the pointer was last. --}}
        <script>
            (() => {
                const stage = document.querySelector('[data-intro]');
                const dots = stage.querySelector('.intro-dots');
                const still = matchMedia('(prefers-reduced-motion: reduce)').matches;

                let at = null, to = null, frame = 0;

                const place = () => {
                    dots.style.setProperty('--x', at.x.toFixed(1) + 'px');
                    dots.style.setProperty('--y', at.y.toFixed(1) + 'px');
                };

                const step = () => {
                    at.x += (to.x - at.x) * 0.16;
                    at.y += (to.y - at.y) * 0.16;

                    const near = Math.abs(to.x - at.x) < 0.5 && Math.abs(to.y - at.y) < 0.5;

                    if (near) at = { ...to };
                    place();
                    frame = near ? 0 : requestAnimationFrame(step);
                };

                stage.addEventListener('pointermove', (event) => {
                    const box = stage.getBoundingClientRect();

                    to = { x: event.clientX - box.left, y: event.clientY - box.top };

                    // It starts from where it rests, behind the text
                    at ??= { x: box.width * 0.5, y: box.height * 0.42 };

                    if (still) {
                        at = { ...to };
                        place();
                    } else if (!frame) {
                        frame = requestAnimationFrame(step);
                    }
                }, { passive: true });
            })();
        </script>

        {{-- Started here, before the first paint, so the mark is never seen
             at rest first. The guides are drawn in the middle of the screen:
             --lift is how far that is below where the mark comes to rest. --}}
        <script>
            (() => {
                const stage = document.querySelector('[data-intro]');
                const mark = stage.querySelector('.intro-mark');

                if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

                // The transforms sit on layers inside the mark, so the mark itself can be measured at any time
                const measure = () => {
                    const s = stage.getBoundingClientRect();
                    const m = mark.getBoundingClientRect();
                    if (!s.height) return;
                    stage.style.setProperty('--lift', (s.top + s.height / 2 - (m.top + m.height / 2)).toFixed(1) + 'px');
                };

                const animations = () => {
                    try { return stage.getAnimations({ subtree: true }); } catch (e) { return []; }
                };

                const skip = (event) => {
                    if (event.metaKey || event.ctrlKey || event.altKey) return;
                    animations().forEach((a) => { try { a.finish(); } catch (e) { /* already done */ } });
                };

                measure();
                stage.classList.add('is-playing');

                addEventListener('resize', measure);
                addEventListener('keydown', skip);
                stage.addEventListener('click', skip);
                document.fonts?.ready.then(measure);

                // Played once: at rest the page is plain markup again, so coming
                // back from the picker shows it as it is rather than replaying
                Promise.allSettled(animations().map((a) => a.finished)).then(() => {
                    stage.classList.remove('is-playing');
                    removeEventListener('resize', measure);
                    removeEventListener('keydown', skip);
                    stage.removeEventListener('click', skip);
                });
            })();
        </script>

        {{-- Step 2 — Template picker --}}
        <div x-show="step === 2" x-cloak class="mx-auto w-full max-w-6xl flex-1 px-6 py-12 lg:py-16">
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="s-microlabel">Step 2 of 2</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">Pick a starting point</h1>
                    <p class="mt-1.5 max-w-2xl text-[13.5px] text-soft">Whole sites, ready to edit. Its files are added to your app in <span class="font-mono text-[12px] text-ink/80">resources/designer</span> and <span class="font-mono text-[12px] text-ink/80">public/designer</span> — yours to keep, with or without Studio.</p>
                </div>
                <button @click="step = 1" class="s-btn-ghost shrink-0">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                    Back
                </button>
            </div>

            @if(count($templates) > 1)
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    @if(count($templates) > 6)
                        <label class="relative block w-full max-w-[280px]">
                            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="4.75" stroke="currentColor" stroke-width="1.5"/><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <input
                                type="search"
                                x-model="query"
                                x-ref="search"
                                @keydown.escape="query = ''; $el.blur()"
                                @keydown.window.slash="if (step === 2 && document.activeElement !== $refs.search && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) { $event.preventDefault(); $refs.search.focus() }"
                                class="s-input !pl-8"
                                placeholder="Search templates"
                                autocomplete="off"
                                aria-label="Search templates"
                            >
                        </label>
                    @endif

                    @if(count($categories) > 1)
                        <div class="s-seg" role="tablist" aria-label="Filter templates by category">
                            <button type="button" role="tab" class="s-seg-btn !w-auto px-3 text-[12.5px] font-medium" :class="filter === 'all' && 'is-active'" :aria-selected="filter === 'all'" @click="filter = 'all'">All</button>
                            @foreach($categories as $category => $label)
                                <button type="button" role="tab" class="s-seg-btn !w-auto px-3 text-[12.5px] font-medium" :class="filter === @js($category) && 'is-active'" :aria-selected="filter === @js($category)" @click="filter = @js($category)">{{ $label }}</button>
                            @endforeach
                        </div>
                    @endif

                    @if(count($themes) > 1)
                        <div class="s-seg" role="tablist" aria-label="Filter templates by theme">
                            <button type="button" role="tab" class="s-seg-btn !w-auto px-3 text-[12.5px] font-medium" :class="theme === 'all' && 'is-active'" :aria-selected="theme === 'all'" @click="theme = 'all'">Any theme</button>
                            @foreach($themes as $option)
                                <button type="button" role="tab" class="s-seg-btn !w-auto px-3 text-[12.5px] font-medium" :class="theme === @js($option) && 'is-active'" :aria-selected="theme === @js($option)" @click="theme = @js($option)">{{ \Illuminate\Support\Str::headline($option) }}</button>
                            @endforeach
                        </div>
                    @endif

                    @if($local && count(array_filter($templates, fn ($t) => ! $t['active'])) > 0)
                        <button
                            type="button"
                            class="s-btn-ghost !h-7 px-2.5 text-[12.5px]"
                            :class="showInactive && '!text-ink !bg-wash'"
                            :aria-pressed="showInactive"
                            @click="showInactive = ! showInactive"
                        >
                            <span x-text="showInactive ? 'Hide inactive' : 'Show inactive'">Show inactive</span>
                        </button>
                    @endif

                    <span class="text-xs text-faint tabular-nums" x-text="shown + ' of ' + offered + ' templates'">{{ count(array_filter($templates, fn ($t) => $t['active'])) }} templates</span>
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($templates as $key => $template)
                    <button
                        type="button"
                        x-show="matches(templates[{{ $loop->index }}])"
                        @click="selected = @js($key)"
                        @dblclick="selected = @js($key); apply()"
                        class="group relative flex flex-col overflow-hidden rounded-2xl border bg-raised text-left transition-all duration-150"
                        :class="selected === @js($key)
                            ? 'border-accent shadow-[0_0_0_3px_color-mix(in_srgb,#4c7dfa_25%,transparent)] -translate-y-0.5'
                            : 'border-line hover:border-line-strong hover:-translate-y-0.5'"
                    >
                        {{-- Preview --}}
                        <span
                            data-template-preview
                            class="pointer-events-none relative block w-full overflow-hidden bg-white"
                            style="aspect-ratio: 16/11"
                        >
                            {{-- Each template ships a picture of itself --}}
                            <img
                                src="{{ route('studio.preview.thumbnail', ['name' => $key]) }}"
                                loading="lazy"
                                alt="{{ $template['title'] }} preview"
                                class="absolute inset-0 h-full w-full object-cover object-top"
                            >
                            <span class="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-black/5 to-transparent"></span>

                            {{-- Selected check --}}
                            <span
                                x-show="selected === @js($key)"
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-75"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-2.5 top-2.5 flex h-6 w-6 items-center justify-center rounded-full bg-accent text-white shadow-lg"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                            </span>
                        </span>

                        {{-- Meta --}}
                        <span class="flex flex-1 flex-col border-t border-line p-4">
                            <span class="flex items-center gap-2">
                                <span class="text-[13.5px] font-semibold text-ink">{{ $template['title'] }}</span>
                                @unless($template['active'])
                                    <span class="s-chip !border-dashed !text-faint">inactive</span>
                                @endunless
                                @if($template['pages'] > 1)
                                    <span class="s-chip">{{ $template['pages'] }} pages</span>
                                @endif
                            </span>
                            <span class="mt-1 line-clamp-3 text-xs leading-relaxed text-soft">{{ $template['description'] }}</span>
                            @if($template['preview_url'])
                                <span class="mt-3 flex items-center gap-3 text-[12px]">
                                    <a
                                        href="{{ $template['preview_url'] }}"
                                        target="_blank"
                                        rel="noopener"
                                        @click.stop
                                        @dblclick.stop
                                        class="inline-flex items-center gap-1 font-medium text-soft transition-colors hover:text-ink"
                                    >
                                        Preview
                                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6h7m-3-3 3 3-3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                    @if($template['theme'])
                                        <span class="text-faint">{{ \Illuminate\Support\Str::headline($template['theme']) }}</span>
                                    @endif
                                </span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

            <div x-show="shown === 0" x-cloak class="py-20 text-center text-[13.5px] text-soft">
                <p>No templates match those filters.</p>
                <button type="button" @click="reset()" class="s-btn-ghost mt-3">Clear filters</button>
            </div>

        </div>

        {{-- Step 2 — pinned action bar (always visible while browsing templates) --}}
        <div
            x-show="step === 2"
            x-cloak
            class="sticky bottom-0 z-20 border-t border-line bg-shell/85 shadow-[0_-16px_40px_-16px_rgba(0,0,0,0.65)] backdrop-blur-xl"
        >
            <div class="mx-auto flex w-full max-w-6xl items-center gap-4 px-6 py-3.5">
                <p class="hidden text-xs text-faint sm:block">Tip: double-click a template to jump straight in.</p>

                <div class="ml-auto flex items-center gap-3">
                    <p class="text-[13px] text-soft">
                        <span class="text-faint">Selected:</span>
                        <span class="font-medium text-ink" x-text="selectedTitle"></span>
                    </p>
                    <button
                        @click="apply()"
                        :disabled="!selected || applying"
                        class="s-btn-primary !h-10 !px-6"
                    >
                        <span x-show="!applying">Use this template</span>
                        <span x-show="applying" x-cloak class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                            Setting up your site…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-studio::layouts.app>
