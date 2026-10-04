{{-- ============================================================ --}}
{{-- Design variations of a section                                --}}
{{-- ============================================================ --}}
{{-- Opened by the Ask AI menu's Variations ($store.studio.designVariations
     → studio:section-variations). A snapshot of the section is taken at
     desktop width, Codex draws redesign concepts from it (the image
     variations' job and stream — ImageVariationController), and the one
     chosen goes to the Assistant with the snapshot beside it: "redesign
     this to that". Nothing on the page changes here. --}}
<div
    x-data="{
        open: false,
        sectionId: null,
        title: '',
        snapshot: null,           // the snapshot's job: every generation copies its picture
        ratio: 1.6,               // the tiles' width / height
        prompt: '',
        count: 3,
        state: 'idle',            // snapping | idle | working | done | error | failed (no snapshot)
        job: null,
        images: [],               // file names, in the order they were drawn
        selected: null,
        activity: '',
        error: '',
        seconds: 0,
        stream: null,
        timer: null,
        defaultPrompt: @js(\Designer\Studio\Services\Assistant\ImageVariations::DEFAULT_SECTION_PROMPT),
        csrf: document.querySelector('meta[name=csrf-token]').content,
        urls: {
            snapshot: @js(route('studio.api.variations.snapshot')),
            start: @js(route('studio.api.variations.start')),
            stream: @js(route('studio.api.variations.stream', ['id' => '__ID__'])),
            stop: @js(route('studio.api.variations.stop', ['id' => '__ID__'])),
            // Root-relative: these travel to the Assistant as attachments
            image: @js(route('studio.api.variations.image', ['id' => '__ID__', 'file' => '__FILE__'], false)),
        },

        get busy() { return this.state === 'snapping' || this.state === 'working'; },
        get ready() { return !!this.snapshot && this.state !== 'snapping'; },
        get slots() { return Array.from({ length: ['working', 'done'].includes(this.state) ? Math.max(this.count, this.images.length) : 0 }, (_, i) => this.images[i] || null); },
        get elapsed() { return Math.floor(this.seconds / 60) + ':' + String(this.seconds % 60).padStart(2, '0'); },
        get now() { return this.snapshot ? this.src('source.png', this.snapshot) : ''; },
        src(file, job = this.job) { return this.urls.image.replace('__ID__', job).replace('__FILE__', file); },

        show(detail) {
            this.reset();
            Object.assign(this, { sectionId: detail.sectionId, title: '', snapshot: null, prompt: this.defaultPrompt, open: true });
            this.snap();
        },

        reset() {
            this.stream?.close();
            clearInterval(this.timer);
            Object.assign(this, { stream: null, timer: null, state: 'idle', job: null, images: [], selected: null, activity: '', error: '', seconds: 0 });
        },

        async post(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) throw new Error(data.message || 'Something went wrong. Try again.');
            return data;
        },

        // The picture everything starts from: the section alone, as the draft has it
        async snap() {
            const sectionId = this.sectionId;
            this.reset();
            this.state = 'snapping';

            try {
                const data = await this.post(this.urls.snapshot, { page: window.__studioPageSlug, section: sectionId });
                if (!this.open || this.sectionId !== sectionId) return;
                this.snapshot = data.id;
                this.title = data.title;
                this.state = 'idle';
                this.$nextTick(() => { this.$refs.prompt?.focus(); this.$refs.prompt?.select(); });
            } catch (e) {
                if (!this.open || this.sectionId !== sectionId) return;
                this.state = 'failed';
                this.error = e.message;
            }
        },

        async generate() {
            if (this.busy || !this.snapshot) return;
            this.reset();
            this.state = 'working';
            this.activity = 'Starting Codex…';
            this.timer = setInterval(() => this.seconds++, 1000);

            try {
                this.job = (await this.post(this.urls.start, { snapshot: this.snapshot, prompt: this.prompt.trim() || this.defaultPrompt, count: this.count })).id;
            } catch (e) {
                this.fail(e.message);
                return;
            }

            const stream = new EventSource(this.urls.stream.replace('__ID__', this.job));
            this.stream = stream;
            stream.addEventListener('activity', (e) => { this.activity = JSON.parse(e.data).label; });
            stream.addEventListener('image', (e) => {
                const file = JSON.parse(e.data).file;
                if (!this.images.includes(file)) this.images.push(file);
                if (this.selected === null) this.selected = file;
            });
            stream.addEventListener('done', (e) => { this.images = JSON.parse(e.data).images; this.settle('done'); });
            stream.addEventListener('error', (e) => {
                if (stream !== this.stream) return;
                const d = e.data ? JSON.parse(e.data) : { message: 'Lost the connection to Codex.' };
                // Whatever was drawn before it stopped is still worth choosing from
                if (this.images.length) { this.settle('done'); return; }
                this.fail(d.message);
            });
        },

        settle(state) {
            this.stream?.close();
            this.stream = null;
            clearInterval(this.timer);
            this.state = state;
            this.activity = '';
            if (this.selected === null) this.selected = this.images[0] ?? null;
        },

        fail(message) {
            this.settle('error');
            this.error = message || 'Codex did not return any concepts.';
        },

        async stop() {
            if (this.state !== 'working' || !this.job) return;
            this.activity = 'Stopping…';
            await fetch(this.urls.stop.replace('__ID__', this.job), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } }).catch(() => {});
        },

        // The Assistant builds it: the section as it is, and the concept to take it to
        redesign() {
            if (!this.selected || !this.job) return;
            const sectionId = this.sectionId;
            const current = this.src('source.png');
            const concept = this.src(this.selected);
            this.close();
            $store.studio.redesignSection(sectionId, current, concept);
        },

        close() {
            if (!this.open) return;
            if (this.state === 'working') this.stop();
            this.open = false;
            this.reset();
        },

        onKey(event) {
            if (!this.open) return;
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.close(); return; }
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                event.preventDefault();
                this.state === 'done' && this.selected && document.activeElement !== this.$refs.prompt ? this.redesign() : this.generate();
                return;
            }
            if (document.activeElement === this.$refs.prompt) return;
            if (/^[1-4]$/.test(event.key) && this.images[event.key - 1]) { this.selected = this.images[event.key - 1]; return; }
            if (event.key === 'Enter' && this.state === 'done' && this.selected) { event.preventDefault(); this.redesign(); }
        },
    }"
    @studio:section-variations.window="show($event.detail)"
    @keydown.window.capture="onKey($event)"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[96] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-label="Design variations of this section"
>
    <div class="s-modal-backdrop !z-[96]" x-show="open" x-transition.opacity.duration.200ms @click="close()"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 scale-[0.98]"
        class="s-modal s-vary !z-[97] flex max-h-[90vh] w-[900px] max-w-full flex-col overflow-hidden"
    >
        {{-- What is being redesigned --}}
        <div class="flex items-center gap-3 border-b border-line px-4 py-3">
            <div class="min-w-0 flex-1">
                <h2 class="flex items-center gap-1.5 text-[13px] font-semibold text-ink">
                    <svg class="h-3.5 w-3.5 text-accent" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.5a.75.75 0 0 1 .72.54l1.2 4.1a2.75 2.75 0 0 0 1.87 1.87l4.1 1.2a.75.75 0 0 1 0 1.44l-4.1 1.2a2.75 2.75 0 0 0-1.87 1.87l-1.2 4.1a.75.75 0 0 1-1.44 0l-1.2-4.1a2.75 2.75 0 0 0-1.87-1.87l-4.1-1.2a.75.75 0 0 1 0-1.44l4.1-1.2a2.75 2.75 0 0 0 1.87-1.87l1.2-4.1A.75.75 0 0 1 10 1.5Z"/></svg>
                    Design variations
                </h2>
                <p class="truncate text-[11px] text-faint" x-text="title || 'This section'"></p>
            </div>
            <button type="button" class="s-icon-btn" @click="close()" aria-label="Close">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            {{-- The prompt: one box, the count and the action on its bottom edge --}}
            <div class="p-4">
                <div class="s-vary-composer" :class="busy && 'is-busy'">
                    <textarea
                        x-ref="prompt"
                        x-model="prompt"
                        rows="2"
                        class="s-vary-prompt"
                        :placeholder="defaultPrompt"
                        :disabled="busy || !ready"
                        aria-label="What to explore"
                    ></textarea>
                    <div class="flex items-center gap-2 px-2 pb-2">
                        <div class="s-seg" role="radiogroup" aria-label="How many">
                            @foreach([2, 3, 4] as $n)
                                <button type="button" class="s-seg-btn !px-2.5" role="radio" :aria-checked="count === {{ $n }}" :class="count === {{ $n }} && 'is-active'" :disabled="busy" @click="count = {{ $n }}">{{ $n }}</button>
                            @endforeach
                        </div>
                        <span class="text-[11px] text-faint">concepts · drawn by Codex</span>
                        <span class="flex-1"></span>
                        <button type="button" class="s-btn-ghost" x-show="state === 'working'" x-cloak @click="stop()">Stop</button>
                        <button type="button" class="s-btn-primary" :class="state === 'done' && '!bg-transparent !text-ink ring-1 ring-inset ring-line-strong hover:!bg-wash'" x-show="state !== 'working'" :disabled="!ready" @click="generate()">
                            <span x-text="state === 'done' || state === 'error' ? 'Generate again' : 'Generate'"></span>
                            <kbd class="s-vary-kbd" x-show="state === 'idle'">⌘↵</kbd>
                        </button>
                    </div>
                </div>

                <p class="mt-2 flex items-start gap-1.5 text-[11.5px] leading-relaxed text-danger" x-show="state === 'error' || state === 'failed'" x-cloak>
                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    <span>
                        <span x-text="error"></span>
                        <button type="button" class="ml-1 underline underline-offset-2" x-show="state === 'failed'" @click="snap()">Try again</button>
                    </span>
                </p>
            </div>

            {{-- The section as it is, then a tile per concept asked for, filled as each is drawn --}}
            <div class="px-4 pb-4" x-show="state !== 'failed'">
                <div class="grid grid-cols-2 gap-2.5" role="listbox" aria-label="Concepts">
                    <a
                        class="s-vary-tile is-now"
                        :class="state === 'snapping' && 'is-empty is-waiting'"
                        :style="`aspect-ratio: ${ratio}`"
                        :href="now || null"
                        target="_blank"
                        rel="noopener"
                        title="The section as it is now — open full size"
                    >
                        <template x-if="now">
                            <img :src="now" alt="The section as it is now" class="s-vary-img is-design" @load="ratio = Math.min(2.4, Math.max(1.25, $event.target.naturalWidth / Math.max(1, $event.target.naturalHeight)))">
                        </template>
                        <span class="s-vary-index" x-text="state === 'snapping' ? 'Taking a picture…' : 'Now'"></span>
                    </a>
                    <template x-for="(file, i) in slots" :key="i">
                        <button
                            type="button"
                            class="s-vary-tile"
                            role="option"
                            :style="`aspect-ratio: ${ratio}`"
                            :class="{ 'is-selected': file && selected === file, 'is-empty': !file, 'is-waiting': !file && state === 'working' }"
                            :aria-selected="file && selected === file"
                            :disabled="!file"
                            x-show="file || state === 'working'"
                            @click="selected = file"
                            @dblclick="selected = file; redesign()"
                        >
                            <template x-if="file">
                                <img :src="src(file)" alt="" class="s-vary-img is-design">
                            </template>
                            <span class="s-vary-index" x-text="i + 1"></span>
                            <span class="s-vary-check" x-show="file && selected === file" aria-hidden="true">
                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 0 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Status on the left, the decision on the right --}}
        <div class="flex items-center gap-3 border-t border-line px-4 py-3">
            <p class="flex min-w-0 flex-1 items-center gap-2 text-[11.5px] text-soft">
                <template x-if="busy">
                    <span class="flex min-w-0 items-center gap-2">
                        <svg class="h-3 w-3 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                        <span class="truncate" x-text="state === 'snapping' ? 'Taking a picture of the section…' : (images.length ? `${images.length} of ${count} drawn` : activity)"></span>
                        <span class="shrink-0 font-mono text-[10.5px] tabular-nums text-faint" x-show="state === 'working'" x-text="elapsed"></span>
                    </span>
                </template>
                <template x-if="state === 'done'">
                    <span class="flex min-w-0 items-center gap-2 text-faint">
                        <span class="truncate">Pick one — the Assistant rebuilds the section to match it.</span>
                        <a class="shrink-0 text-soft underline underline-offset-2 hover:text-ink" x-show="selected" :href="selected ? src(selected) : null" target="_blank" rel="noopener">Open full size</a>
                    </span>
                </template>
                <template x-if="!busy && state !== 'done'">
                    <span class="truncate text-faint">Nothing on the page changes until you choose a concept.</span>
                </template>
            </p>
            <button type="button" class="s-btn-ghost" @click="close()">Cancel</button>
            <button type="button" class="s-btn-accent" :disabled="!selected || state === 'working'" @click="redesign()">
                Redesign to this
                <kbd class="s-vary-kbd is-on-accent" x-show="state === 'done'">↵</kbd>
            </button>
        </div>
    </div>
</div>
