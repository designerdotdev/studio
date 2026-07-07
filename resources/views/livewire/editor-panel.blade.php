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
                @if($selected['hidden'])
                    <span class="s-chip !text-warn">Hidden</span>
                @endif
            </div>

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
                    wire:confirm="Delete this section?"
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
            <div class="shrink-0 border-b border-line p-2">
                <div class="grid grid-cols-2 gap-0.5 rounded-lg border border-line bg-shell p-0.5">
                    <button
                        wire:click="$set('tab', 'sections')"
                        class="flex h-7 cursor-pointer items-center justify-center rounded-[7px] text-[13px] font-medium transition-all duration-150 {{ $tab === 'sections' ? 'bg-raised text-ink shadow-sm' : 'text-faint hover:text-soft' }}"
                    >
                        Sections
                    </button>
                    <button
                        wire:click="$set('tab', 'page')"
                        class="flex h-7 cursor-pointer items-center justify-center rounded-[7px] text-[13px] font-medium transition-all duration-150 {{ $tab === 'page' ? 'bg-raised text-ink shadow-sm' : 'text-faint hover:text-soft' }}"
                    >
                        Page
                    </button>
                </div>
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

                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-line bg-raised {{ $section['hidden'] ? 'text-faint' : 'text-soft' }}">
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
                                            wire:confirm="Delete this section?"
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

                    <p class="s-microlabel">Search engine listing</p>

                    <div>
                        <label for="page-seo-title" class="s-label">SEO title</label>
                        <input id="page-seo-title" type="text" class="s-input" placeholder="{{ $page['title'] ?? '' }}" wire:model.blur="page.seo_title">
                    </div>

                    <div>
                        <label for="page-seo-description" class="s-label">SEO description</label>
                        <textarea id="page-seo-description" rows="3" class="s-input" placeholder="Describe this page for search results…" wire:model.blur="page.seo_description"></textarea>
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
