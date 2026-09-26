{{-- The content card's header (44px): the sidebar toggle, the Preview /
     Edit / Code pill (only the active segment shows its label; the others
     collapse to icons behind hairlines that fade beside the active pill),
     the page pill dead-centre, and on the right the device toggle (one
     button cycling Desktop → Tablet → Phone), the open-in-new-tab link and
     Publish (the `actions` slot from home.blade.php). --}}
<header class="s-topbar" aria-label="Editor">
    {{-- Sidebar toggle — the inner bar previews the action on hover --}}
    <button
        type="button"
        class="s-tb-btn s-tip"
        :class="$store.studio.sidebar ? 'tgl-collapse' : 'tgl-expand'"
        :data-tip="$store.studio.sidebar ? 'Collapse sidebar  ⌘B' : 'Open sidebar  ⌘B'"
        aria-label="Toggle the sidebar"
        :aria-expanded="$store.studio.sidebar"
        @click="$store.studio.toggleSidebar()"
    >
        <svg class="h-[17px] w-[17px]" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.5498 2.30001C3.68785 2.30001 2.8612 2.64242 2.25171 3.25191C1.64221 3.8614 1.2998 4.68805 1.2998 5.55001C1.2998 6.41196 1.3 10.45 1.3 10.45C1.3 10.8768 1.38406 11.2994 1.54739 11.6937C1.71072 12.088 1.95011 12.4463 2.2519 12.7481C2.8614 13.3576 3.68805 13.7 4.55 13.7L11.4498 13.7C11.8766 13.7 12.2992 13.6159 12.6935 13.4526C13.0878 13.2893 13.4461 13.0499 13.7479 12.7481C14.0497 12.4463 14.2891 12.088 14.4524 11.6937C14.6157 11.2994 14.6998 10.8768 14.6998 10.45C14.6998 8.30212 14.6998 7.69789 14.6998 5.55C14.6998 5.12321 14.6157 4.70059 14.4524 4.30628C14.2891 3.91197 14.0497 3.5537 13.7479 3.25191C13.4461 2.95012 13.0878 2.71072 12.6935 2.54739C12.2992 2.38407 11.8766 2.3 11.4498 2.3L4.5498 2.30001ZM2.4998 5.50001C2.4998 4.96957 2.71052 4.46087 3.08559 4.08579C3.46066 3.71072 3.96937 3.50001 4.4998 3.50001H11.4998C12.0302 3.50001 12.5389 3.71072 12.914 4.08579C13.2891 4.46087 13.4998 4.96957 13.4998 5.50001V10.5C13.4998 11.0304 13.2891 11.5391 12.914 11.9142C12.5389 12.2893 12.0302 12.5 11.4998 12.5H4.4998C3.96937 12.5 3.46066 12.2893 3.08559 11.9142C2.71052 11.5391 2.4998 11.0304 2.4998 10.5V5.50001Z"/>
            <rect class="sidebar-toggle-bar" x="3.9" y="5" width="4.5" height="6" rx="0.75"/>
        </svg>
    </button>

    {{-- Preview / Edit / Code --}}
    @php
        $segments = [
            ['preview', 'Preview', '<circle cx="12" cy="12" r="9"/><path d="M3.5 9h17M3.5 15h17M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>', 'Preview — browse the site as a visitor'],
            ['edit', 'Edit', '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>', 'Edit — click anything on the page to change it'],
            ['code', 'Code', '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>', 'Code — edit the site\'s source files'],
        ];
    @endphp
    <div class="s-mode" role="tablist" aria-label="Editor mode">
        @foreach($segments as $index => [$key, $label, $icon, $hint])
            @if($index > 0)
                <span
                    class="s-mode-sep"
                    :class="($store.studio.mode === '{{ $key }}' || $store.studio.mode === '{{ $segments[$index - 1][0] }}') && 'is-hidden'"
                    @if($key === 'code') x-show="$store.studio.codeAvailable" x-cloak @endif
                    aria-hidden="true"
                ></span>
            @endif
            <button
                type="button"
                class="s-mode-btn"
                role="tab"
                :class="$store.studio.mode === '{{ $key }}' && 'is-active'"
                :aria-selected="$store.studio.mode === '{{ $key }}'"
                aria-label="{{ $label }} mode"
                title="{{ $hint }}"
                @click="$store.studio.setMode('{{ $key }}')"
                @if($key === 'code') x-show="$store.studio.codeAvailable" x-cloak @endif
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                <span x-show="$store.studio.mode === '{{ $key }}'">{{ $label }}</span>
            </button>
        @endforeach
    </div>

    {{-- The page pill, dead-centre over the whole bar --}}
    <div class="s-topbar-center" x-show="$store.studio.canvasVisible">
        @include('studio::partials.page-pill')
    </div>

    <div class="flex-1"></div>

    {{-- Right: device · open in a new tab · Publish --}}
    <div class="flex shrink-0 items-center gap-1">
        {{-- One responsive button: click cycles Desktop → Tablet → Phone.
             The icon is the current device; the title names the next one. --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            :class="$store.studio.device !== 'desktop' && 'is-accent'"
            x-show="$store.studio.canvasVisible"
            :data-tip="({ desktop: 'Desktop — switch to Tablet  ⌥2', tablet: 'Tablet · 768px — switch to Phone  ⌥3', mobile: 'Phone · 390px — switch to Desktop  ⌥1' })[$store.studio.device]"
            :aria-label="'Preview size: ' + $store.studio.device"
            @click="$store.studio.cycleDevice()"
        >
            <svg x-show="$store.studio.device === 'desktop'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
            <svg x-show="$store.studio.device === 'tablet'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            <svg x-show="$store.studio.device === 'mobile'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3"/></svg>
        </button>

        {{-- Code mode: bring the live preview back beside the editor --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            x-show="$store.studio.mode === 'code'"
            x-cloak
            :class="$store.studio.codeSplit && 'is-active'"
            :data-tip="$store.studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code'"
            aria-label="Toggle the preview beside the code"
            @click="$store.studio.toggleCodeSplit()"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><rect x="3" y="4.5" width="18" height="15" rx="2.5"/><path stroke-linecap="round" d="M3 9h18M6.2 6.75h.01M8.7 6.75h.01M11.2 6.75h.01"/></svg>
        </button>

        {{-- Open the draft (or live) page in a new tab --}}
        @if($openUrl)
            <a
                href="{{ $openUrl }}"
                target="_blank"
                rel="noopener"
                class="s-tb-btn s-tip"
                data-tip="{{ $openLabel }}"
                aria-label="{{ $openLabel }}"
            >
                <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 14 21 3"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </a>
        @endif

        {{ $actions ?? '' }}
    </div>
</header>
