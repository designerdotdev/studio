{{-- Code mode's file tree. The node list arrives flat from
     CodeWorkspace::tree() (path/name/type/depth/parent/design), so this
     renders as one x-for with indentation instead of a recursive include —
     folder open/close is a plain map in $store.code.

     Two views: Designer (just the site — resources/designer and
     public/designer) and Laravel (the whole app, the site's folders
     tinted). A file has the same path in both, so both drive one buffer
     and one save path. --}}
<div class="flex h-full min-h-0 flex-col">
    {{-- Header --}}
    <div class="flex h-11 shrink-0 items-center gap-1.5 px-3">
        <h2 class="flex-1 truncate text-[13px] font-semibold text-ink">Files</h2>

        <button
            type="button"
            class="s-icon-btn"
            @click="$store.code.newSectionOpen = true; $nextTick(() => $refs.newName?.focus())"
            title="New section"
            aria-label="New section"
            x-show="$store.code.view === 'designer'"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        </button>

        <button
            type="button"
            class="s-icon-btn"
            @click="$store.code.loadTree()"
            :class="$store.code.loading && 'opacity-50'"
            title="Refresh the file tree"
            aria-label="Refresh the file tree"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5V9H15"/></svg>
        </button>
    </div>

    {{-- Which tree: just the design surface, or the whole application --}}
    <div class="shrink-0 border-b border-line px-3 pb-2">
        <div class="s-seg !h-7 w-full">
            <button
                type="button"
                class="s-seg-btn !h-6 !w-auto flex-1 gap-1.5 text-[11.5px] font-medium"
                :class="$store.code.view === 'designer' && 'is-active'"
                @click="$store.code.setView('designer')"
                title="Only the site — resources/designer and public/designer"
            >
                <span class="s-tree-dot"></span>
                Designer
            </button>
            <button
                type="button"
                class="s-seg-btn !h-6 !w-auto flex-1 text-[11.5px] font-medium"
                :class="$store.code.view === 'laravel' && 'is-active'"
                @click="$store.code.setView('laravel')"
                title="The whole application — app, config, database, resources, routes, public, tests"
            >
                Laravel
            </button>
        </div>
    </div>

    {{-- New-section form --}}
    <div x-show="$store.code.newSectionOpen" x-cloak class="shrink-0 border-y border-line bg-raised/60 px-3 py-2.5">
        <p class="s-microlabel pb-1.5">New section</p>
        <div class="flex flex-col gap-1.5">
            <input
                x-ref="newName"
                x-model="$store.code.newName"
                @keydown.enter.prevent="$store.code.createSection()"
                @keydown.escape.stop="$store.code.newSectionOpen = false"
                class="s-input"
                placeholder="section-name"
                aria-label="Section name"
            >
            <input
                x-model="$store.code.newLabel"
                @keydown.enter.prevent="$store.code.createSection()"
                class="s-input"
                placeholder="Title (optional)"
                aria-label="Title"
            >
            <input
                x-model="$store.code.newCategory"
                @keydown.enter.prevent="$store.code.createSection()"
                class="s-input"
                placeholder="category (heroes, features…)"
                aria-label="Category"
            >
            <p class="text-[10.5px] leading-relaxed text-faint">Creates <span class="font-mono">views/components/sections/&lt;name&gt;.blade.php</span> and its <span class="font-mono">.yml</span>.</p>
            <div class="flex items-center gap-1.5">
                <button class="s-btn-accent flex-1" @click="$store.code.createSection()" :disabled="$store.code.creating">
                    <span x-show="!$store.code.creating">Create</span>
                    <span x-show="$store.code.creating" x-cloak>Creating…</span>
                </button>
                <button class="s-btn-ghost" @click="$store.code.newSectionOpen = false">Cancel</button>
            </div>
        </div>
    </div>

    {{-- The tree --}}
    <div class="min-h-0 flex-1 overflow-y-auto px-1.5 pb-3 pt-1">
        <template x-for="node in $store.code.visibleNodes" :key="node.path">
            <div class="group relative flex items-center rounded-md transition-colors"
                :class="$store.code.active === node.path ? 'bg-wash-strong text-ink' : 'text-soft hover:bg-wash hover:text-ink'">

                {{-- Folder. The site's folders keep their accent tint in the
                     Laravel view, where they are the thing you came looking for. --}}
                <button
                    x-show="node.type === 'dir'"
                    type="button"
                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-1.5 py-1 pr-1.5 text-left"
                    :style="`padding-left: ${6 + node.depth * 12}px`"
                    @click="$store.code.toggleFolder(node.path)"
                >
                    <svg class="h-2.5 w-2.5 shrink-0 text-faint transition-transform duration-100"
                        :class="$store.code.openFolders[node.path] && 'rotate-90'"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    <svg class="h-3.5 w-3.5 shrink-0" :class="node.design ? 'text-accent' : 'text-faint/70'"
                        viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2.25 6A2.25 2.25 0 0 1 4.5 3.75h4.129a2.25 2.25 0 0 1 1.59.659l1.622 1.622a.75.75 0 0 0 .53.219H19.5A2.25 2.25 0 0 1 21.75 8.5v9.25A2.25 2.25 0 0 1 19.5 20H4.5a2.25 2.25 0 0 1-2.25-2.25V6Z"/></svg>
                    <span class="truncate text-[12.5px]" :class="node.design && 'text-ink'" x-text="node.name"></span>
                </button>

                {{-- File --}}
                <button
                    x-show="node.type === 'file'"
                    type="button"
                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-1.5 py-1 pr-1.5 text-left"
                    :style="`padding-left: ${20 + node.depth * 12}px`"
                    @click="$store.code.openFile(node.path)"
                >
                    <span class="truncate font-mono text-[11.5px]" :class="node.design && 'text-ink/90'" x-text="node.name"></span>
                    <span x-show="$store.code.dirty[node.path]" x-cloak class="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-accent" title="Unsaved changes"></span>
                </button>

                {{-- Two-step delete keeps destructive intent inside the row --}}
                <div x-show="node.type === 'file' && $store.code.confirmDelete === node.path" x-cloak class="flex shrink-0 items-center gap-1 pr-1.5" @click.stop>
                    <button class="rounded bg-danger px-1.5 py-0.5 text-[10px] font-semibold text-white" @click="$store.code.deleteFile(node.path)">Delete</button>
                    <button class="rounded px-1.5 py-0.5 text-[10px] font-medium hover:bg-wash" @click="$store.code.confirmDelete = null">Cancel</button>
                </div>

                <button
                    x-show="node.type === 'file' && node.design && $store.code.confirmDelete !== node.path"
                    x-cloak
                    type="button"
                    class="s-icon-btn !h-6 !w-6 mr-1 hidden shrink-0 group-hover:flex"
                    @click.stop="$store.code.confirmDelete = node.path"
                    title="Delete this file (a section takes its .yml with it)"
                >
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21L18.16 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92a2.13 2.13 0 0 0-2.09-2.2 51.96 51.96 0 0 0-3.32 0 2.13 2.13 0 0 0-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0"/></svg>
                </button>
            </div>
        </template>

        <p x-show="!$store.code.loading && !$store.code.nodes.length" x-cloak class="px-2 py-6 text-center text-[12px] text-faint">
            No editable files were found.
        </p>
    </div>
</div>
