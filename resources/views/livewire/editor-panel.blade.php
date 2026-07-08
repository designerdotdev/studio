<div
    class="flex h-full min-h-0 flex-col"
    x-data="{
        preview(sectionId, key, value) {
            window.dispatchEvent(new CustomEvent('studio:to-iframe', {
                detail: { type: 'studio:update-variable', sectionId, key, value }
            }));
        },
        hint(sectionId, on) {
            window.dispatchEvent(new CustomEvent('studio:to-iframe', {
                detail: { type: 'studio:hover', sectionId, on }
            }));
        }
    }"
>
    @php $selected = $this->selectedSection; @endphp

    @if($selected)
        {{-- ======================================================== --}}
        {{-- Inspector — edit the selected section                     --}}
        {{-- ======================================================== --}}
        <div class="s-panel-enter flex h-full min-h-0 flex-col" wire:key="inspector-{{ $selectedId }}">
            {{-- Inspector header --}}
            <div class="flex shrink-0 items-center gap-1 border-b border-line px-2 py-2.5">
                <button wire:click="closeInspector" class="s-icon-btn" title="Back to sections (Esc)">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] font-semibold text-ink">{{ $selected['title'] }}</p>
                    <p class="s-microlabel !normal-case !tracking-normal capitalize">{{ str_replace('-', ' ', $selected['category']) }}</p>
                </div>
                @if(!empty($selected['block']))
                    <span class="s-chip !border-block/40 !text-block">Global</span>
                @elseif(($selected['scope'] ?? 'page') === 'page')
                    <button
                        wire:click="makeGlobal('{{ $selectedId }}')"
                        class="s-icon-btn"
                        title="Make global — reuse this section on any page"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3.196 12.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 12.87Z"/><path d="M3.196 8.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 8.87Z"/><path d="M10.38 1.103a.75.75 0 0 0-.76 0l-7.25 4.25a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .76 0l7.25-4.25a.75.75 0 0 0 0-1.294l-7.25-4.25Z"/></svg>
                    </button>
                @endif
                @if(($selected['scope'] ?? 'page') === 'layout')
                    <span class="s-chip !border-layout/40 !text-layout">Layout</span>
                @endif
                @if($selected['hidden'])
                    <span class="s-chip !text-warn">Hidden</span>
                @endif
            </div>

            @if(!empty($selected['block']))
                {{-- Global block banner --}}
                <div class="shrink-0 space-y-2 border-b border-block/25 bg-block/8 px-3 py-2.5">
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-block" viewBox="0 0 20 20" fill="currentColor"><path d="M3.196 12.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 12.87Z"/><path d="M3.196 8.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 8.87Z"/><path d="M10.38 1.103a.75.75 0 0 0-.76 0l-7.25 4.25a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .76 0l7.25-4.25a.75.75 0 0 0 0-1.294l-7.25-4.25Z"/></svg>
                        <p class="text-[11.5px] leading-relaxed text-soft">
                            Global block — editing it updates
                            {{ $this->blockUsageCount === 1 ? 'its only placement' : 'all ' . $this->blockUsageCount . ' placements' }} across your site.
                        </p>
                    </div>
                    <input
                        type="text"
                        class="s-input !h-7 !text-xs"
                        title="Block name (shown in the section library)"
                        wire:model.blur="selectedBlockName"
                    >
                    <div class="flex items-center gap-1">
                        <button
                            wire:click="detachBlock('{{ $selectedId }}')"
                            class="s-btn-ghost !h-6 flex-1 !justify-center !text-xs"
                            title="Make this copy independent — it stops syncing"
                        >
                            Detach
                        </button>
                        <button
                            wire:click="deleteBlockEverywhere('{{ $selectedId }}')"
                            wire:confirm="Delete “{{ $selected['blockName'] }}” and remove all {{ $this->blockUsageCount }} {{ \Illuminate\Support\Str::plural('placement', $this->blockUsageCount) }} across your site? This cannot be undone."
                            class="s-btn-ghost !h-6 flex-1 !justify-center !text-xs !text-danger hover:!bg-danger/10"
                        >
                            Delete everywhere
                        </button>
                    </div>
                </div>
            @endif

            @if(($selected['scope'] ?? 'page') === 'layout')
                {{-- Layout edit banner --}}
                <div class="flex shrink-0 items-start gap-2 border-b border-layout/25 bg-layout/8 px-3 py-2">
                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-layout" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    <p class="text-[11.5px] leading-relaxed text-soft">
                        Editing <span class="font-medium text-ink">{{ $layoutName ?: 'the layout' }}</span> —
                        changes apply to {{ $this->layoutUsageCount === 1 ? 'this page' : 'all ' . $this->layoutUsageCount . ' pages using it' }}.
                    </p>
                </div>
            @endif

            {{-- Fields --}}
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5 pb-6">
                @php $fields = $fieldsByRef[$selected['ref']] ?? []; @endphp

                @forelse($fields as $key => $field)
                    @php
                        $type = $field['type'] ?? 'text';
                        $partial = in_array($type, ['text', 'url', 'textarea', 'select', 'toggle', 'colorpicker', 'image', 'repeater'], true) ? $type : 'text';
                    @endphp

                    <div wire:key="field-{{ $selectedId }}-{{ $key }}">
                        @include('studio::livewire.fields.' . $partial, [
                            'sectionId' => $selectedId,
                            'key' => $key,
                            'field' => $field,
                            'value' => $variables[$selectedId][$key] ?? null,
                        ])

                        @if(!empty($field['description']))
                            <p class="mt-1.5 text-[11px] leading-relaxed text-faint">{{ $field['description'] }}</p>
                        @endif
                    </div>
                @empty
                    <div class="py-10 text-center">
                        <p class="text-[13px] text-soft">This section has no editable fields.</p>
                    </div>
                @endforelse
            </div>

            {{-- Inspector footer actions --}}
            <div class="flex shrink-0 items-center gap-1 border-t border-line p-2">
                <button
                    wire:click="duplicateSection('{{ $selectedId }}')"
                    class="s-btn-ghost flex-1 !justify-center"
                    title="Duplicate section (⌘D)"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    Duplicate
                </button>
                <button
                    wire:click="toggleHidden('{{ $selectedId }}')"
                    class="s-btn-ghost flex-1 !justify-center"
                    title="{{ $selected['hidden'] ? 'Show section' : 'Hide section' }}"
                >
                    @if($selected['hidden'])
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                        Show
                    @else
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                        Hide
                    @endif
                </button>
                <button
                    wire:click="removeSection('{{ $selectedId }}')"
                    class="s-btn-ghost flex-1 !justify-center !text-danger hover:!bg-danger/10"
                    title="Delete section (⌫)"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193v-.443A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Zm-1.586 4.914a.75.75 0 1 0-1.498.086l.5 8.5a.75.75 0 0 0 1.498-.086l-.5-8.5Zm4.67.086a.75.75 0 1 0-1.498-.086l-.5 8.5a.75.75 0 0 0 1.498.086l.5-8.5Z" clip-rule="evenodd"/></svg>
                    Delete
                </button>
            </div>
        </div>
    @else
        {{-- ======================================================== --}}
        {{-- Tabs — Sections list / Page settings                      --}}
        {{-- ======================================================== --}}
        <div class="s-panel-enter-back flex h-full min-h-0 flex-col" wire:key="panel-tabs">
            <div class="flex shrink-0 items-center gap-1.5 border-b border-line p-2">
                <div class="grid min-w-0 flex-1 grid-cols-3 gap-0.5 rounded-lg border border-line bg-shell p-0.5">
                    <button
                        wire:click="$set('tab', 'sections')"
                        class="flex h-7 cursor-pointer items-center justify-center rounded-[7px] text-[13px] font-medium transition-all duration-150 {{ $tab === 'sections' ? 'bg-raised text-ink shadow-sm' : 'text-faint hover:text-soft' }}"
                    >
                        Sections
                    </button>
                    <button
                        wire:click="$set('tab', 'layout')"
                        class="flex h-7 cursor-pointer items-center justify-center rounded-[7px] text-[13px] font-medium transition-all duration-150 {{ $tab === 'layout' ? 'bg-raised text-ink shadow-sm' : 'text-faint hover:text-soft' }}"
                    >
                        Layout
                    </button>
                    <button
                        wire:click="$set('tab', 'page')"
                        class="flex h-7 cursor-pointer items-center justify-center rounded-[7px] text-[13px] font-medium transition-all duration-150 {{ $tab === 'page' ? 'bg-raised text-ink shadow-sm' : 'text-faint hover:text-soft' }}"
                    >
                        Page
                    </button>
                </div>

                <button
                    x-data
                    @click="$store.studio.toggleSidebar()"
                    class="s-icon-btn s-panel-collapse !h-8 !w-8 shrink-0"
                    title="Hide sidebar"
                    aria-label="Hide sidebar"
                >
                    <svg class="h-4 w-4" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M4.5498 2.30001C3.68785 2.30001 2.8612 2.64242 2.25171 3.25191C1.64221 3.8614 1.2998 4.68805 1.2998 5.55001C1.2998 6.41196 1.3 10.45 1.3 10.45C1.3 10.8768 1.38406 11.2994 1.54739 11.6937C1.71072 12.088 1.95011 12.4463 2.2519 12.7481C2.8614 13.3576 3.68805 13.7 4.55 13.7L11.4498 13.7C11.8766 13.7 12.2992 13.6159 12.6935 13.4526C13.0878 13.2893 13.4461 13.0499 13.7479 12.7481C14.0497 12.4463 14.2891 12.088 14.4524 11.6937C14.6157 11.2994 14.6998 10.8768 14.6998 10.45C14.6998 8.30212 14.6998 7.69789 14.6998 5.55C14.6998 5.12321 14.6157 4.70059 14.4524 4.30628C14.2891 3.91197 14.0497 3.5537 13.7479 3.25191C13.4461 2.95012 13.0878 2.71072 12.6935 2.54739C12.2992 2.38407 11.8766 2.3 11.4498 2.3L4.5498 2.30001ZM2.4998 5.50001C2.4998 4.96957 2.71052 4.46087 3.08559 4.08579C3.46066 3.71072 3.96937 3.50001 4.4998 3.50001H11.4998C12.0302 3.50001 12.5389 3.71072 12.914 4.08579C13.2891 4.46087 13.4998 4.96957 13.4998 5.50001V10.5C13.4998 11.0304 13.2891 11.5391 12.914 11.9142C12.5389 12.2893 12.0302 12.5 11.4998 12.5H4.4998C3.96937 12.5 3.46066 12.2893 3.08559 11.9142C2.71052 11.5391 2.4998 11.0304 2.4998 10.5V5.50001Z"></path>
                        <rect class="s-panel-icon-bar" x="3.9" y="5" width="4.5" height="6" rx="0.75"></rect>
                    </svg>
                </button>
            </div>

            @if($tab === 'sections')
                {{-- Sections list --}}
                <div class="min-h-0 flex-1 overflow-y-auto p-2">
                    @if(count($sections))
                        <div
                            wire:key="sections-sortable"
                            x-init="window.Studio.sortable($el, '.s-drag-handle', ids => $wire.reorderSections(ids))"
                            class="space-y-0.5"
                        >
                            @foreach($sections as $section)
                                <div
                                    wire:key="row-{{ $section['id'] }}"
                                    data-section-id="{{ $section['id'] }}"
                                    class="s-section-row group {{ $selectedId === $section['id'] ? 'is-active' : '' }}"
                                    wire:click="selectFromList('{{ $section['id'] }}')"
                                    @mouseenter="hint('{{ $section['id'] }}', true)"
                                    @mouseleave="hint('{{ $section['id'] }}', false)"
                                >
                                    <span class="s-drag-handle" @click.stop title="Drag to reorder">
                                        <svg class="h-3 w-3" viewBox="0 0 16 16" fill="currentColor"><circle cx="5" cy="3.5" r="1.2"/><circle cx="11" cy="3.5" r="1.2"/><circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/><circle cx="5" cy="12.5" r="1.2"/><circle cx="11" cy="12.5" r="1.2"/></svg>
                                    </span>

                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border bg-raised {{ !empty($section['block']) ? 'border-block/40 text-block' : 'border-line ' . ($section['hidden'] ? 'text-faint' : 'text-soft') }}">
                                        @include('studio::partials.category-icon', ['category' => $section['category']])
                                    </span>

                                    <span class="min-w-0 flex-1 truncate text-[13px] {{ $section['hidden'] ? 'text-faint line-through decoration-line-strong' : 'text-ink' }}">
                                        {{ $section['title'] }}
                                    </span>

                                    <span class="row-actions" @click.stop>
                                        <button
                                            wire:click="toggleHidden('{{ $section['id'] }}')"
                                            class="s-icon-btn !h-6 !w-6"
                                            title="{{ $section['hidden'] ? 'Show section' : 'Hide section' }}"
                                        >
                                            @if($section['hidden'])
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                                            @else
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                                            @endif
                                        </button>
                                        <button
                                            wire:click="duplicateSection('{{ $section['id'] }}')"
                                            class="s-icon-btn !h-6 !w-6"
                                            title="Duplicate section"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                                        </button>
                                        <button
                                            wire:click="removeSection('{{ $section['id'] }}')"
                                            class="s-icon-btn !h-6 !w-6 hover:!text-danger"
                                            title="Delete section"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193v-.443A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Zm-1.586 4.914a.75.75 0 1 0-1.498.086l.5 8.5a.75.75 0 0 0 1.498-.086l-.5-8.5Zm4.67.086a.75.75 0 1 0-1.498-.086l-.5 8.5a.75.75 0 0 0 1.498.086l.5-8.5Z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <button
                            type="button"
                            class="s-empty-tile mt-1"
                            @click="window.dispatchEvent(new CustomEvent('studio:open-library', { detail: { index: null } }))"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-raised text-soft">
                                <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                            </span>
                            <span class="text-[13px] font-medium text-ink">Add your first section</span>
                            <span class="text-xs leading-relaxed text-faint">Pick from {{ count($fieldsByRef) ?: 'dozens of' }} pre-built designs<br>in the section library.</span>
                        </button>
                    @endif
                </div>

                {{-- Add section footer --}}
                <div class="shrink-0 border-t border-line p-2.5">
                    <button
                        type="button"
                        class="s-btn-accent w-full"
                        @click="window.dispatchEvent(new CustomEvent('studio:open-library', { detail: { index: null } }))"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                        Add section
                    </button>
                </div>
            @elseif($tab === 'layout')
                {{-- Layout — shared header/footer sections around the page --}}
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5">
                    <div>
                        <label for="page-layout" class="s-label">Page layout</label>
                        <select id="page-layout" class="s-input" wire:change="assignLayout($event.target.value)">
                            <option value="" @selected(!$layoutSlug)>None</option>
                            @foreach($layouts as $slug => $name)
                                <option value="{{ $slug }}" @selected($layoutSlug === $slug)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-faint">A layout wraps this page with shared sections — like a site-wide header and footer.</p>
                    </div>

                    @if($layoutSlug)
                        <div class="s-divider"></div>

                        <div>
                            <label for="layout-name" class="s-label">Layout name</label>
                            <input id="layout-name" type="text" class="s-input" wire:model.blur="layoutName">
                            <p class="mt-1.5 text-[11px] leading-relaxed text-faint">
                                Used by {{ $this->layoutUsageCount }} {{ \Illuminate\Support\Str::plural('page', $this->layoutUsageCount) }}.
                            </p>
                        </div>

                        <div class="flex items-start gap-2.5 rounded-lg border border-layout/25 bg-layout/8 p-3">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-layout" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                            <p class="text-xs leading-relaxed text-soft">
                                The <span class="font-medium text-layout">violet sections</span> on the canvas belong to this layout.
                                Click one to edit it, or use the add buttons above and below your page content —
                                changes apply to every page using it.
                            </p>
                        </div>

                        <div class="s-divider"></div>

                        <button
                            wire:click="deleteLayout"
                            wire:confirm="Delete “{{ $layoutName }}”? Its header and footer sections will be removed from {{ $this->layoutUsageCount }} {{ \Illuminate\Support\Str::plural('page', $this->layoutUsageCount) }}. This cannot be undone."
                            class="s-btn-danger w-full"
                        >
                            Delete layout
                        </button>
                    @endif

                    @if(!$layoutSlug || $showCreateLayout)
                        <div class="s-divider"></div>

                        <div wire:key="create-layout-form">
                            <label for="new-layout-name" class="s-label">New layout</label>
                            <div class="flex gap-1.5">
                                <input
                                    id="new-layout-name"
                                    type="text"
                                    class="s-input"
                                    placeholder="Main"
                                    wire:model="newLayoutName"
                                    wire:keydown.enter="createLayout"
                                    @if($showCreateLayout) x-init="$el.focus()" @endif
                                >
                                <button wire:click="createLayout" class="s-btn-outline shrink-0">Create</button>
                            </div>
                            <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Creates an empty layout and applies it to this page — then add its shared sections right on the canvas.</p>
                        </div>
                    @elseif($layoutSlug)
                        <button
                            wire:click="$set('showCreateLayout', true)"
                            class="s-btn-ghost w-full !justify-center"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                            New layout
                        </button>
                    @endif
                </div>
            @else
                {{-- Page settings --}}
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5">
                    <div>
                        <label for="page-title" class="s-label">Page title</label>
                        <input id="page-title" type="text" class="s-input" wire:model.blur="page.title">
                    </div>

                    <div>
                        <label for="page-slug" class="s-label">URL</label>
                        <div class="flex items-center overflow-hidden rounded-lg border border-line bg-raised transition-colors focus-within:border-accent">
                            <span class="border-r border-line px-2.5 font-mono text-xs text-faint">/</span>
                            <input id="page-slug" type="text" class="h-8 w-full bg-transparent px-2.5 font-mono text-xs text-ink focus:outline-none" wire:model.blur="page.slug">
                        </div>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Changing the URL takes effect immediately.</p>
                    </div>

                    <div class="s-divider"></div>

                    {{-- ============ Search engine listing ============ --}}
                    <p class="s-microlabel">Search engine listing</p>

                    {{-- Live result preview --}}
                    <div class="rounded-lg border border-line bg-shell px-3 py-2.5">
                        <p class="truncate text-[11px] text-faint">{{ parse_url(url('/'), PHP_URL_HOST) }} › {{ $page['slug'] ?? '' }}</p>
                        <p class="mt-0.5 truncate text-[14.5px] leading-snug text-[#9cc0ff]">{{ ($page['seo_title'] ?? '') !== '' ? $page['seo_title'] : ($page['title'] ?? 'Untitled') }}</p>
                        <p class="mt-0.5 line-clamp-2 text-xs leading-relaxed {{ ($page['seo_description'] ?? '') !== '' ? 'text-soft' : 'text-faint italic' }}">
                            {{ ($page['seo_description'] ?? '') !== '' ? $page['seo_description'] : 'Add a description to control how this page reads in search results.' }}
                        </p>
                    </div>

                    <div>
                        <label for="page-seo-title" class="s-label">SEO title</label>
                        <input id="page-seo-title" type="text" class="s-input" placeholder="{{ $page['title'] ?? '' }}" wire:model.blur="page.seo_title">
                    </div>

                    <div>
                        <label for="page-seo-description" class="s-label">SEO description</label>
                        <textarea id="page-seo-description" rows="3" class="s-input" placeholder="Describe this page for search results…" wire:model.blur="page.seo_description"></textarea>
                    </div>

                    {{-- ============ Social sharing (collapsible) ============ --}}
                    <div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-line" wire:key="group-social">
                        <button type="button" @click="open = !open" class="flex w-full cursor-pointer items-center justify-between gap-2 px-3 py-2.5 transition-colors hover:bg-white/[0.03]">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-medium text-ink">Social sharing</span>
                                <span class="mt-0.5 block truncate text-[11px] text-faint">Facebook, LinkedIn, Slack, iMessage, X</span>
                            </span>
                            <svg class="h-3.5 w-3.5 shrink-0 text-faint transition-transform duration-150" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="space-y-4 border-t border-line p-3">
                                <div x-data="{ uploading: false }">
                                    <label class="s-label">Social share image</label>
                                    @if(!empty($page['og_image']))
                                        <div class="group relative mb-2 overflow-hidden rounded-lg border border-line bg-shell">
                                            <img src="{{ $page['og_image'] }}" alt="" class="block h-24 w-full object-cover">
                                            <button type="button" class="absolute right-1.5 top-1.5 flex h-6 w-6 cursor-pointer items-center justify-center rounded-md bg-black/60 text-white/80 opacity-0 backdrop-blur transition-all hover:bg-black/80 hover:text-white group-hover:opacity-100" title="Remove image" wire:click="$set('page.og_image', '')">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                                            </button>
                                        </div>
                                    @endif
                                    <div class="flex gap-1.5">
                                        <input type="text" class="s-input !font-mono !text-xs" placeholder="https://… or upload" wire:model.blur="page.og_image">
                                        <label class="s-btn-outline relative shrink-0 cursor-pointer !px-2.5" :class="uploading && 'pointer-events-none opacity-60'" title="Upload an image">
                                            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" class="sr-only" x-on:change="
                                                const file = $event.target.files[0]; $event.target.value = '';
                                                if (!file) return;
                                                uploading = true;
                                                window.Studio.upload(file, { url: @js(route('studio.api.upload')), csrf: document.querySelector('meta[name=csrf-token]').content })
                                                    .then((url) => $wire.set('page.og_image', url))
                                                    .catch((e) => window.Studio.toast(e.message || 'Upload failed', 'error'))
                                                    .finally(() => uploading = false);
                                            ">
                                            <svg x-show="!uploading" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.636l2.955 3.129a.75.75 0 0 0 1.09-1.03l-4.25-4.5a.75.75 0 0 0-1.09 0l-4.25 4.5a.75.75 0 1 0 1.09 1.03L9.25 4.636v8.614Z" clip-rule="evenodd"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                                            <svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                        </label>
                                    </div>
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Shown when this page is shared. 1200×630px recommended.</p>
                                </div>

                                <div>
                                    <label for="page-og-image-alt" class="s-label">Image alt text</label>
                                    <input id="page-og-image-alt" type="text" class="s-input" placeholder="Describe the image" wire:model.blur="page.og_image_alt">
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="page-og-type" class="s-label">Type</label>
                                        <select id="page-og-type" class="s-input" wire:model.change="page.og_type">
                                            <option value="">Website</option>
                                            <option value="article">Article</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="page-og-locale" class="s-label">Locale</label>
                                        <input id="page-og-locale" type="text" class="s-input" placeholder="{{ str_replace('-', '_', app()->getLocale()) }}" wire:model.blur="page.og_locale">
                                    </div>
                                </div>

                                <div>
                                    <label for="page-og-site-name" class="s-label">Site name</label>
                                    <input id="page-og-site-name" type="text" class="s-input" placeholder="{{ config('app.name') }}" wire:model.blur="page.og_site_name">
                                </div>

                                <div class="s-divider"></div>

                                <div>
                                    <label for="page-twitter-card" class="s-label">X / Twitter card</label>
                                    <select id="page-twitter-card" class="s-input" wire:model.change="page.twitter_card">
                                        <option value="">Large image</option>
                                        <option value="summary">Small summary</option>
                                    </select>
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Title, description, and image come from the fields above.</p>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="page-twitter-site" class="s-label">Site handle</label>
                                        <input id="page-twitter-site" type="text" class="s-input" placeholder="@yoursite" wire:model.blur="page.twitter_site">
                                    </div>
                                    <div>
                                        <label for="page-twitter-creator" class="s-label">Creator handle</label>
                                        <input id="page-twitter-creator" type="text" class="s-input" placeholder="@author" wire:model.blur="page.twitter_creator">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ============ Advanced (collapsible) ============ --}}
                    <div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-line" wire:key="group-advanced">
                        <button type="button" @click="open = !open" class="flex w-full cursor-pointer items-center justify-between gap-2 px-3 py-2.5 transition-colors hover:bg-white/[0.03]">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-medium text-ink">Advanced</span>
                                <span class="mt-0.5 block truncate text-[11px] text-faint">Indexing, canonical URL, structured data</span>
                            </span>
                            <svg class="h-3.5 w-3.5 shrink-0 text-faint transition-transform duration-150" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="space-y-4 border-t border-line p-3">
                                @foreach([
                                    'noindex' => ['Hide from search engines', 'Adds noindex so this page is left out of results.'],
                                    'nofollow' => ['Don\'t follow links', 'Asks crawlers not to follow links on this page.'],
                                ] as $toggleKey => [$toggleLabel, $toggleHint])
                                    @php $on = filter_var($page[$toggleKey] ?? false, FILTER_VALIDATE_BOOLEAN); @endphp
                                    <div class="flex items-center justify-between gap-3" wire:key="page-toggle-{{ $toggleKey }}">
                                        <span class="min-w-0">
                                            <span class="s-label !mb-0 block">{{ $toggleLabel }}</span>
                                            <span class="mt-0.5 block text-[11px] leading-relaxed text-faint">{{ $toggleHint }}</span>
                                        </span>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked="{{ $on ? 'true' : 'false' }}"
                                            class="relative inline-flex h-[18px] w-8 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 {{ $on ? 'bg-accent' : 'bg-white/12' }}"
                                            wire:click="$set('page.{{ $toggleKey }}', {{ $on ? 'false' : 'true' }})"
                                        >
                                            <span class="sr-only">Toggle {{ $toggleLabel }}</span>
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform duration-200 {{ $on ? 'translate-x-[15px]' : 'translate-x-[2px]' }}"></span>
                                        </button>
                                    </div>
                                @endforeach

                                <div class="s-divider"></div>

                                <div>
                                    <label for="page-canonical" class="s-label">Canonical URL</label>
                                    <input id="page-canonical" type="text" class="s-input !font-mono !text-xs" placeholder="Defaults to this page's URL" wire:model.blur="page.canonical_url">
                                </div>

                                <div>
                                    <label for="page-keywords" class="s-label">Keywords</label>
                                    <input id="page-keywords" type="text" class="s-input" placeholder="comma, separated, keywords" wire:model.blur="page.seo_keywords">
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Most search engines ignore these — optional.</p>
                                </div>

                                <div class="s-divider"></div>

                                <div x-data="{ uploading: false }">
                                    <label class="s-label">Favicon</label>
                                    <div class="flex gap-1.5">
                                        <input type="text" class="s-input !font-mono !text-xs" placeholder="https://… or upload" wire:model.blur="page.favicon">
                                        <label class="s-btn-outline relative shrink-0 cursor-pointer !px-2.5" :class="uploading && 'pointer-events-none opacity-60'" title="Upload a favicon">
                                            <input type="file" accept="image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon,image/webp" class="sr-only" x-on:change="
                                                const file = $event.target.files[0]; $event.target.value = '';
                                                if (!file) return;
                                                uploading = true;
                                                window.Studio.upload(file, { url: @js(route('studio.api.upload')), csrf: document.querySelector('meta[name=csrf-token]').content })
                                                    .then((url) => $wire.set('page.favicon', url))
                                                    .catch((e) => window.Studio.toast(e.message || 'Upload failed', 'error'))
                                                    .finally(() => uploading = false);
                                            ">
                                            <svg x-show="!uploading" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.636l2.955 3.129a.75.75 0 0 0 1.09-1.03l-4.25-4.5a.75.75 0 0 0-1.09 0l-4.25 4.5a.75.75 0 1 0 1.09 1.03L9.25 4.636v8.614Z" clip-rule="evenodd"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                                            <svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                        </label>
                                    </div>
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Overrides your site's favicon on this page.</p>
                                </div>

                                <div>
                                    <label for="page-theme-color" class="s-label">Theme color</label>
                                    <div class="flex gap-1.5">
                                        <input type="color" class="h-8 w-9 shrink-0 cursor-pointer rounded-lg border border-line bg-raised p-1" value="{{ ($page['theme_color'] ?? '') !== '' ? $page['theme_color'] : '#ffffff' }}" x-on:change="$wire.set('page.theme_color', $event.target.value)">
                                        <input id="page-theme-color" type="text" class="s-input !font-mono !text-xs" placeholder="#0b0b0d" wire:model.blur="page.theme_color">
                                    </div>
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Tints the browser chrome on mobile.</p>
                                </div>

                                <div class="s-divider"></div>

                                <div>
                                    <label for="page-json-ld" class="s-label">Structured data (JSON-LD)</label>
                                    <textarea id="page-json-ld" rows="4" class="s-input !font-mono !text-[11px]" placeholder='{"@@context": "https://schema.org", …}' wire:model.blur="page.json_ld"></textarea>
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Raw JSON, rendered as a script tag. Invalid JSON is skipped.</p>
                                </div>

                                <div>
                                    <label for="page-head-html" class="s-label">Custom head HTML</label>
                                    <textarea id="page-head-html" rows="3" class="s-input !font-mono !text-[11px]" placeholder="<meta …> <link …>" wire:model.blur="page.head_html"></textarea>
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">Injected verbatim into this page's &lt;head&gt;.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="s-divider"></div>

                    <div class="space-y-2">
                        <button wire:click="duplicatePage" class="s-btn-outline w-full">
                            Duplicate page
                        </button>
                        <button
                            wire:click="deletePage"
                            wire:confirm="Delete “{{ $page['title'] ?? 'this page' }}” and all of its content? This cannot be undone."
                            class="s-btn-danger w-full"
                        >
                            Delete page
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
