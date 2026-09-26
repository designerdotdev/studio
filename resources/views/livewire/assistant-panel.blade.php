{{-- The Assistant: a column on the right of the site (developer mode),
     toggled from the top bar or ⌘J. Header, transcript, composer. --}}
@php
    $engines = $this->engines;
    $thread = $this->thread;
    $messages = $thread['messages'] ?? [];
    $anyEngine = collect($engines)->contains(fn ($e) => $e['ok']);
@endphp
<div
    class="s-chat flex h-full min-h-0 flex-col"
    :class="{
        'is-open': true,
        'is-picking': picking,
        'is-busy': busy,
    }"
    x-data="{
        urls: {
            turn: @js(route('studio.api.assistant.turn')),
            stream: @js(route('studio.api.assistant.stream', ['turn' => '__TURN__'])),
            stop: @js(route('studio.api.assistant.stop', ['turn' => '__TURN__'])),
        },
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        page: @js($pageSlug),

        prompt: '',
        busy: false,
        turn: null,
        source: null,
        live: { text: '', activity: null, files: [] },
        element: null,          // {sectionId, ref, path, tag, text, field, itemIndex, subKey, source}
        picking: false,
        // Ask reads and answers; Build edits files. Remembered.
        mode: localStorage.getItem('studio.chat-mode') === 'build' ? 'build' : 'ask',
        // Images from the media library, sent with the next message
        attachments: [],
        attaching: false,
        count: @js(count($messages)),


        init() {
            window.addEventListener('studio:element-selected', (e) => {
                this.element = e.detail;
                this.setPicking(false);
                $store.studio.setAssistant(true);
                this.$nextTick(() => this.$refs.composer?.focus());
            });
            // Esc (in the editor or the canvas) and the banner's Cancel
            window.addEventListener('studio:pick-cancel', () => { if (this.picking) this.setPicking(false) });
            this.$watch('busy', () => this.$nextTick(() => this.scrollToEnd()));
            this.$watch('$store.studio.assistantOpen', (open) => { if (open) this.$nextTick(() => { this.scrollToEnd(); this.grow(); }) });
            this.$nextTick(() => { this.scrollToEnd(); this.grow(); });
        },

        scrollToEnd() { const el = this.$refs.log; if (el) el.scrollTop = el.scrollHeight; },

        // The textarea grows with the message, up to a few lines. Hidden
        // (another rail panel is showing) it cannot be measured — leave it
        // alone, it is re-measured the moment it shows.
        grow() {
            const el = this.$refs.composer;
            if (!el || !el.offsetParent) return;
            el.style.height = 'auto';
            const h = el.scrollHeight;
            el.style.height = h > 0 ? Math.min(h, 168) + 'px' : '';
        },

        setMode(mode) {
            this.mode = mode === 'build' ? 'build' : 'ask';
            localStorage.setItem('studio.chat-mode', this.mode);
            this.$refs.composer?.focus();
        },

        togglePick() { this.setPicking(!this.picking) },

        // The pick tool is one state in two documents: the canvas draws the
        // dashed outline and reports the click; the editor shows the banner
        // (studio:pick) and cancels on Esc.
        setPicking(on) {
            this.picking = on;
            window.dispatchEvent(new CustomEvent('studio:to-iframe', { detail: { type: 'studio:element-select', on } }));
            window.dispatchEvent(new CustomEvent('studio:pick', { detail: { on } }));
        },

        async attach() {
            if (this.attaching || this.attachments.length >= 6) return;
            this.attaching = true;
            try {
                const url = await window.Studio.mediaPick();
                if (url && !this.attachments.includes(url)) this.attachments.push(url);
            } finally {
                this.attaching = false;
                this.$nextTick(() => this.$refs.composer?.focus());
            }
        },

        use(text) { this.prompt = text; this.$nextTick(() => { this.grow(); this.$refs.composer?.focus(); }); },

        onEscape() {
            if (this.picking) { this.setPicking(false); return; }
            this.$refs.composer?.blur();
        },

        async send() {
            const text = this.prompt.trim();
            if (!text || this.busy) return;

            const threadId = await $wire.ensureThread();
            const section = $wire.get('selected');
            const context = { page: this.page };
            if (section) context.section = section;
            if (this.element) context.element = {
                path: this.element.path,
                tag: this.element.tag,
                text: this.element.text,
                field: this.element.field,
                itemIndex: this.element.itemIndex,
                subKey: this.element.subKey,
                source: this.element.source,
            };
            if (this.attachments.length) context.attachments = [...this.attachments];

            this.busy = true;
            $store.studio.chatBusy = true;
            this.live = { text: '', activity: 'Starting…', files: [] };
            this.prompt = '';
            this.$nextTick(() => this.grow());
            const element = this.element;
            const attachments = this.attachments;
            this.element = null;
            this.attachments = [];

            let data;
            try {
                const response = await fetch(this.urls.turn, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ thread: threadId, engine: $wire.engine, prompt: text, context, mode: this.mode }),
                });
                data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Could not start the assistant.');
            } catch (e) {
                this.busy = false;
                $store.studio.chatBusy = false;
                this.prompt = text;
                this.element = element;
                this.attachments = attachments;
                this.$nextTick(() => this.grow());
                window.Studio.toast(e.message, 'error');
                return;
            }

            await $wire.$refresh();
            this.count += 1;
            this.turn = data.turn;
            this.listen(data.turn);
        },

        listen(turn) {
            const source = new EventSource(this.urls.stream.replace('__TURN__', turn));
            this.source = source;

            source.addEventListener('text', (e) => { this.live.text += JSON.parse(e.data).delta; this.scrollToEnd(); });
            source.addEventListener('activity', (e) => { this.live.activity = JSON.parse(e.data).label; });
            source.addEventListener('files', (e) => { this.live.files = JSON.parse(e.data).paths; });
            source.addEventListener('done', (e) => { const d = JSON.parse(e.data); this.finish(false, d); });
            source.addEventListener('error', (e) => {
                if (e.data) { const d = JSON.parse(e.data); this.finish(true, d); return; }
                // Connection dropped without a server event
                if (this.busy) this.finish(true, { message: 'Lost the connection to the assistant.' });
            });
        },

        async finish(failed, d) {
            this.source?.close();
            this.source = null;
            this.busy = false;
            $store.studio.chatBusy = false;
            this.turn = null;
            this.live.activity = null;

            const files = d.files || this.live.files || [];
            await $wire.$refresh();
            this.count += 1;
            this.live = { text: '', activity: null, files: [] };
            this.$nextTick(() => this.scrollToEnd());

            if (failed) {
                window.Studio.toast(d.message || 'The assistant failed.', 'error');
            }

            // EditorPanel re-syncs the site, then reloads the canvas itself
            if (files.length || !failed) {
                window.Livewire?.dispatch('studio:code-saved');
            }

            if (files.length) {
                window.Studio.toast(`Assistant changed ${files.length} ${files.length === 1 ? 'file' : 'files'}`, 'success');
            }
        },

        async stop() {
            if (!this.turn) return;
            await fetch(this.urls.stop.replace('__TURN__', this.turn), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
        },
    }"
    @studio:focus-chat.window="$nextTick(() => $refs.composer?.focus())"
    x-effect="$store.studio.assistantOpen; $nextTick(() => grow())"
>
    {{-- The conversation: the header and the transcript --}}
    <div class="s-chat-thread">
        <div class="s-chat-thread-inner">
            {{-- Header --}}
            <div class="s-chat-head">
                <p class="s-microlabel flex-1">Assistant</p>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="s-icon-btn" title="Conversation history" aria-label="Conversation history" @click="open = !open">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="s-pop absolute right-0 top-full z-30 mt-1 w-64 origin-top-right" role="menu">
                        <p class="s-microlabel px-2.5 pb-1 pt-2">Conversations</p>
                        <div class="max-h-64 overflow-y-auto">
                            @forelse($this->threads as $item)
                                <div wire:key="thread-{{ $item['id'] }}" class="group flex items-center">
                                    <button type="button" class="s-menu-item min-w-0 flex-1 {{ $item['id'] === $threadId ? 'bg-wash !text-ink' : '' }}" @click="open = false" wire:click="open('{{ $item['id'] }}')">
                                        <span class="min-w-0 flex-1 truncate">{{ $item['title'] }}</span>
                                        <span class="shrink-0 text-[10px] text-faint">{{ $item['count'] }}</span>
                                    </button>
                                    <button type="button" class="s-icon-btn !h-6 !w-6 shrink-0 opacity-0 hover:!text-danger group-hover:opacity-100" title="Delete" wire:click="delete('{{ $item['id'] }}')" wire:confirm="Delete this conversation?">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                                    </button>
                                </div>
                            @empty
                                <p class="px-2.5 py-3 text-xs text-faint">No conversations yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <button type="button" class="s-icon-btn" title="New conversation" aria-label="New conversation" wire:click="newThread">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                </button>

            </div>

            @if(!$anyEngine)
                {{-- No CLI installed --}}
                <div class="s-chat-log space-y-3 text-[12.5px] leading-relaxed text-soft" x-ref="log">
                    <p class="text-ink">The Assistant runs an AI coding CLI installed on this machine, using its own file tools over this project.</p>
                    @foreach($engines as $engine)
                        <div class="rounded-lg border border-line bg-raised p-3">
                            <p class="font-medium text-ink">{{ $engine['label'] }} <span class="text-faint">— not found</span></p>
                            <p class="mt-1 text-[11.5px] text-faint">{{ $engine['hint'] }}</p>
                        </div>
                    @endforeach
                    <p class="text-[11px] text-faint">Pin a path with <code class="font-mono">STUDIO_CLAUDE_BIN</code> / <code class="font-mono">STUDIO_CODEX_BIN</code> if the binary is somewhere unusual.</p>
                </div>
            @else
                {{-- Transcript --}}
                <div class="s-chat-log" x-ref="log">
                    @if(empty($messages) && !$thread)
                        <div class="px-1 py-6 text-center">
                            <p class="text-[13px] text-ink">What should change on this page?</p>
                            <p class="mt-1 text-[11.5px] leading-relaxed text-faint">Describe an edit in plain words. To point at something, select a section first, or use <span class="text-soft">Select on page</span> and click the exact element. Ask answers questions; Build makes the change.</p>
                        </div>
                    @endif

                    <div class="space-y-3">
                        @foreach($messages as $message)
                            <div wire:key="msg-{{ $message['id'] }}">
                                @if($message['role'] === 'user')
                                    <div class="s-chat-msg-user">
                                        @if(!empty($message['context']['element']['path']) || !empty($message['context']['section']['ref']) || ($message['context']['mode'] ?? '') === 'ask')
                                            <span class="mb-1.5 flex flex-wrap items-center gap-1">
                                                @if(($message['context']['mode'] ?? '') === 'ask')
                                                    <span class="inline-flex items-center rounded-md bg-black/25 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide">Ask</span>
                                                @endif
                                                @if(!empty($message['context']['element']['path']) || !empty($message['context']['section']['ref']))
                                                    <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-black/25 px-1.5 py-0.5 font-mono text-[10px]">
                                                        <svg class="h-2.5 w-2.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5a.75.75 0 0 1 1.14-.64l12 7a.75.75 0 0 1-.09 1.33l-4.2 1.72-1.72 4.2a.75.75 0 0 1-1.33.09l-7-12A.75.75 0 0 1 3 3.5Z"/></svg>
                                                        <span class="truncate">{{ $message['context']['section']['ref'] ?? '' }}{{ !empty($message['context']['element']['path']) ? ' › ' . $message['context']['element']['path'] : '' }}</span>
                                                    </span>
                                                @endif
                                            </span>
                                        @endif
                                        <p class="whitespace-pre-wrap break-words">{{ $message['text'] }}</p>
                                        @if(!empty($message['context']['attachments']))
                                            <span class="mt-2 flex flex-wrap gap-1.5">
                                                @foreach($message['context']['attachments'] as $attachment)
                                                    <img src="{{ $attachment }}" alt="" class="h-12 w-12 rounded-md object-cover ring-1 ring-white/20">
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div class="s-chat-msg {{ $message['failed'] ? 'text-danger' : '' }}">
                                        <p class="whitespace-pre-wrap break-words">{{ $message['text'] !== '' ? $message['text'] : ($message['failed'] ? 'The assistant failed.' : 'Done.') }}</p>
                                        @if(!empty($message['files']) || !empty($message['activity']))
                                            <details class="mt-2 border-t border-line pt-1.5 text-[11px] text-faint">
                                                <summary class="cursor-pointer select-none hover:text-soft">More details{{ !empty($message['files']) ? ' · ' . count($message['files']) . ' ' . Str::plural('file', count($message['files'])) : '' }}</summary>
                                                <ul class="mt-1.5 space-y-0.5 font-mono text-[10.5px]">
                                                    @foreach($message['files'] as $file)
                                                        <li class="truncate text-soft">{{ $file }}</li>
                                                    @endforeach
                                                    @foreach(array_slice($message['activity'], 0, 12) as $activity)
                                                        <li class="truncate">{{ $activity['label'] ?? '' }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        {{-- The reply being streamed --}}
                        <div x-show="busy" x-cloak class="s-chat-msg">
                            <p class="whitespace-pre-wrap break-words" x-text="live.text" x-show="live.text"></p>
                            <p class="flex items-center gap-1.5 text-[11px] text-faint" :class="live.text && 'mt-1.5'" x-show="live.activity">
                                <svg class="h-3 w-3 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                <span class="truncate" x-text="live.activity"></span>
                            </p>
                        </div>
                    </div>

                    {{-- Suggestions on an empty thread --}}
                    @if(empty($messages))
                        <div class="mt-4 space-y-1.5" x-show="!busy">
                            @foreach($this->suggestions as $suggestion)
                                <button type="button" class="s-suggestion" @click="use(@js($suggestion))">{{ $suggestion }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($anyEngine)
        <div class="s-chat-fold">

            {{-- Composer --}}
            <div class="s-chat-composer">
                {{-- Context chips: the selected section (server-rendered), a picked element, attached images --}}
                <div class="s-chat-chips" x-show="@js((bool) $selected) || element || attachments.length" data-context-chips>
                    <span class="s-chat-chip" x-show="@js((bool) $selected) || element" data-context-chip>
                        <svg class="h-2.5 w-2.5 shrink-0 text-accent" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5a.75.75 0 0 1 1.14-.64l12 7a.75.75 0 0 1-.09 1.33l-4.2 1.72-1.72 4.2a.75.75 0 0 1-1.33.09l-7-12A.75.75 0 0 1 3 3.5Z"/></svg>
                        @if($selected)
                            <span class="truncate">{{ $selected['title'] }}<span x-show="element" x-text="element ? ' › ' + element.path : ''"></span></span>
                        @else
                            <span class="truncate" x-text="element?.path || ''"></span>
                        @endif
                        <button type="button" class="s-chat-chip-x" x-show="element" @click="element = null" title="Clear element" aria-label="Clear element">×</button>
                    </span>
                    <template x-for="(url, i) in attachments" :key="url">
                        <span class="s-chat-chip is-image" :title="url">
                            <img :src="url" alt="" class="h-4 w-4 rounded-sm object-cover">
                            <span class="truncate" x-text="url.split('/').pop()"></span>
                            <button type="button" class="s-chat-chip-x" @click="attachments.splice(i, 1)" title="Remove" aria-label="Remove attachment">×</button>
                        </span>
                    </template>
                </div>

                <div class="s-chat-field">
                    <textarea
                        x-ref="composer"
                        x-model="prompt"
                        rows="1"
                        class="s-chat-input"
                        :placeholder="mode === 'ask' ? 'Ask about this page…' : 'Describe a change…'"
                        @input="grow()"
                        @keydown.enter.prevent="if (!$event.shiftKey) send(); else { prompt += '\n'; $nextTick(() => grow()) }"
                        @keydown.escape.stop="onEscape()"
                        :disabled="busy"
                        aria-label="Message the assistant"
                    ></textarea>

                    <div class="s-chat-tools">
                        {{-- The pick tool. Labelled, and violet while armed, so it
                             never reads as Edit mode's blue selection. --}}
                        <button
                            type="button"
                            class="s-pick-btn"
                            :class="picking && 'is-on'"
                            :title="picking ? 'Stop selecting (Esc)' : 'Click an element on the page to add it to the chat'"
                            :aria-pressed="picking"
                            @click="togglePick()"
                        >
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="5.5"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/></svg>
                            <span class="s-chat-tool-label" x-text="picking ? 'Selecting…' : 'Select on page'"></span>
                        </button>

                        {{-- Attach an image from the media library --}}
                        <button type="button" class="s-chat-tool" :class="attaching && 'is-on'" title="Attach an image from the library" aria-label="Attach an image" @click="attach()" :disabled="busy">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m20 11.5-8.6 8.6a5 5 0 0 1-7.1-7.1l9-9a3.3 3.3 0 0 1 4.7 4.7l-9 9a1.7 1.7 0 0 1-2.4-2.4l8.3-8.3"/></svg>
                        </button>

                        {{-- Ask reads and answers; Build edits the site --}}
                        <div class="s-chat-seg" role="radiogroup" aria-label="What the assistant may do">
                            <button type="button" class="s-chat-seg-btn" :class="mode === 'ask' && 'is-active'" role="radio" :aria-checked="mode === 'ask'" @click="setMode('ask')" title="Ask — answers from the files, changes nothing">Ask</button>
                            <button type="button" class="s-chat-seg-btn is-build" :class="mode === 'build' && 'is-active'" role="radio" :aria-checked="mode === 'build'" @click="setMode('build')" title="Build — edits the site's files">Build</button>
                        </div>

                        {{-- Engine picker --}}
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" class="s-chat-tool is-text" @click="open = !open" title="Which CLI runs the turn">
                                <span class="whitespace-nowrap">{{ $engines[$engine]['label'] ?? 'Engine' }}</span>
                                <svg class="h-3 w-3 text-faint" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </button>
                            <div x-show="open" x-cloak class="s-pop absolute bottom-full left-0 z-30 mb-1 w-52 origin-bottom-left">
                                @foreach($engines as $name => $item)
                                    <button type="button" class="s-menu-item {{ $name === $engine ? '!text-ink' : '' }} {{ $item['ok'] ? '' : 'opacity-50' }}" @click="open = false" wire:click="setEngine('{{ $name }}')" {{ $item['ok'] ? '' : 'disabled' }} title="{{ $item['hint'] }}">
                                        <span class="flex-1">{{ $item['label'] }}</span>
                                        @unless($item['ok'])<span class="text-[10px] text-faint">not installed</span>@endunless
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <span class="flex-1"></span>


                        <button type="button" x-show="busy" x-cloak class="s-btn-ghost !h-7 !px-2 !text-[11px]" @click="stop()">Stop</button>
                        <button type="button" x-show="!busy" class="s-chat-send" :disabled="!prompt.trim()" @click="send()" :title="'Send (Enter) — ' + (mode === 'ask' ? 'Ask' : 'Build')" aria-label="Send">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
