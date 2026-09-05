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

    public function mount(string $pageSlug = ''): void
    {
        $this->pageSlug = $pageSlug;
    }

    protected function pages(): PageRepository
    {
        return app(PageRepository::class);
    }

    /** Rows for the view: [slug, title, home, current, url] */
    public function getRowsProperty(): array
    {
        $home = SiteUrls::homeSlug();
        $needle = mb_strtolower(trim($this->filter));

        return $this->pages()->all()
            ->filter(fn ($p) => $needle === '' || str_contains(mb_strtolower($p->title . ' ' . $p->slug), $needle))
            ->map(fn ($p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'home' => $p->slug === $home,
                'current' => $p->slug === $this->pageSlug,
                'path' => $p->slug === $home ? '/' : '/' . $p->slug,
            ])
            ->values()
            ->all();
    }

    public function open(string $slug): void
    {
        $this->redirect(route('studio.index', ['page' => $slug]));
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
