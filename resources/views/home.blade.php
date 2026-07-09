@php
    $homeSlug = config('studio.page_routing.home_slug', 'home');
    $routingEnabled = config('studio.page_routing.enabled', true);
    $liveUrl = $routingEnabled ? \Designer\Studio\Support\SiteUrls::pageUrl($page->slug) : null;
    $totalComponents = $library->flatten(1)->count();
@endphp

<x-studio::layouts.app>
    <x-slot:title>{{ $page->title }} — Designer Studio</x-slot:title>

    {{-- ============================================================ --}}
    {{-- Topbar                                                        --}}
    {{-- ============================================================ --}}
    <x-slot:topbar>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('studio', {
                    device: 'desktop',
                    widths: { desktop: '100%', tablet: '768px', mobile: '390px' },
                    sidebar: localStorage.getItem('studio.sidebar') !== '0',
                    toggleSidebar() {
                        this.sidebar = !this.sidebar;
                        localStorage.setItem('studio.sidebar', this.sidebar ? '1' : '0');
                    },
                    devMode: localStorage.getItem('studio.devmode') === '1',
                    toggleDevMode() {
                        this.devMode = !this.devMode;
                        localStorage.setItem('studio.devmode', this.devMode ? '1' : '0');
                    },
                });
            });

            window.addEventListener('studio:toast', (event) => {
                const { message, type, action } = event.detail;

                let toastAction = null;

                if (action?.dispatch) {
                    toastAction = { label: action.label, onClick: () => window.Livewire?.dispatch(action.dispatch) };
                } else if (action?.reload) {
                    toastAction = { label: action.label, onClick: () => window.location.reload() };
                }

                window.Studio?.toast(message, type || 'success', undefined, toastAction);
            });
        </script>

        {{-- Menu --}}
        <div
            class="relative"
            x-data="{
                open: false,

                async duplicatePage() {
                    this.open = false;
                    try {
                        const response = await fetch(@js(route('studio.api.pages.duplicate', ['slug' => $page->slug])), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = data.editor_url;
                            return;
                        }
                        window.Studio.toast(data.error || 'Could not duplicate the page', 'error');
                    } catch (e) {
                        window.Studio.toast('Could not duplicate the page', 'error');
                    }
                },

                exportBlade() {
                    this.open = false;
                    window.Studio.exportBlade(@js(route('studio.api.generate.page', ['slug' => $page->slug])));
                }
            }"
            @click.outside="open = false"
            @keydown.escape.window="open = false"
        >
            <button @click="open = !open" class="s-box-btn s-logo-btn" :class="open && 'is-open'" title="Menu" aria-label="Menu">
                <svg class="s-logo-btn-logo h-[15px] w-auto text-ink" viewBox="0 0 72 75" fill="none"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/></svg>
                <svg class="s-logo-btn-menu h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" d="M4 6.5h16M4 12h16M4 17.5h16"/></svg>
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="s-pop absolute left-0 top-full mt-1.5 w-60 origin-top-left"
            >
                <div class="flex items-center gap-2.5 px-2.5 pb-2 pt-2.5 -translate-y-0.5">
                    <svg class="h-[17px] w-auto -translate-y-0.5 text-neutral-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 72 75" fill="none"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"></path></svg>
                    <span class="text-[13px] font-semibold text-ink">Designer Studio</span>
                </div>

                <div class="s-divider mb-1"></div>

                <button @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    New page…
                </button>
                <button @click="open = false; window.Livewire?.dispatch('studio:new-layout')" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    New layout…
                </button>
                <button @click="duplicatePage()" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    Duplicate this page
                </button>

                <div class="s-divider my-1"></div>

                <button @click="exportBlade()" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                    Export Blade file
                </button>
                @if(\Designer\Studio\Support\DevMode::enabled())
                    <button @click="$store.studio.toggleDevMode()" class="s-menu-item justify-between" title="Edit section .html and .yml source files from the editor">
                        <span class="flex items-center gap-2.5">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd"/></svg>
                            Dev mode
                        </span>
                        <span class="s-chip" :class="$store.studio.devMode && '!border-accent/50 !text-accent'" x-text="$store.studio.devMode ? 'On' : 'Off'"></span>
                    </button>
                @endif
                @if($liveUrl)
                    <a href="{{ $liveUrl }}" target="_blank" class="s-menu-item">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        View live site
                    </a>
                @endif
            </div>
        </div>

        {{-- Sidebar reveal — slides out from under the menu button when collapsed --}}
        <div x-data class="s-reveal" :class="!$store.studio.sidebar && 'is-out'" :inert="$store.studio.sidebar">
            <button
                @click="$store.studio.toggleSidebar()"
                class="s-box-btn s-panel-expand"
                title="Show sidebar"
                aria-label="Show sidebar"
            >
                <svg class="h-4 w-4" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4.5498 2.30001C3.68785 2.30001 2.8612 2.64242 2.25171 3.25191C1.64221 3.8614 1.2998 4.68805 1.2998 5.55001C1.2998 6.41196 1.3 10.45 1.3 10.45C1.3 10.8768 1.38406 11.2994 1.54739 11.6937C1.71072 12.088 1.95011 12.4463 2.2519 12.7481C2.8614 13.3576 3.68805 13.7 4.55 13.7L11.4498 13.7C11.8766 13.7 12.2992 13.6159 12.6935 13.4526C13.0878 13.2893 13.4461 13.0499 13.7479 12.7481C14.0497 12.4463 14.2891 12.088 14.4524 11.6937C14.6157 11.2994 14.6998 10.8768 14.6998 10.45C14.6998 8.30212 14.6998 7.69789 14.6998 5.55C14.6998 5.12321 14.6157 4.70059 14.4524 4.30628C14.2891 3.91197 14.0497 3.5537 13.7479 3.25191C13.4461 2.95012 13.0878 2.71072 12.6935 2.54739C12.2992 2.38407 11.8766 2.3 11.4498 2.3L4.5498 2.30001ZM2.4998 5.50001C2.4998 4.96957 2.71052 4.46087 3.08559 4.08579C3.46066 3.71072 3.96937 3.50001 4.4998 3.50001H11.4998C12.0302 3.50001 12.5389 3.71072 12.914 4.08579C13.2891 4.46087 13.4998 4.96957 13.4998 5.50001V10.5C13.4998 11.0304 13.2891 11.5391 12.914 11.9142C12.5389 12.2893 12.0302 12.5 11.4998 12.5H4.4998C3.96937 12.5 3.46066 12.2893 3.08559 11.9142C2.71052 11.5391 2.4998 11.0304 2.4998 10.5V5.50001Z"></path>
                    <rect class="s-panel-icon-bar" x="3.9" y="5" width="4.5" height="6" rx="0.75"></rect>
                </svg>
            </button>
        </div>

        {{-- Browser navigation --}}
        <div x-data class="flex shrink-0 items-center">
            <button class="s-nav-btn" title="Back" aria-label="Back" @click="history.back()">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 5.5 8 12l6.5 6.5"/></svg>
            </button>
            <button class="s-nav-btn" title="Forward" aria-label="Forward" @click="history.forward()">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 5.5 16 12l-6.5 6.5"/></svg>
            </button>
            <button class="s-nav-btn" title="Reload preview" aria-label="Reload preview" @click="window.dispatchEvent(new CustomEvent('studio:refresh-preview'))">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 3.75V6.9a.6.6 0 0 1-.6.6h-3.15"/></svg>
            </button>
        </div>

        {{-- URL bar — centered, capped width, doubles as the page switcher --}}
        @php $displayHost = parse_url(url('/'), PHP_URL_HOST); @endphp
        <div class="flex min-w-0 flex-1 justify-center px-1">
        <div
            class="relative w-full min-w-0 max-w-xl"
            x-data="{
                state: 'idle',
                pagesOpen: false,
                slug: @js($page->slug),
                homeSlug: @js($homeSlug),
                get path() { return this.slug === this.homeSlug ? '' : this.slug },
                get liveUrl() { return @js(rtrim(url('/'), '/')) + '/' + this.path },
                get openUrl() {
                    @if($draftMode)
                    return @js(route('studio.preview.home')) + (this.path ? '/' + this.path : '');
                    @else
                    return this.liveUrl;
                    @endif
                },
            }"
            @studio:status.window="state = $event.detail.state"
            @studio:page-meta-updated.window="
                if ($event.detail.slug && $event.detail.slug !== slug) {
                    slug = $event.detail.slug;
                    history.replaceState({}, '', '{{ route('studio.index') }}?page=' + slug);
                }
            "
            @click.outside="pagesOpen = false"
            @keydown.escape.window="pagesOpen = false"
        >
            <div
                class="s-urlbar w-full"
                :class="pagesOpen && 'is-open'"
                role="button"
                tabindex="0"
                :aria-expanded="pagesOpen"
                aria-haspopup="menu"
                title="Switch page"
                @click="pagesOpen = !pagesOpen"
                @keydown.enter.prevent="pagesOpen = !pagesOpen"
                @keydown.space.prevent="pagesOpen = !pagesOpen"
            >
                <span
                    class="s-status-dot shrink-0 transition-colors duration-300"
                    :class="{
                        'bg-ok': state === 'idle' || state === 'saved',
                        'bg-warn animate-pulse': state === 'saving',
                        'bg-danger': state === 'error',
                    }"
                ></span>
                <span class="min-w-0 flex-1 truncate text-[12.5px]">
                    <span class="text-soft">{{ $displayHost }}</span>
                    <span class="mx-1 text-faint">/</span><span class="font-mono text-xs text-ink" x-text="path">{{ $page->slug === $homeSlug ? '' : $page->slug }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-1">
                    <span class="mr-1 text-[11px] text-faint">
                        <span x-show="state === 'saving'" x-cloak>Saving…</span>
                        <span x-show="state === 'saved'" x-cloak class="text-ok/80">Saved</span>
                        <span x-show="state === 'error'" x-cloak class="text-danger">Offline</span>
                    </span>
                    <span class="s-urlbar-action" :class="pagesOpen && 'is-active'">
                        <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="pagesOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </span>
                    @if($liveUrl || $draftMode)
                        <a
                            :href="openUrl"
                            href="{{ $draftMode ? route('studio.preview.page', ['slug' => $page->slug]) : $liveUrl }}"
                            target="_blank"
                            class="s-urlbar-action"
                            title="{{ $draftMode ? 'Open the draft preview in a new tab' : 'Open the live page in a new tab' }}"
                            aria-label="{{ $draftMode ? 'Open the draft preview in a new tab' : 'Open the live page in a new tab' }}"
                            @click.stop
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        </a>
                    @endif
                </span>
            </div>

            {{-- Pages dropdown --}}
            <div
                x-show="pagesOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.99]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="s-pop absolute inset-x-0 top-full z-50 mt-1.5 origin-top"
                role="menu"
                aria-label="Pages"
            >
                <p class="s-microlabel px-2.5 pb-1 pt-2">Pages</p>
                @foreach($pages as $p)
                    <a
                        href="{{ route('studio.index', ['page' => $p->slug]) }}"
                        class="s-menu-item {{ $p->slug === $page->slug ? 'bg-white/6 !text-ink' : '' }}"
                        role="menuitem"
                    >
                        <svg class="h-3.5 w-3.5 shrink-0 {{ $p->slug === $page->slug ? 'text-accent' : 'text-transparent' }}" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                        </svg>
                        <span class="min-w-0 flex-1 truncate">{{ $p->title }}</span>
                        <span class="font-mono text-[10.5px] text-faint">/{{ $p->slug === $homeSlug ? '' : $p->slug }}</span>
                    </a>
                @endforeach

                <div class="s-divider my-1"></div>

                <button @click="pagesOpen = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/>
                    </svg>
                    New page
                </button>
            </div>
        </div>
        </div>

        {{-- Device switcher --}}
        <div x-data>
            <div class="s-seg">
                <button
                    class="s-seg-btn"
                    :class="$store.studio.device === 'desktop' && 'is-active'"
                    @click="$store.studio.device = 'desktop'"
                    title="Desktop preview"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v8.5A2.25 2.25 0 0 1 15.75 15h-3.105a3.501 3.501 0 0 0 1.1 1.677A.75.75 0 0 1 13.26 18H6.74a.75.75 0 0 1-.484-1.323A3.501 3.501 0 0 0 7.355 15H4.25A2.25 2.25 0 0 1 2 12.75v-8.5Zm1.5 0a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-7.5Z" clip-rule="evenodd"/></svg>
                </button>
                <button
                    class="s-seg-btn"
                    :class="$store.studio.device === 'tablet' && 'is-active'"
                    @click="$store.studio.device = 'tablet'"
                    title="Tablet preview — 768px"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 1a2.5 2.5 0 0 0-2.5 2.5v13A2.5 2.5 0 0 0 5 19h10a2.5 2.5 0 0 0 2.5-2.5v-13A2.5 2.5 0 0 0 15 1H5ZM4 3.5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-13Zm5 11.75a.75.75 0 0 0 0 1.5h2a.75.75 0 0 0 0-1.5H9Z" clip-rule="evenodd"/></svg>
                </button>
                <button
                    class="s-seg-btn"
                    :class="$store.studio.device === 'mobile' && 'is-active'"
                    @click="$store.studio.device = 'mobile'"
                    title="Mobile preview — 390px"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7 1a2.5 2.5 0 0 0-2.5 2.5v13A2.5 2.5 0 0 0 7 19h6a2.5 2.5 0 0 0 2.5-2.5v-13A2.5 2.5 0 0 0 13 1H7ZM6 3.5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-13Zm3 11.75a.75.75 0 0 0 0 1.5h2a.75.75 0 0 0 0-1.5H9Z" clip-rule="evenodd"/></svg>
                </button>
            </div>
        </div>

        {{-- Publish --}}
        <div class="relative" x-data="{
            open: false,
            exporting: false,
            copied: false,
            draftMode: @js($draftMode),
            status: @js($publishStatus),
            busy: false,

            copyUrl() {
                navigator.clipboard.writeText(@js($liveUrl)).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1800);
                });
            },

            async refreshStatus() {
                if (!this.draftMode) return;
                try {
                    const response = await fetch(@js(route('studio.api.publish.status')), { headers: { 'Accept': 'application/json' } });
                    this.status = await response.json();
                } catch (e) { /* keep last known status */ }
            },

            async publish() {
                if (this.busy) return;
                this.busy = true;
                try {
                    const response = await fetch(@js(route('studio.api.publish')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.Studio.toast(data.count === 1 ? '1 change published' : data.count + ' changes published');
                        if (data.home_claimed) {
                            window.Studio.toast('Removed Laravel\'s default welcome page — your homepage now serves at /', 'info', 6000);
                        }
                        await this.refreshStatus();
                    } else {
                        window.Studio.toast('Could not publish the site', 'error');
                    }
                } catch (e) {
                    window.Studio.toast('Could not publish the site', 'error');
                }
                this.busy = false;
            },

            async discard() {
                const count = this.status?.items?.length ?? 0;
                if (!window.confirm('Discard all unpublished changes? ' + count + (count === 1 ? ' item' : ' items') + ' will be reverted to the live site. This cannot be undone.')) return;
                this.busy = true;
                try {
                    const response = await fetch(@js(route('studio.api.publish.discard')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                } catch (e) { /* fall through */ }
                window.Studio.toast('Could not discard the draft', 'error');
                this.busy = false;
            },

            async exportBlade() {
                this.exporting = true;
                const ok = await window.Studio.exportBlade(@js(route('studio.api.generate.page', ['slug' => $page->slug])));
                if (ok) this.open = false;
                this.exporting = false;
            }
        }"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        @studio:status.window="if ($event.detail.state === 'saved' && status) status.dirty = true"
        >
            <button @click="open = !open; if (open) refreshStatus()" class="s-btn-primary relative">
                Publish
                <span
                    x-show="draftMode && status?.dirty"
                    x-cloak
                    x-transition.opacity
                    class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-accent ring-2 ring-shell"
                ></span>
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="s-pop absolute right-0 top-full mt-1.5 w-80 origin-top-right p-3"
            >
                @if($draftMode)
                    {{-- Unpublished changes --}}
                    <div x-show="status?.dirty" x-cloak>
                        <p class="s-microlabel px-0.5">Unpublished changes</p>

                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto pr-0.5">
                            <template x-for="item in (status?.items ?? [])" :key="item.type + '/' + item.slug">
                                <div class="flex items-center gap-2 rounded-md px-0.5 py-0.5 text-xs">
                                    <span class="s-chip w-14 shrink-0 !justify-center capitalize" x-text="item.type"></span>
                                    <span class="min-w-0 flex-1 truncate text-soft" x-text="item.label"></span>
                                    <span
                                        class="shrink-0 text-[10.5px]"
                                        :class="{ 'text-ok': item.state === 'added', 'text-warn': item.state === 'updated', 'text-danger': item.state === 'removed' }"
                                        x-text="item.state === 'removed' ? 'will be removed' : item.state"
                                    ></span>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex items-center gap-1.5">
                            <button @click="discard()" :disabled="busy" class="s-btn-ghost flex-1 !justify-center !text-danger hover:!bg-danger/10">
                                Discard
                            </button>
                            <button @click="publish()" :disabled="busy" class="s-btn-accent flex-1">
                                <span x-show="!busy">Publish site</span>
                                <span x-show="busy" x-cloak>Publishing…</span>
                            </button>
                        </div>

                        <a
                            href="{{ route('studio.preview.page', ['slug' => $page->slug]) }}"
                            target="_blank"
                            class="s-btn-outline mt-1.5 w-full"
                        >
                            Preview the draft site
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        </a>

                        <div class="s-divider my-3"></div>
                    </div>

                    {{-- Everything published --}}
                    <div x-show="!status || !status.dirty" x-cloak class="mb-3 flex items-start gap-2.5">
                        <span class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center">
                            <span class="s-status-dot bg-ok"></span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-ink">Everything is live</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-soft">The live site matches your draft. New edits stay in the draft until you publish them.</p>
                        </div>
                    </div>
                @elseif($routingEnabled)
                    <div class="mb-3 flex items-start gap-2.5">
                        <span class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center">
                            <span class="s-status-dot bg-ok"></span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-ink">This page is live</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-soft">Changes you make in the Studio are published to your site immediately.</p>
                        </div>
                    </div>
                @endif

                @if($routingEnabled)
                    <div class="flex items-center gap-1.5 rounded-lg border border-line bg-shell py-1.5 pl-2.5 pr-1.5">
                        <span class="min-w-0 flex-1 truncate font-mono text-xs text-soft">{{ $liveUrl }}</span>
                        <button @click="copyUrl()" class="s-icon-btn !h-6 !w-6" title="Copy URL">
                            <svg x-show="!copied" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.988 3.012A2.25 2.25 0 0 1 18 5.25v6.5A2.25 2.25 0 0 1 15.75 14H13.5v-3.379a3 3 0 0 0-.879-2.121l-3.12-3.121a3 3 0 0 0-1.402-.791 2.252 2.252 0 0 1 1.913-1.576A2.25 2.25 0 0 1 12.25 1h1.5a2.25 2.25 0 0 1 2.238 2.012ZM11.5 3.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v.25h-3v-.25Z" clip-rule="evenodd"/><path d="M3.5 6A1.5 1.5 0 0 0 2 7.5v9A1.5 1.5 0 0 0 3.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L8.44 6.439A1.5 1.5 0 0 0 7.378 6H3.5Z"/></svg>
                            <svg x-show="copied" x-cloak class="h-3.5 w-3.5 text-ok" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>

                    <div class="s-divider my-3"></div>
                @endif

                <p class="font-medium text-ink">Export as Blade</p>
                <p class="mt-0.5 text-xs leading-relaxed text-soft">
                    Write this page to <span class="font-mono text-[11px] text-ink/80">resources/views/designer/{{ $page->slug }}.blade.php</span> to serve it from your own routes.
                </p>
                <button @click="exportBlade()" :disabled="exporting" class="s-btn-outline mt-2.5 w-full">
                    <span x-show="!exporting">Export Blade file</span>
                    <span x-show="exporting" x-cloak>Exporting…</span>
                </button>
            </div>
        </div>

    </x-slot:topbar>

    {{-- ============================================================ --}}
    {{-- Sidebar — Livewire editor panel                               --}}
    {{-- ============================================================ --}}
    <x-slot:sidebar>
        <livewire:studio::editor-panel :page-slug="$page->slug" />
    </x-slot:sidebar>

    {{-- ============================================================ --}}
    {{-- Editor notices (security / storage warnings)                  --}}
    {{-- ============================================================ --}}
    @if(!empty($notices))
        <div class="pointer-events-none absolute inset-x-0 top-3 z-40 flex flex-col items-center gap-2 px-4">
            @foreach($notices as $notice)
                <div
                    x-data="{ show: {{ $notice['dismissible'] ? "localStorage.getItem('studio.notice.{$notice['id']}') !== '1'" : 'true' }} }"
                    x-show="show"
                    class="pointer-events-auto flex max-w-2xl items-center gap-2.5 rounded-xl border px-3.5 py-2.5 shadow-[0_12px_32px_-8px_rgba(0,0,0,0.5)] backdrop-blur-md {{ $notice['tone'] === 'danger' ? 'border-danger/40 bg-danger/15' : 'border-warn/40 bg-warn/10' }}"
                >
                    <svg class="h-4 w-4 shrink-0 {{ $notice['tone'] === 'danger' ? 'text-danger' : 'text-warn' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    <span class="text-[12.5px] leading-snug text-ink/90">{{ $notice['text'] }}</span>
                    @if($notice['dismissible'])
                        <button
                            @click="show = false; localStorage.setItem('studio.notice.{{ $notice['id'] }}', '1')"
                            class="s-icon-btn !h-6 !w-6 shrink-0"
                            title="Dismiss"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Canvas                                                        --}}
    {{-- ============================================================ --}}
    <div class="s-canvas h-full w-full overflow-auto" x-data>
        <div class="flex h-full flex-col p-1 lg:p-2">
            <div
                class="s-frame mx-auto w-full transition-[max-width] duration-300 ease-out"
                :style="`max-width: ${$store.studio.widths[$store.studio.device]}`"
            >
                {{-- Live preview --}}
                <iframe
                    id="studio-canvas-frame"
                    class="w-full flex-1 border-0 bg-white"
                    src="{{ route('studio.page.iframe', ['slug' => $page->slug]) }}"
                    title="Page preview"
                ></iframe>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Section library modal                                         --}}
    {{-- ============================================================ --}}
    <div
            x-data="{
                open: false,
                insertIndex: null,
                insertScope: 'page',
                addingRef: null,
                search: '',
                category: 'all',

                openLibrary(index = null, scope = 'page') {
                    this.insertIndex = index;
                    this.insertScope = scope;
                    this.search = '';
                    this.category = 'all';
                    this.open = true;
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                        // Scale previews once the modal is measurable
                        this.$root.querySelectorAll('.s-preview-card').forEach((card) => this.scaleCard(card));
                    });
                },

                close() {
                    this.open = false;
                    this.addingRef = null;
                },

                add(ref) {
                    if (this.addingRef) return;
                    this.addingRef = ref;
                    Livewire.dispatch('studio:add-section', { ref: ref, index: this.insertIndex, scope: this.insertScope });
                },

                matches(el) {
                    const haystack = el.dataset.search || '';
                    const cat = el.dataset.category || '';
                    const okSearch = !this.search || haystack.includes(this.search.toLowerCase());
                    const okCategory = this.category === 'all' || cat === this.category;
                    return okSearch && okCategory;
                },

                scaleCard(card) {
                    const viewport = card.querySelector('.s-preview-viewport');
                    const frame = viewport?.querySelector('iframe');
                    if (!viewport || !frame) return;
                    const scale = viewport.offsetWidth / 1200;
                    frame.style.transform = `scale(${scale})`;
                    frame.style.height = `${Math.ceil(viewport.offsetHeight / scale)}px`;
                }
            }"
            @studio:open-library.window="openLibrary($event.detail?.index ?? null, $event.detail?.scope || 'page')"
            @studio:section-added.window="close()"
            @keydown.escape.window="close()"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center p-4 lg:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="Add a section"
        >
            <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="close()"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-modal flex h-[660px] max-h-[88vh] w-[980px] max-w-full flex-col overflow-hidden"
            >
                {{-- Modal header --}}
                <div class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-3">
                    <h2 class="text-[15px] font-semibold text-ink" x-text="insertScope === 'layout' ? 'Add a section to the layout' : 'Add a section'">Add a section</h2>
                    <span class="s-chip" x-show="insertScope === 'layout'" x-cloak style="border-color: color-mix(in srgb, var(--color-layout) 40%, transparent); color: var(--color-layout);">Shared across pages</span>
                    <span class="s-chip">{{ $totalComponents }} designs</span>

                    <div class="relative ml-auto w-64">
                        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
                        </svg>
                        <input
                            x-ref="searchInput"
                            x-model="search"
                            type="text"
                            placeholder="Search sections…"
                            class="s-input !pl-8"
                        >
                    </div>

                    <button @click="close()" class="s-icon-btn" title="Close">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>

                <div class="flex min-h-0 flex-1">
                    {{-- Category rail --}}
                    <div class="w-44 shrink-0 space-y-0.5 overflow-y-auto border-r border-line p-2">
                        @if(count($blocks))
                            <button
                                @click="category = '__blocks'"
                                class="s-menu-item justify-between !text-block"
                                :class="category === '__blocks' && 'bg-block/10'"
                            >
                                Global blocks
                                <span class="text-[10.5px] text-block/70">{{ count($blocks) }}</span>
                            </button>
                        @endif
                        <button
                            @click="category = 'all'"
                            class="s-menu-item justify-between"
                            :class="category === 'all' && 'bg-white/6 !text-ink'"
                        >
                            All
                            <span class="text-[10.5px] text-faint">{{ $totalComponents }}</span>
                        </button>

                        @foreach($library as $categoryName => $components)
                            <button
                                @click="category = @js($categoryName)"
                                class="s-menu-item justify-between capitalize"
                                :class="category === @js($categoryName) && 'bg-white/6 !text-ink'"
                            >
                                {{ str_replace('-', ' ', $categoryName) }}
                                <span class="text-[10.5px] text-faint">{{ $components->count() }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Cards --}}
                    <div class="min-h-0 flex-1 overflow-y-auto p-4">
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Global blocks — synced everywhere they're placed --}}
                            @foreach($blocks as $block)
                                <button
                                    type="button"
                                    class="s-preview-card !border-block/25 hover:!border-block/60"
                                    data-category="__blocks"
                                    data-search="{{ strtolower($block['name'] . ' global block') }}"
                                    x-show="matches($el)"
                                    @click="add(@js('block:' . $block['slug']))"
                                    :class="addingRef === @js('block:' . $block['slug']) && 'pointer-events-none opacity-70'"
                                >
                                    <span class="s-preview-viewport">
                                        <iframe
                                            src="{{ route('studio.preview.block', ['slug' => $block['slug']]) }}"
                                            loading="lazy"
                                            tabindex="-1"
                                            title="{{ $block['name'] }} preview"
                                        ></iframe>
                                    </span>
                                    <span class="flex items-center gap-2 p-3">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-1.5">
                                                <span class="truncate text-[13px] font-medium text-ink">{{ $block['name'] }}</span>
                                                <span class="s-chip !border-block/40 !text-block">Global</span>
                                            </span>
                                            <span class="mt-0.5 block truncate text-xs text-faint">
                                                Synced — used {{ $block['usage'] }} {{ \Illuminate\Support\Str::plural('time', $block['usage']) }}
                                            </span>
                                        </span>
                                        <span x-show="addingRef === @js('block:' . $block['slug'])" x-cloak>
                                            <svg class="h-4 w-4 animate-spin text-block" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                        </span>
                                    </span>
                                </button>
                            @endforeach

                            @foreach($library as $categoryName => $components)
                                @foreach($components as $component)
                                    <button
                                        type="button"
                                        class="s-preview-card"
                                        data-category="{{ $categoryName }}"
                                        data-search="{{ strtolower($component->title . ' ' . $component->description . ' ' . $categoryName . ' ' . implode(' ', $component->tags)) }}"
                                        x-show="matches($el)"
                                        @click="add(@js($component->name))"
                                        :class="addingRef === @js($component->name) && 'pointer-events-none opacity-70'"
                                    >
                                        <span class="s-preview-viewport">
                                            <iframe
                                                src="{{ route('studio.preview.component', ['name' => $component->name]) }}"
                                                loading="lazy"
                                                tabindex="-1"
                                                title="{{ $component->title }} preview"
                                            ></iframe>
                                        </span>
                                        <span class="flex items-center gap-2 p-3">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[13px] font-medium text-ink">{{ $component->title }}</span>
                                                @if($component->description)
                                                    <span class="mt-0.5 block truncate text-xs text-faint">{{ $component->description }}</span>
                                                @endif
                                            </span>
                                            <span x-show="addingRef === @js($component->name)" x-cloak>
                                                <svg class="h-4 w-4 animate-spin text-accent" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            @endforeach
                        </div>

                        {{-- No results --}}
                        <div x-show="search && ![...$el.parentElement.querySelectorAll('.s-preview-card')].some(el => el.style.display !== 'none')" x-cloak class="py-16 text-center">
                            <p class="text-sm text-soft">No sections match “<span x-text="search" class="text-ink"></span>”</p>
                            <button @click="search = ''" class="s-btn-ghost mx-auto mt-2">Clear search</button>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Create page modal                                             --}}
    {{-- ============================================================ --}}
    <div
            x-data="{
                open: false,
                title: '',
                slug: '',
                slugEdited: false,
                creating: false,
                error: '',

                openModal() {
                    this.open = true;
                    this.title = '';
                    this.slug = '';
                    this.slugEdited = false;
                    this.error = '';
                    this.$nextTick(() => this.$refs.titleInput?.focus());
                },

                syncSlug() {
                    if (this.slugEdited) return;
                    this.slug = this.title.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                },

                async create() {
                    if (!this.title.trim() || this.creating) return;
                    this.creating = true;
                    this.error = '';
                    try {
                        const response = await fetch(@js(route('studio.api.pages.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ title: this.title, slug: this.slug }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = data.editor_url;
                            return;
                        }
                        this.error = data.message || 'Could not create the page.';
                    } catch (e) {
                        this.error = 'Could not create the page.';
                    }
                    this.creating = false;
                }
            }"
            @studio:open-create-page.window="openModal()"
            @keydown.escape.window="open = false"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Create a new page"
        >
            <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="open = false"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-modal w-[420px] max-w-full p-5"
            >
                <h2 class="text-[15px] font-semibold text-ink">Create a new page</h2>
                <p class="mt-1 text-xs leading-relaxed text-soft">Pages start empty — add sections from the library once it opens.</p>

                <div class="mt-4">
                    <label for="new-page-title" class="s-label">Page title</label>
                    <input
                        id="new-page-title"
                        x-ref="titleInput"
                        x-model="title"
                        @input="syncSlug()"
                        @keydown.enter="create()"
                        type="text"
                        placeholder="About us"
                        class="s-input"
                    >
                </div>

                <div class="mt-3">
                    <label for="new-page-slug" class="s-label">URL</label>
                    <div class="flex items-center overflow-hidden rounded-lg border border-line bg-raised transition-colors focus-within:border-accent">
                        <span class="border-r border-line px-2.5 font-mono text-xs text-faint">/</span>
                        <input
                            id="new-page-slug"
                            x-model="slug"
                            @input="slugEdited = true"
                            @keydown.enter="create()"
                            type="text"
                            placeholder="about-us"
                            class="h-8 w-full bg-transparent px-2.5 font-mono text-xs text-ink placeholder:text-faint focus:outline-none"
                        >
                    </div>
                </div>

                <p x-show="error" x-cloak x-text="error" class="mt-3 text-xs text-danger"></p>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="open = false" class="s-btn-ghost">Cancel</button>
                    <button @click="create()" :disabled="creating || !title.trim()" class="s-btn-primary">
                        <span x-show="!creating">Create page</span>
                        <span x-show="creating" x-cloak>Creating…</span>
                    </button>
                </div>
            </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Dev mode — section source editor                              --}}
    {{-- ============================================================ --}}
    @if(\Designer\Studio\Support\DevMode::enabled())
    <div
            x-data="{
                open: false,
                ref: null,
                title: '',
                tab: 'html',
                loading: false,
                saving: false,
                error: '',
                paths: { html: '', yaml: '' },
                editors: null,
                base: @js(url(trim(config('studio.path', 'studio'), '/') . '/api/dev/components')),

                ensureEditors() {
                    if (this.editors) return;
                    this.editors = {
                        html: window.Studio.codeEditor(this.$refs.htmlHost, { language: 'html' }),
                        yaml: window.Studio.codeEditor(this.$refs.yamlHost, { language: 'yaml' }),
                    };
                },

                async openEditor(detail) {
                    this.ref = detail.ref;
                    this.title = detail.title || detail.ref;
                    this.tab = 'html';
                    this.error = '';
                    this.open = true;
                    this.loading = true;
                    window.Studio.codeModalOpen = true;

                    try {
                        const response = await fetch(`${this.base}/${this.ref}`, { headers: { 'Accept': 'application/json' } });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not load the source files.');
                        await this.$nextTick();
                        this.ensureEditors();
                        this.editors.html.setValue(data.html);
                        this.editors.yaml.setValue(data.yaml);
                        this.paths = data.paths;
                    } catch (e) {
                        this.error = e.message;
                    }
                    this.loading = false;
                },

                close() {
                    this.open = false;
                    window.Studio.codeModalOpen = false;
                },

                async save() {
                    if (this.saving || !this.ref || !this.editors) return;
                    this.saving = true;
                    this.error = '';
                    try {
                        const response = await fetch(`${this.base}/${this.ref}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                html: this.editors.html.getValue(),
                                yaml: this.editors.yaml.getValue(),
                            }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not save the source files.');
                        window.Studio.toast('Section source saved — every section using it is updated');
                        window.dispatchEvent(new CustomEvent('studio:refresh-preview'));
                        window.Livewire?.dispatch('studio:code-saved');
                    } catch (e) {
                        this.error = e.message;
                    }
                    this.saving = false;
                }
            }"
            @studio:open-code-editor.window="openEditor($event.detail)"
            @keydown.escape.window="close()"
            @keydown.window="if (open && ($event.metaKey || $event.ctrlKey) && ($event.key === 's' || $event.key === 'S')) { $event.preventDefault(); save(); }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center p-4 lg:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="Edit section source code"
        >
            <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="close()"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-modal flex h-[720px] max-h-[90vh] w-[1080px] max-w-full flex-col overflow-hidden"
            >
                {{-- Header --}}
                <div class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-[15px] font-semibold text-ink" x-text="title"></h2>
                        <p class="truncate font-mono text-[11px] text-faint" x-text="tab === 'html' ? paths.html : paths.yaml"></p>
                    </div>

                    <div class="s-seg ml-auto">
                        <button class="s-seg-btn !w-14 text-[11.5px] font-medium" :class="tab === 'html' && 'is-active'" @click="tab = 'html'">HTML</button>
                        <button class="s-seg-btn !w-14 text-[11.5px] font-medium" :class="tab === 'yaml' && 'is-active'" @click="tab = 'yaml'">YAML</button>
                    </div>

                    <button @click="close()" class="s-icon-btn" title="Close (Esc)">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>

                {{-- Editors --}}
                <div class="relative min-h-0 flex-1">
                    <div x-show="loading" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-panel/70">
                        <svg class="h-5 w-5 animate-spin text-soft" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                    </div>
                    <div x-ref="htmlHost" x-show="tab === 'html'" class="s-code-pane h-full"></div>
                    <div x-ref="yamlHost" x-show="tab === 'yaml'" class="s-code-pane h-full"></div>
                </div>

                {{-- Footer --}}
                <div class="flex shrink-0 items-center gap-3 border-t border-line px-4 py-2.5">
                    <p class="min-w-0 flex-1 truncate text-[11.5px] text-faint">
                        <span x-show="!error">Sections must stay inside the supported Blade subset — see <span class="font-mono">docs/authoring-sections.md</span>. Saving updates every page using this section.</span>
                        <span x-show="error" x-cloak class="text-danger" x-text="error"></span>
                    </p>
                    <button @click="close()" class="s-btn-ghost">Cancel</button>
                    <button @click="save()" :disabled="saving || loading" class="s-btn-accent">
                        <span x-show="!saving">Save</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                        <span class="s-kbd !border-white/25 !bg-transparent !text-white/80">⌘S</span>
                    </button>
                </div>
            </div>
    </div>
    @endif
</x-studio::layouts.app>
