<x-studio::layouts.iframe>
    @push('iframe-head')
        <style>
            /* ---- Designer Studio editing overlay (never shipped to production pages) ---- */
            .studio-section {
                position: relative;
            }

            .studio-section::after {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                z-index: 2147483000;
                transition: box-shadow 120ms ease;
            }

            .studio-section:hover::after,
            .studio-section.is-hinted::after {
                box-shadow: inset 0 0 0 1.5px rgba(76, 125, 250, 0.75);
            }

            .studio-section.is-selected::after {
                box-shadow: inset 0 0 0 2px #4c7dfa;
            }

            .studio-section [data-section-content] {
                cursor: default;
            }

            /* Name chip */
            .studio-chip {
                position: absolute;
                top: 0;
                left: 0;
                z-index: 2147483002;
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 3px 9px 4px;
                border-radius: 0 0 8px 0;
                background: #4c7dfa;
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: -0.01em;
                line-height: 1.4;
                opacity: 0;
                transform: translateY(-2px);
                transition: opacity 120ms ease, transform 120ms ease;
                pointer-events: none;
                white-space: nowrap;
            }

            .studio-section:hover .studio-chip,
            .studio-section.is-hinted .studio-chip,
            .studio-section.is-selected .studio-chip {
                opacity: 1;
                transform: translateY(0);
            }

            /* Floating toolbar */
            .studio-toolbar {
                position: absolute;
                top: 8px;
                right: 8px;
                z-index: 2147483003;
                display: flex;
                align-items: center;
                gap: 2px;
                padding: 3px;
                border-radius: 10px;
                background: rgba(12, 12, 14, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                opacity: 0;
                transform: translateY(-4px);
                transition: opacity 130ms ease, transform 130ms ease;
                pointer-events: none;
            }

            .studio-section:hover .studio-toolbar,
            .studio-section.is-selected .studio-toolbar {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }

            .studio-toolbar button {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                border: 0;
                border-radius: 7px;
                background: transparent;
                color: rgba(255, 255, 255, 0.65);
                cursor: pointer;
                transition: background 100ms ease, color 100ms ease;
                padding: 0;
            }

            .studio-toolbar button:hover {
                background: rgba(255, 255, 255, 0.12);
                color: #fff;
            }

            .studio-toolbar button.studio-danger:hover {
                background: rgba(243, 114, 114, 0.18);
                color: #f37272;
            }

            .studio-toolbar button:disabled {
                opacity: 0.3;
                pointer-events: none;
            }

            .studio-toolbar svg {
                width: 14px;
                height: 14px;
            }

            .studio-toolbar .studio-toolbar-sep {
                width: 1px;
                height: 14px;
                margin: 0 2px;
                background: rgba(255, 255, 255, 0.14);
            }

            /* Insert affordance between sections */
            .studio-insert {
                position: absolute;
                left: 0;
                right: 0;
                height: 28px;
                z-index: 2147483004;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 130ms ease;
            }

            .studio-insert:hover {
                opacity: 1;
            }

            .studio-insert--top { top: -14px; }
            .studio-insert--bottom { bottom: -14px; }

            /* Keep the very first insert zone inside the viewport */
            .studio-section:first-of-type .studio-insert--top { top: 4px; }

            .studio-insert::before {
                content: '';
                position: absolute;
                left: 12px;
                right: 12px;
                top: 50%;
                height: 2px;
                margin-top: -1px;
                border-radius: 2px;
                background: #4c7dfa;
                box-shadow: 0 0 12px rgba(76, 125, 250, 0.55);
            }

            .studio-insert button {
                position: relative;
                display: flex;
                align-items: center;
                gap: 5px;
                height: 26px;
                padding: 0 12px;
                border: 0;
                border-radius: 999px;
                background: #4c7dfa;
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 11.5px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 16px -2px rgba(76, 125, 250, 0.55);
                transition: transform 120ms ease, background 120ms ease;
            }

            .studio-insert button:hover {
                background: #3d6ef2;
                transform: scale(1.04);
            }

            .studio-insert svg {
                width: 12px;
                height: 12px;
            }

            /* Hidden sections — dimmed with stripes in the editor only */
            .studio-section.is-hidden [data-section-content] {
                opacity: 0.35;
                filter: grayscale(0.6);
            }

            .studio-section.is-hidden::before {
                content: '';
                position: absolute;
                inset: 0;
                z-index: 2147483001;
                pointer-events: none;
                background: repeating-linear-gradient(
                    -45deg,
                    rgba(120, 120, 135, 0.08) 0,
                    rgba(120, 120, 135, 0.08) 10px,
                    transparent 10px,
                    transparent 20px
                );
            }

            .studio-hidden-badge {
                position: absolute;
                top: 8px;
                left: 8px;
                z-index: 2147483002;
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 3px 8px;
                border-radius: 6px;
                background: rgba(12, 12, 14, 0.85);
                color: rgba(255, 255, 255, 0.85);
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 10.5px;
                font-weight: 600;
                pointer-events: none;
            }

            .studio-hidden-badge svg {
                width: 11px;
                height: 11px;
            }

            /* Empty page state */
            .studio-empty {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                background:
                    radial-gradient(circle at 50% 30%, rgba(76, 125, 250, 0.04), transparent 60%),
                    #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
            }

            .studio-empty-inner {
                text-align: center;
                padding: 24px;
                max-width: 380px;
            }

            .studio-empty-mark {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 52px;
                height: 52px;
                margin: 0 auto 18px;
                border-radius: 14px;
                background: #0b0b0d;
                box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.35);
            }

            .studio-empty h2 {
                margin: 0 0 6px;
                font-size: 17px;
                font-weight: 650;
                letter-spacing: -0.02em;
                color: #18181b;
            }

            .studio-empty p {
                margin: 0 0 20px;
                font-size: 13.5px;
                line-height: 1.6;
                color: #71717a;
            }

            .studio-empty button {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 38px;
                padding: 0 18px;
                border: 0;
                border-radius: 10px;
                background: #4c7dfa;
                color: #fff;
                font-size: 13.5px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 8px 24px -6px rgba(76, 125, 250, 0.55);
                transition: transform 120ms ease, background 120ms ease;
            }

            .studio-empty button:hover {
                background: #3d6ef2;
                transform: translateY(-1px);
            }

            .studio-empty svg {
                width: 14px;
                height: 14px;
            }
        </style>
    @endpush

    {{-- Section templates + variables for client-side re-rendering --}}
    <script>
        window.__studioPreview = {
            templates: @js(collect($sections)->pluck('html', 'id')->toArray()),
            variables: @js($componentVariables),
        };
    </script>

    @if(count($sections) > 0)
        @foreach($sections as $section)
            @php
                $rendered = '';
                try {
                    $rendered = \Illuminate\Support\Facades\Blade::render($section['html'], $componentVariables[$section['id']] ?? []);
                } catch (\Throwable $e) {
                    $rendered = '<div style="padding:48px 24px;text-align:center;font-family:ui-sans-serif,system-ui,sans-serif;color:#991b1b;background:#fef2f2;border:1px dashed #fecaca;">Section “' . e($section['ref']) . '” failed to render: ' . e($e->getMessage()) . '</div>';
                }
            @endphp

            <div
                data-section="{{ $section['id'] }}"
                class="studio-section {{ $section['hidden'] ? 'is-hidden' : '' }}"
                onclick="Studio.preview.select('{{ $section['id'] }}', event)"
            >
                {{-- Insert above --}}
                <div class="studio-insert studio-insert--top">
                    <button type="button" onclick="Studio.preview.addAt({{ $loop->index }}, event)">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                        Add section
                    </button>
                </div>

                {{-- Name chip --}}
                <span class="studio-chip">{{ $section['title'] }}</span>

                @if($section['hidden'])
                    <span class="studio-hidden-badge">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                        Hidden
                    </span>
                @endif

                {{-- Toolbar --}}
                <div class="studio-toolbar" onclick="event.stopPropagation()">
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'move-up', event)" title="Move up" {{ $loop->first ? 'disabled' : '' }}>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd"/></svg>
                    </button>
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'move-down', event)" title="Move down" {{ $loop->last ? 'disabled' : '' }}>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.53 13.53a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 1.06-1.06L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25Z" clip-rule="evenodd"/></svg>
                    </button>
                    <span class="studio-toolbar-sep"></span>
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'duplicate', event)" title="Duplicate (⌘D)">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    </button>
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'toggle-hidden', event)" title="{{ $section['hidden'] ? 'Show' : 'Hide' }}">
                        @if($section['hidden'])
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                        @else
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                        @endif
                    </button>
                    <span class="studio-toolbar-sep"></span>
                    <button type="button" class="studio-danger" onclick="Studio.preview.action('{{ $section['id'] }}', 'delete', event)" title="Delete (⌫)">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193v-.443A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Zm-1.586 4.914a.75.75 0 1 0-1.498.086l.5 8.5a.75.75 0 0 0 1.498-.086l-.5-8.5Zm4.67.086a.75.75 0 1 0-1.498-.086l-.5 8.5a.75.75 0 0 0 1.498.086l.5-8.5Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                {{-- Rendered section --}}
                <div data-section-content>{!! $rendered !!}</div>

                {{-- Insert below (last section only — other boundaries use the next section's top zone) --}}
                @if($loop->last)
                    <div class="studio-insert studio-insert--bottom">
                        <button type="button" onclick="Studio.preview.addAt({{ $loop->index + 1 }}, event)">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                            Add section
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="studio-empty">
            <div class="studio-empty-inner">
                <div class="studio-empty-mark">
                    <svg width="26" height="26" viewBox="0 0 32 32" fill="none">
                        <path d="M10 9.5h7.25a6.5 6.5 0 0 1 0 13H10v-13Z" stroke="#fff" stroke-width="2.5"/>
                        <circle cx="23.5" cy="23.5" r="2.5" fill="#4c7dfa"/>
                    </svg>
                </div>
                <h2>{{ $page->title }} is empty</h2>
                <p>Add your first section from the library — heroes, features, pricing, testimonials, and more.</p>
                <button type="button" onclick="Studio.preview.addAt(null, event)">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Add a section
                </button>
            </div>
        </div>
    @endif
</x-studio::layouts.iframe>
