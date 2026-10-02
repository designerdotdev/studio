{{-- The Code view's file tree, docked on the stage's left edge beside the
     editor (home.blade.php): the whole application, a folder at a time, with
     the site's own folders (resources/designer, public/designer) tinted. The
     node list arrives flat from CodeWorkspace::tree() (path/name/type/depth/
     parent/design), so this renders as one x-for with indentation instead of
     a recursive include — folder open/close is a plain map in $store.code.

     Files and folders are made, renamed, duplicated and deleted here: from
     the header, from a row's context menu, or from the keyboard (↑ ↓ ← →
     move, F2 renames, ⌘⌫ deletes). A new one is named in a row of its own,
     in place; a rename edits the row's name in place. --}}
<div
    class="flex h-full min-h-0 flex-col"
    x-data="{
        /* The menu opens at the pointer, then moves back inside the window if it does not fit */
        fit() {
            const el = document.querySelector('[data-tree-menu]');
            if (!el) return;
            const menu = $store.code.menu;
            const box = el.getBoundingClientRect();
            const x = Math.max(8, Math.min(menu.x, window.innerWidth - box.width - 8));
            const y = Math.max(8, Math.min(menu.y, window.innerHeight - box.height - 8));
            if (x !== menu.x || y !== menu.y) $store.code.menu = { ...menu, x, y };
        },

        rows() { return [...this.$refs.tree.querySelectorAll('[data-row]')] },

        /* Arrow keys walk the rows; ← → fold and open; F2 renames; ⌘⌫ asks to delete */
        keys(event) {
            if (event.target.closest('input')) return;
            const row = event.target.closest('[data-row]');
            if (!row) return;

            const code = $store.code;
            const node = code.node(row.dataset.row);
            const rows = this.rows();
            const at = rows.indexOf(row);
            const go = (index) => { rows[index]?.focus(); event.preventDefault(); };

            if (event.key === 'ArrowDown') return go(Math.min(at + 1, rows.length - 1));
            if (event.key === 'ArrowUp') return go(Math.max(at - 1, 0));
            if (event.key === 'Home') return go(0);
            if (event.key === 'End') return go(rows.length - 1);

            if (!node) return;

            if (event.key === 'ArrowRight' && node.type === 'dir' && !node.inert) {
                event.preventDefault();
                return code.openFolders[node.path] ? rows[at + 1]?.focus() : code.toggleFolder(node.path);
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                if (node.type === 'dir' && code.openFolders[node.path]) return code.toggleFolder(node.path);
                return rows.find((r) => r.dataset.row === node.parent)?.focus();
            }

            if (event.key === 'F2') {
                event.preventDefault();
                return code.startRename(node.path);
            }

            if ((event.key === 'Backspace' || event.key === 'Delete') && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                return code.askDelete(node.path);
            }

            // The menu key, or Shift+F10: the menu, at the row
            if (event.key === 'ContextMenu' || (event.key === 'F10' && event.shiftKey)) {
                event.preventDefault();
                const box = row.getBoundingClientRect();
                code.openMenu({ clientX: box.left + 24, clientY: box.bottom - 4 }, node.path);
            }
        },
    }"
    x-effect="if ($store.code.menu.open) $nextTick(() => fit())"
    @keydown.escape.window="$store.code.closeMenu()"
    @mousedown.window="if ($store.code.menu.open && !$event.target.closest('[data-tree-menu]')) $store.code.closeMenu()"
    @blur.window="$store.code.closeMenu()"
    @resize.window="$store.code.closeMenu()"
>
    {{-- Header: the project, then what can be done to its tree. The same
         height as the editor's tab strip, so one hairline runs across the
         view. New file and New folder land in the selected folder. --}}
    <div class="s-code-files-head">
        <p class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink" title="{{ basename(base_path()) }}">{{ basename(base_path()) }}</p>

        <button type="button" class="s-icon-btn" @click="$store.code.startCreate('file')" title="New file" aria-label="New file">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
        </button>

        <button type="button" class="s-icon-btn" @click="$store.code.startCreate('dir')" title="New folder" aria-label="New folder">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
        </button>

        <button
            type="button"
            class="s-icon-btn"
            @click="$store.code.loadTree()"
            :class="$store.code.loading && 'opacity-50'"
            title="Refresh"
            aria-label="Refresh the file tree"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5V9H15"/></svg>
        </button>

        <button type="button" class="s-icon-btn" @click="$store.code.collapseAll()" title="Collapse all folders" aria-label="Collapse all folders">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 4.75h8.75a2 2 0 0 1 2 2v8.75"/><rect x="4.75" y="8.5" width="10.75" height="10.75" rx="2"/><path d="M7.9 13.875h4.45"/></svg>
        </button>

        <button
            type="button"
            class="s-icon-btn"
            @click="$store.studio.toggleDock('code')"
            title="Hide the files (⌘B)"
            aria-label="Hide the files"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4.75" width="18" height="14.5" rx="2.25"/><path d="M9 4.75v14.5"/></svg>
        </button>
    </div>

    {{-- New-section form (the tree's menu, inside the site) --}}
    <div x-show="$store.code.newSectionOpen" x-cloak class="shrink-0 border-b border-line bg-wash-faint px-3 py-2.5">
        <p class="s-microlabel pb-1.5">New section</p>
        <div class="flex flex-col gap-1.5">
            <input
                data-new-section
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
            <p class="break-words text-[10.5px] leading-relaxed text-faint">Creates <span class="break-all font-mono">views/components/sections/&lt;name&gt;.blade.php</span> and its <span class="font-mono">.yml</span>.</p>
            <div class="flex items-center gap-1.5">
                <button class="s-btn-accent flex-1" @click="$store.code.createSection()" :disabled="$store.code.creating">
                    <span x-show="!$store.code.creating">Create</span>
                    <span x-show="$store.code.creating" x-cloak>Creating…</span>
                </button>
                <button class="s-btn-ghost" @click="$store.code.newSectionOpen = false">Cancel</button>
            </div>
        </div>
    </div>

    {{-- The tree. A right-click on a row is that row's menu; on the space
         under the rows, the project's. --}}
    <div
        x-ref="tree"
        class="s-tree min-h-0 flex-1 overflow-y-auto px-1.5 pb-6 pt-1.5"
        role="tree"
        aria-label="Project files"
        @keydown="keys($event)"
        @scroll.passive="$store.code.closeMenu()"
        @contextmenu.prevent="$store.code.openMenu($event, null)"
    >
        <template x-for="node in $store.code.visibleNodes" :key="node.path">
            {{-- An inert row (vendor, node_modules, .git, a binary or oversized
                 file) is listed so the project reads true, and does nothing --}}
            <div
                class="s-tree-row group"
                :class="{
                    'is-active': $store.code.active === node.path,
                    'is-selected': $store.code.selected === node.path && $store.code.active !== node.path,
                    'is-target': $store.code.menu.open && $store.code.menu.path === node.path,
                    'is-inert': node.inert,
                }"
                :title="node.note"
                @contextmenu.prevent.stop="node.type !== 'draft' && $store.code.openMenu($event, node.path)"
            >
                {{-- A file or folder being named, before it exists --}}
                <template x-if="node.type === 'draft'">
                    <div class="flex min-w-0 flex-1 items-center gap-1.5 py-0.5 pr-1" :style="`padding-left: ${(node.kind === 'dir' ? 22 : 14) + node.depth * 12}px`">
                        <template x-if="node.kind === 'dir'">
                            <svg class="h-3.5 w-3.5 shrink-0 text-faint/70" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2.25 6A2.25 2.25 0 0 1 4.5 3.75h4.129a2.25 2.25 0 0 1 1.59.659l1.622 1.622a.75.75 0 0 0 .53.219H19.5A2.25 2.25 0 0 1 21.75 8.5v9.25A2.25 2.25 0 0 1 19.5 20H4.5a2.25 2.25 0 0 1-2.25-2.25V6Z"/></svg>
                        </template>
                        <input
                            type="text"
                            class="s-tree-input"
                            :class="node.kind === 'file' && 'font-mono'"
                            :value="$store.code.draft?.value"
                            @input="$store.code.draft && ($store.code.draft.value = $event.target.value)"
                            x-init="$nextTick(() => $el.focus())"
                            :placeholder="node.kind === 'dir' ? 'Folder name' : 'File name'"
                            :aria-label="node.kind === 'dir' ? 'New folder name' : 'New file name'"
                            spellcheck="false"
                            autocomplete="off"
                            @keydown.enter.prevent="$store.code.commitCreate()"
                            @keydown.escape.stop.prevent="$store.code.cancelCreate()"
                            @blur="$store.code.commitCreate()"
                        >
                    </div>
                </template>

                {{-- Folder. The site's folders keep their accent tint: they are
                     the thing you came looking for. x-if rather than x-show for
                     the folder/file pair: rows are reused by key when the tree
                     reloads, and an x-show'd file button could survive on a
                     folder row beside its name. --}}
                <template x-if="node.type === 'dir'">
                    <button
                        type="button"
                        class="s-tree-hit"
                        :data-row="node.path"
                        role="treeitem"
                        :aria-expanded="node.inert ? null : !!$store.code.openFolders[node.path]"
                        :class="node.inert ? 'cursor-default' : 'cursor-pointer'"
                        :style="`padding-left: ${6 + node.depth * 12}px`"
                        @click="$store.code.selected = node.path; node.inert || $store.code.renaming === node.path || $store.code.toggleFolder(node.path)"
                    >
                        <svg class="h-2.5 w-2.5 shrink-0 text-faint transition-transform duration-100"
                            :class="{ 'rotate-90': $store.code.openFolders[node.path] && !node.inert, 'opacity-0': node.inert }"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        <svg class="h-3.5 w-3.5 shrink-0" :class="node.design ? 'text-accent' : (node.inert ? 'text-faint/40' : 'text-faint/70')"
                            viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2.25 6A2.25 2.25 0 0 1 4.5 3.75h4.129a2.25 2.25 0 0 1 1.59.659l1.622 1.622a.75.75 0 0 0 .53.219H19.5A2.25 2.25 0 0 1 21.75 8.5v9.25A2.25 2.25 0 0 1 19.5 20H4.5a2.25 2.25 0 0 1-2.25-2.25V6Z"/></svg>
                        <span x-show="$store.code.renaming !== node.path" class="truncate text-[12.5px]" :class="node.design && 'text-ink'" x-text="node.name"></span>
                    </button>
                </template>

                {{-- File --}}
                <template x-if="node.type === 'file'">
                    <button
                        type="button"
                        class="s-tree-hit"
                        :data-row="node.path"
                        role="treeitem"
                        :aria-selected="$store.code.active === node.path"
                        :class="node.inert ? 'cursor-default' : 'cursor-pointer'"
                        :style="`padding-left: ${20 + node.depth * 12}px`"
                        @click="$store.code.selected = node.path; node.inert || $store.code.renaming === node.path || $store.code.openFile(node.path)"
                    >
                        <span x-show="$store.code.renaming !== node.path" class="truncate font-mono text-[11.5px]" :class="node.design && !node.inert && 'text-ink/90'" x-text="node.name"></span>
                        <span x-show="$store.code.dirty[node.path] && $store.code.renaming !== node.path" x-cloak class="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-accent" title="Unsaved changes"></span>
                    </button>
                </template>

                {{-- Renaming: the name becomes a field, on top of the row. The
                     name up to its first dot is selected — the part that changes. --}}
                <template x-if="node.type !== 'draft' && $store.code.renaming === node.path">
                    <input
                        type="text"
                        class="s-tree-input is-rename"
                        :class="node.type === 'file' && 'font-mono'"
                        :style="`left: ${(node.type === 'dir' ? 36 : 14) + node.depth * 12}px`"
                        x-model="$store.code.renameValue"
                        x-init="$nextTick(() => { $el.focus(); const dot = $el.value.indexOf('.'); $el.setSelectionRange(0, dot > 0 ? dot : $el.value.length); })"
                        aria-label="New name"
                        spellcheck="false"
                        autocomplete="off"
                        @click.stop
                        @keydown.enter.prevent="$store.code.commitRename()"
                        @keydown.escape.stop.prevent="$store.code.cancelRename()"
                        @blur="$store.code.commitRename()"
                    >
                </template>

                {{-- Two-step delete keeps destructive intent inside the row --}}
                <div x-show="node.type !== 'draft' && $store.code.confirmDelete === node.path" x-cloak class="flex shrink-0 items-center gap-1 pr-1.5" @click.stop>
                    <button
                        class="rounded bg-danger px-1.5 py-0.5 text-[10px] font-semibold text-white"
                        x-effect="if ($store.code.confirmDelete === node.path) $nextTick(() => $el.focus())"
                        @click="$store.code.remove(node.path)"
                        @keydown.escape.stop="$store.code.confirmDelete = null"
                        :title="node.type === 'dir' ? 'Deletes the folder and everything in it' : 'Deletes the file — a section takes its .yml with it'"
                    >Delete</button>
                    <button class="rounded px-1.5 py-0.5 text-[10px] font-medium hover:bg-wash" @click="$store.code.confirmDelete = null">Cancel</button>
                </div>
            </div>
        </template>

        <p x-show="!$store.code.loading && !$store.code.nodes.length" x-cloak class="px-2 py-6 text-center text-[12px] text-faint">
            No files were found.
        </p>
    </div>

    {{-- The context menu. Dark, like every popover. On <body>, so nothing
         above the tree can clip it or change what `fixed` means for it. --}}
    <template x-teleport="body">
        <div
            data-tree-menu
            x-show="$store.code.menu.open"
            x-cloak
            x-transition:enter="transition duration-100 ease-out"
            x-transition:enter-start="scale-[0.97] opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            class="s-pop s-tree-menu"
            :style="`top: ${$store.code.menu.y}px; left: ${$store.code.menu.x}px`"
            role="menu"
            aria-label="File actions"
            x-data="{
                get node() { return $store.code.node($store.code.menu.path) },
                get path() { return $store.code.menu.path },
                get dir() { return this.node?.type === 'dir' },
                get file() { return this.node?.type === 'file' },
                /* What can be done to it at all: not a dependency tree or a binary, not a folder that has to stay */
                get live() { return !!this.node && !this.node.inert },
                get movable() { return this.live && !$store.code.locked(this.node) },
                /* New things land inside the site when the row is, or holds, the site */
                get inSite() { return !this.node || this.node.design || $store.code.siteRoots.some((root) => root.startsWith(this.node.path + '/')) },
            }"
            @contextmenu.prevent
        >
            {{-- What the menu is about --}}
            <p class="s-tree-menu-head" x-text="node ? node.name : @js(basename(base_path()))"></p>

            <template x-if="node?.inert">
                <p class="px-2.5 pb-1.5 text-[11px] leading-snug text-faint" x-text="node.note"></p>
            </template>

            <template x-if="!node || live">
                <div>
                    <button type="button" class="s-menu-item" role="menuitem" @click="$store.code.startCreate('file', path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        New file…
                    </button>
                    <button type="button" class="s-menu-item" role="menuitem" @click="$store.code.startCreate('dir', path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                        New folder…
                    </button>
                    <button type="button" class="s-menu-item" role="menuitem" x-show="inSite" @click="$store.code.closeMenu(); $store.code.newSectionOpen = true; $nextTick(() => document.querySelector('[data-new-section]')?.focus())">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h12a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18v-2.25Z"/></svg>
                        New section…
                    </button>
                </div>
            </template>

            <template x-if="live">
                <div>
                    <div class="s-pop-divider"></div>

                    <button type="button" class="s-menu-item" role="menuitem" x-show="movable" @click="$store.code.startRename(path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                        <span class="flex-1">Rename…</span>
                        <span class="s-kbd">F2</span>
                    </button>
                    <button type="button" class="s-menu-item" role="menuitem" x-show="file" @click="$store.code.duplicate(path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
                        Duplicate
                    </button>
                </div>
            </template>

            <template x-if="node">
                <div>
                    <div class="s-pop-divider"></div>

                    <button type="button" class="s-menu-item" role="menuitem" @click="$store.code.copyPath(path, true)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                        Copy path
                    </button>
                    <button type="button" class="s-menu-item" role="menuitem" @click="$store.code.copyPath(path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                        Copy relative path
                    </button>
                </div>
            </template>

            <template x-if="!node || (dir && live)">
                <div>
                    <div class="s-pop-divider"></div>

                    <button type="button" class="s-menu-item" role="menuitem" @click="$store.code.closeMenu(); $store.code.reloadFolder(path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5V9H15"/></svg>
                        Refresh
                    </button>
                    <button type="button" class="s-menu-item" role="menuitem" x-show="!node" @click="$store.code.closeMenu(); $store.code.collapseAll()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 4.75h8.75a2 2 0 0 1 2 2v8.75"/><rect x="4.75" y="8.5" width="10.75" height="10.75" rx="2"/><path d="M7.9 13.875h4.45"/></svg>
                        Collapse all folders
                    </button>
                </div>
            </template>

            <template x-if="movable">
                <div>
                    <div class="s-pop-divider"></div>

                    <button type="button" class="s-menu-item is-danger" role="menuitem" @click="$store.code.askDelete(path)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        <span class="flex-1" x-text="dir ? 'Delete folder…' : 'Delete…'"></span>
                        <span class="s-kbd">⌘⌫</span>
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
