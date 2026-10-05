<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\SiteRepository;
use Designer\Studio\Support\SiteUrls;
use Livewire\Component;

/**
 * The Pages panel: every page in the site, with rename / duplicate /
 * delete / set-home / reorder. Opening a page navigates the editor.
 */
class PagesPanel extends Component
{
    public string $pageSlug = '';

    /** The collection entry on the canvas (its address), when the editor is showing one */
    public ?string $entryPath = null;

    /** Slug currently being renamed inline (null = none) */
    public ?string $renaming = null;

    public string $renameTitle = '';

    public string $filter = '';

    public function boot(): void
    {
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    public function mount(string $pageSlug = '', ?string $entryPath = null): void
    {
        $this->pageSlug = $pageSlug;
        $this->entryPath = $entryPath;
    }

    protected function pages(): PageRepository
    {
        return app(PageRepository::class);
    }

    /**
     * Rows for the view: [slug, title, home, current, path, children, unfolded].
     * `children` are the collection entries served under the page
     * (blog/[posts.slug] under /blog); a filter that matches entries keeps
     * their page and shows only those.
     */
    public function getRowsProperty(): array
    {
        $home = SiteUrls::homeSlug();
        $needle = mb_strtolower(trim($this->filter));
        $groups = collect($this->dynamic())->whereNotNull('parent')->keyBy('parent');

        return $this->pages()->all()
            ->map(function ($p) use ($home, $needle, $groups) {
                $matches = $needle === '' || str_contains(mb_strtolower($p->title . ' ' . $p->slug), $needle);
                $children = $groups->has($p->slug) ? $this->children($groups[$p->slug], $matches ? '' : $needle) : [];

                return $matches || $children !== [] ? [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'home' => $p->slug === $home,
                    'current' => $p->slug === $this->pageSlug && $this->entryPath === null,
                    'path' => $p->slug === $home ? '/' : '/' . $p->slug,
                    'children' => $children,
                    'unfolded' => ! $matches || collect($children)->contains('current', true),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Collections whose pages sit in a folder no page is served at: listed
     * under the pages, by the collection's name. Same shape as a row's
     * `children`, with the same filter.
     */
    public function getGroupsProperty(): array
    {
        $needle = mb_strtolower(trim($this->filter));

        return collect($this->dynamic())
            ->whereNull('parent')
            ->map(function ($group) use ($needle) {
                $matches = $needle === '' || str_contains(mb_strtolower($group['label'] . ' ' . $group['path']), $needle);
                $children = $this->children($group, $matches ? '' : $needle);

                return $children !== [] ? [
                    'title' => $group['label'],
                    'path' => $group['path'],
                    'children' => $children,
                    'unfolded' => ! $matches || collect($children)->contains('current', true),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function dynamic(): array
    {
        return app(\Designer\Studio\Services\Site\DynamicPages::class)->groups();
    }

    /** A group's entries as rows: [title, path, tail, current] */
    protected function children(array $group, string $needle): array
    {
        return collect($group['entries'])
            ->filter(fn ($e) => $needle === '' || str_contains(mb_strtolower($e['title'] . ' ' . $e['path']), $needle))
            ->map(fn ($e) => [
                'title' => $e['title'],
                'path' => $e['path'],
                // The entry's own part of its address
                'tail' => $group['folder'] === '' ? $e['path'] : substr($e['path'], strlen($group['folder']) + 1),
                'current' => $e['path'] === $this->entryPath,
            ])
            ->values()
            ->all();
    }

    public function open(string $slug): void
    {
        $this->redirect(route('studio.index', ['page' => $slug]));
    }

    /** Put one of a collection's pages on the canvas */
    public function openEntry(string $path): void
    {
        $entry = app(\Designer\Studio\Services\Site\DynamicPages::class)->find($path);

        if ($entry) {
            $this->redirect(route('studio.index', [
                'page' => $entry['group']['parent'] ?? $this->pageSlug,
                'entry' => $entry['path'],
            ]));
        }
    }

    public function startRename(string $slug): void
    {
        $page = $this->pages()->find($slug);

        if (!$page) {
            return;
        }

        $this->renaming = $slug;
        $this->renameTitle = $page->title;
    }

    public function cancelRename(): void
    {
        $this->renaming = null;
        $this->renameTitle = '';
    }

    public function saveRename(): void
    {
        $slug = $this->renaming;
        $title = trim($this->renameTitle);

        $this->cancelRename();

        if (!$slug || $title === '') {
            return;
        }

        $page = $this->pages()->update($slug, ['title' => $title]);

        if (!$page) {
            return;
        }

        if ($slug === $this->pageSlug) {
            // Keep the topbar pill and the inspector's document version in step
            $this->dispatch('studio:page-meta-updated', title: $title, slug: $page->slug);
            $this->dispatch('studio:code-saved');
        }
    }

    public function duplicate(string $slug): void
    {
        $copy = $this->pages()->duplicate($slug);

        if ($copy) {
            $this->redirect(route('studio.index', ['page' => $copy->slug]));
        }
    }

    public function delete(string $slug): void
    {
        if ($this->pages()->all()->count() <= 1) {
            $this->dispatch('studio:toast', message: 'A site needs at least one page.', type: 'error');

            return;
        }

        $this->pages()->delete($slug);

        if ($slug === $this->pageSlug) {
            $this->redirect(route('studio.index'));

            return;
        }

        $this->dispatch('studio:toast', message: 'Page deleted', type: 'success');
    }

    public function setHome(string $slug): void
    {
        if (!$this->pages()->find($slug)) {
            return;
        }

        app(SiteRepository::class)->save(['home_slug' => $slug]);

        $this->dispatch('studio:toast', message: 'Home page updated — publish to make it live.', type: 'success');
        $this->dispatch('studio:refresh-preview');
    }

    public function reorder(array $slugs): void
    {
        $this->pages()->reorder(array_values(array_filter($slugs, 'is_string')));
    }

    public function render()
    {
        return view('studio::livewire.pages-panel');
    }
}
