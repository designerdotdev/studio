<div
    class="flex h-full min-h-0 flex-col"
    x-data="{
        urls: {
            list: @js(route('studio.api.media.index')),
            upload: @js(route('studio.api.media.upload')),
            folder: @js(route('studio.api.media.folder')),
            update: @js(route('studio.api.media.update')),
            duplicate: @js(route('studio.api.media.duplicate')),
            destroy: @js(route('studio.api.media.destroy')),
        },
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',

        loaded: false,
        loading: false,
        dir: '',
        parent: null,
        readonly: false,
        folders: [],
        files: [],
        allFolders: [],
        q: '',
        uploading: 0,
        dragOver: false,

        // picker mode: the id of the field waiting for an image
        picker: null,

        // per-item UI
        menu: null,          // { item, x, y }
        moveFor: null,       // item whose 'Move to…' submenu is open
        renaming: null,      // path being renamed
        renameValue: '',
        newFolderOpen: false,
        newFolderName: '',
        lightbox: null,      // index into visibleFiles

        get visibleFiles() {
            const q = this.q.trim().toLowerCase();
            return q ? this.files.filter((f) => f.name.toLowerCase().includes(q)) : this.files;
        },
        get visibleFolders() {
            const q = this.q.trim().toLowerCase();
            return q ? this.folders.filter((f) => f.name.toLowerCase().includes(q)) : this.folders;
        },
        get crumbs() {
            const parts = this.dir ? this.dir.split('/') : [];
            return parts.map((name, i) => ({ name, path: parts.slice(0, i + 1).join('/') }));
        },

        init() {
            window.addEventListener('studio:rail', (e) => { if (e.detail.name === 'media' && !this.loaded) this.load(this.dir); });
            window.addEventListener('studio:media-pick', (e) => {
                this.picker = e.detail.id;
                Alpine.store('studio').setRail('media', true);
                if (!this.loaded) this.load(this.dir);
            });
            if (Alpine.store('studio').rail === 'media') this.load('');
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', ...(options.headers || {}) },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error(data.message || (data.errors ? Object.values(data.errors).flat()[0] : null) || `Request failed (${response.status})`);
            }
            return data;
        },
        json(method, url, body) {
            return this.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        },

        async load(dir) {
            this.loading = true;
            this.menu = null;
            try {
                const data = await this.request(this.urls.list + '?dir=' + encodeURIComponent(dir ?? ''));
                this.dir = data.dir;
                this.parent = data.parent;
                this.readonly = data.readonly;
                this.folders = data.folders;
                this.files = data.files;
                this.allFolders = ['', ...data.all_folders];
                this.loaded = true;
            } catch (e) {
                window.Studio.toast(e.message, 'error');
            }
            this.loading = false;
        },

        async uploadFiles(fileList) {
            const files = Array.from(fileList || []);
            if (!files.length) return;
            for (const file of files) {
                this.uploading++;
                try {
                    const body = new FormData();
                    body.append('file', file);
                    body.append('dir', this.dir);
                    const data = await this.request(this.urls.upload, { method: 'POST', body });
                    this.files.unshift(data.file);
                } catch (e) {
                    window.Studio.toast(`${file.name}: ${e.message}`, 'error');
                }
                this.uploading--;
            }
        },

        async createFolder() {
            const name = this.newFolderName.trim();
            if (!name) return;
            try {
                await this.json('POST', this.urls.folder, { dir: this.dir, name });
                this.newFolderOpen = false;
                this.newFolderName = '';
                await this.load(this.dir);
            } catch (e) { window.Studio.toast(e.message, 'error'); }
        },

        openMenu(item, event) {
            event.preventDefault();
            const rect = this.$root.getBoundingClientRect();
            this.moveFor = null;
            this.menu = { item, x: Math.min(event.clientX - rect.left, rect.width - 180), y: event.clientY - rect.top + this.$refs.scroller.scrollTop - 40 };
        },

        startRename(item) {
            this.menu = null;
            this.renaming = item.path;
            this.renameValue = item.type ? item.name.replace(/\.[^.]+$/, '') : item.name;
        },
        async saveRename() {
            const path = this.renaming, name = this.renameValue.trim();
            this.renaming = null;
            if (!path || !name) return;
            try {
                await this.json('PATCH', this.urls.update, { path, name });
                await this.load(this.dir);
            } catch (e) { window.Studio.toast(e.message, 'error'); }
        },
        async move(item, to) {
            this.menu = null;
            try {
                await this.json('PATCH', this.urls.update, { path: item.path, to });
                await this.load(this.dir);
            } catch (e) { window.Studio.toast(e.message, 'error'); }
        },
        async duplicate(item) {
            this.menu = null;
            try {
                const data = await this.json('POST', this.urls.duplicate, { path: item.path });
                this.files.unshift(data.file);
            } catch (e) { window.Studio.toast(e.message, 'error'); }
        },
        async remove(item) {
            this.menu = null;
            try {
                await this.json('DELETE', this.urls.destroy, { path: item.path });
                await this.load(this.dir);
                window.Studio.toast('Deleted', 'success');
            } catch (e) { window.Studio.toast(e.message, 'error'); }
        },
        copyUrl(item) {
            this.menu = null;
            const done = () => window.Studio.toast('URL copied', 'success');
            if (navigator.clipboard) { navigator.clipboard.writeText(item.url).then(done).catch(() => {}); return; }
            const el = document.createElement('textarea'); el.value = item.url; document.body.appendChild(el); el.select();
            try { document.execCommand('copy') && done(); } finally { el.remove(); }
        },

        choose(item) {
            if (this.picker) {
                window.dispatchEvent(new CustomEvent('studio:media-picked', { detail: { id: this.picker, url: item.url } }));
                this.picker = null;
                Alpine.store('studio').setRail('sections', true);
                return;
            }
            this.lightbox = this.visibleFiles.indexOf(item);
        },
        cancelPick() {
            window.dispatchEvent(new CustomEvent('studio:media-picked', { detail: { id: this.picker, url: null } }));
            this.picker = null;
        },
        size(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        },
    }"
    @keydown.escape.window="if (lightbox !== null) lightbox = null; else if (menu) menu = null; else if (picker) cancelPick()"
    @keydown.arrow-right.window="if (lightbox !== null && lightbox < visibleFiles.length - 1) lightbox++"
    @keydown.arrow-left.window="if (lightbox !== null && lightbox > 0) lightbox--"
    @click.window="menu = null"
>
    {{-- Header --}}
    <div class="flex h-11 shrink-0 items-center gap-1 px-3">
        <p class="s-microlabel flex-1">Media</p>
        <button type="button" class="s-icon-btn" title="New folder" aria-label="New folder" :disabled="readonly" @click.stop="newFolderOpen = !newFolderOpen; $nextTick(() => $refs.newFolder?.focus())">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 0 0 2 4.75v3.26a3.235 3.235 0 0 1 1.75-.51h12.5c.644 0 1.245.188 1.75.51V6.75A1.75 1.75 0 0 0 16.25 5h-4.836a.25.25 0 0 1-.177-.073L9.823 3.513A1.75 1.75 0 0 0 8.586 3H3.75ZM3.75 9A1.75 1.75 0 0 0 2 10.75v4.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0 0 18 15.25v-4.5A1.75 1.75 0 0 0 16.25 9H3.75Z"/></svg>
        </button>
        <label class="s-icon-btn cursor-pointer" title="Upload images" :class="readonly && 'pointer-events-none opacity-40'">
            <input type="file" multiple accept="image/*" class="sr-only" @change="uploadFiles($event.target.files); $event.target.value = ''">
            <svg x-show="!uploading" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.636l2.955 3.129a.75.75 0 0 0 1.09-1.03l-4.25-4.5a.75.75 0 0 0-1.09 0l-4.25 4.5a.75.75 0 1 0 1.09 1.03L9.25 4.636v8.614Z" clip-rule="evenodd"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
            <svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
        </label>
    </div>

    {{-- Picker banner --}}
    <div x-show="picker" x-cloak class="flex shrink-0 items-center gap-2 border-b border-accent/40 bg-accent/12 px-3 py-2 text-xs text-ink">
        <span class="flex-1">Click an image to use it</span>
        <button type="button" class="s-btn-ghost !h-6 !px-2 !text-[11px]" @click="cancelPick()">Cancel</button>
    </div>

    {{-- Search + breadcrumb --}}
    <div class="shrink-0 space-y-2 border-b border-line px-3 py-2">
        <input type="search" class="s-input !h-7 !text-xs" placeholder="Search this folder…" x-model="q">
        <div class="flex min-w-0 items-center gap-1 text-[11px] text-faint">
            <button type="button" class="shrink-0 hover:text-ink" :class="dir === '' && 'text-ink'" @click="load('')">Library</button>
            <template x-for="crumb in crumbs" :key="crumb.path">
                <span class="flex min-w-0 items-center gap-1">
                    <span>/</span>
                    <button type="button" class="truncate hover:text-ink" :class="crumb.path === dir && 'text-ink'" @click="load(crumb.path)" x-text="crumb.name"></button>
                </span>
            </template>
        </div>
        <div x-show="newFolderOpen" x-cloak class="flex gap-1.5">
            <input type="text" class="s-input !h-7 !text-xs" placeholder="Folder name" x-ref="newFolder" x-model="newFolderName" @keydown.enter="createFolder()" @keydown.escape="newFolderOpen = false">
            <button type="button" class="s-btn-primary !h-7 !px-2.5 !text-[11px]" @click="createFolder()">Create</button>
        </div>
    </div>

    {{-- Grid --}}
    <div
        class="relative min-h-0 flex-1 overflow-y-auto p-2"
        x-ref="scroller"
        @dragover.prevent="dragOver = !readonly"
        @dragleave="dragOver = false"
        @drop.prevent="dragOver = false; if (!readonly) uploadFiles($event.dataTransfer.files)"
        :class="dragOver && 'ring-2 ring-inset ring-accent/60'"
    >
        <div x-show="loading && !loaded" class="p-4 text-center text-xs text-faint">Loading…</div>

        <template x-if="loaded && !visibleFolders.length && !visibleFiles.length">
            <div class="flex flex-col items-center gap-2 px-4 py-10 text-center text-xs text-faint">
                <svg class="h-6 w-6 text-faint/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
                <span x-text="q ? 'Nothing matches.' : 'No images here yet. Drop files to upload.'"></span>
            </div>
        </template>

        {{-- Folders --}}
        <div x-show="visibleFolders.length" class="mb-2 flex flex-col gap-0.5">
            <template x-for="folder in visibleFolders" :key="folder.path">
                <div class="s-section-row group" @contextmenu="!folder.readonly && openMenu(folder, $event)">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" @click="load(folder.path)">
                        <svg class="h-4 w-4 shrink-0 text-faint" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 0 0 2 4.75v3.26a3.235 3.235 0 0 1 1.75-.51h12.5c.644 0 1.245.188 1.75.51V6.75A1.75 1.75 0 0 0 16.25 5h-4.836a.25.25 0 0 1-.177-.073L9.823 3.513A1.75 1.75 0 0 0 8.586 3H3.75ZM3.75 9A1.75 1.75 0 0 0 2 10.75v4.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0 0 18 15.25v-4.5A1.75 1.75 0 0 0 16.25 9H3.75Z"/></svg>
                        <template x-if="renaming === folder.path">
                            <input type="text" class="s-input !h-6 !text-xs" x-init="$nextTick(() => { $el.focus(); $el.select() })" x-model="renameValue" @click.stop @keydown.enter="saveRename()" @keydown.escape="renaming = null" @blur="saveRename()">
                        </template>
                        <template x-if="renaming !== folder.path">
                            <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink/85" x-text="folder.name"></span>
                        </template>
                        <span class="text-[10.5px] text-faint" x-text="folder.count"></span>
                    </button>
                    <button type="button" class="s-icon-btn !h-6 !w-6 opacity-0 group-hover:opacity-100" x-show="!folder.readonly" @click.stop="openMenu(folder, $event)" title="Folder actions">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Files --}}
        <div class="grid grid-cols-3 gap-1.5">
            <template x-for="file in visibleFiles" :key="file.path">
                <div class="s-media-tile group" :class="picker && 'is-pickable'" @contextmenu="!file.readonly && openMenu(file, $event)">
                    <button type="button" class="block aspect-square w-full overflow-hidden rounded-lg bg-shell" @click="choose(file)" :title="file.name">
                        <img :src="file.url" :alt="file.name" class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.04]" loading="lazy">
                    </button>
                    <template x-if="renaming === file.path">
                        <input type="text" class="s-input mt-1 !h-6 !text-[11px]" x-init="$nextTick(() => { $el.focus(); $el.select() })" x-model="renameValue" @keydown.enter="saveRename()" @keydown.escape="renaming = null" @blur="saveRename()">
                    </template>
                    <template x-if="renaming !== file.path">
                        <p class="mt-1 truncate text-[10.5px] text-faint" x-text="file.name"></p>
                    </template>
                    <button type="button" class="s-media-tile-menu" x-show="!file.readonly" @click.stop="openMenu(file, $event)" title="Actions">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Context menu --}}
        <div x-show="menu" x-cloak class="s-pop absolute z-30 w-44" :style="menu && `left:${menu.x}px; top:${menu.y}px`" @click.stop role="menu">
            <template x-if="menu">
                <div>
                    <template x-if="menu.item.type">
                        <div>
                            <button type="button" class="s-menu-item" @click="lightbox = visibleFiles.indexOf(menu.item); menu = null">View</button>
                            <button type="button" class="s-menu-item" @click="copyUrl(menu.item)">Copy URL</button>
                            <button type="button" class="s-menu-item" @click="duplicate(menu.item)">Duplicate</button>
                        </div>
                    </template>
                    <button type="button" class="s-menu-item" @click="startRename(menu.item)">Rename</button>
                    <div class="relative">
                        <button type="button" class="s-menu-item justify-between" @click="moveFor = moveFor ? null : menu.item">
                            <span>Move to…</span>
                            <svg class="h-3 w-3 text-faint" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="moveFor" x-cloak class="s-pop mt-0.5 max-h-40 overflow-y-auto">
                            <template x-for="folder in allFolders" :key="'to-' + folder">
                                <button type="button" class="s-menu-item" x-show="folder !== dir && folder !== menu.item.path" @click="move(menu.item, folder)" x-text="folder === '' ? 'Library (root)' : folder"></button>
                            </template>
                        </div>
                    </div>
                    <div class="s-divider my-1"></div>
                    <button type="button" class="s-menu-item !text-danger" @click="remove(menu.item)">Delete</button>
                </div>
            </template>
        </div>
    </div>

    {{-- Lightbox --}}
    <template x-teleport="body">
        <div x-show="lightbox !== null" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-8 backdrop-blur-sm" @click.self="lightbox = null">
            <template x-if="lightbox !== null && visibleFiles[lightbox]">
                <div class="flex max-h-full max-w-5xl flex-col items-center gap-3">
                    <img :src="visibleFiles[lightbox].url" :alt="visibleFiles[lightbox].name" class="max-h-[80vh] max-w-full rounded-xl object-contain shadow-2xl">
                    <div class="flex items-center gap-3 text-xs text-white/80">
                        <span x-text="visibleFiles[lightbox].name"></span>
                        <span class="text-white/50" x-text="(visibleFiles[lightbox].width ? visibleFiles[lightbox].width + '×' + visibleFiles[lightbox].height + ' · ' : '') + size(visibleFiles[lightbox].size)"></span>
                        <button type="button" class="s-btn-ghost !h-6 !px-2 !text-[11px] !text-white/80" @click="copyUrl(visibleFiles[lightbox])">Copy URL</button>
                    </div>
                </div>
            </template>
            <button type="button" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-show="lightbox > 0" @click="lightbox--" aria-label="Previous"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 5.5 8 12l6.5 6.5"/></svg></button>
            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-show="lightbox !== null && lightbox < visibleFiles.length - 1" @click="lightbox++" aria-label="Next"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 5.5 16 12l-6.5 6.5"/></svg></button>
            <button type="button" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="lightbox = null" aria-label="Close"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
        </div>
    </template>
</div>
