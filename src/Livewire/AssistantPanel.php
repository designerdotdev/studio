<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Assistant\Engines;
use Designer\Studio\Services\Assistant\Threads;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Assistant panel: threads + composer. Streaming a turn happens in the
 * browser against AssistantController's SSE endpoint; this component owns
 * the thread list, the active thread's messages, the engine choice, and
 * the selection context shown as a chip in the composer.
 */
class AssistantPanel extends Component
{
    public string $pageSlug = '';

    public ?string $threadId = null;

    public ?string $engine = null;

    /** The selected canvas section: {id, ref, title, scope} */
    public ?array $selected = null;

    public bool $historyOpen = false;

    public function boot(): void
    {
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    public function mount(string $pageSlug = ''): void
    {
        $this->pageSlug = $pageSlug;
        $this->engine = app(Engines::class)->default();

        $latest = app(Threads::class)->all()[0] ?? null;
        $this->threadId = $latest['id'] ?? null;

        if ($latest && $latest['engine'] && app(Engines::class)->isAvailable($latest['engine'])) {
            $this->engine = $latest['engine'];
        }
    }

    protected function threads(): Threads
    {
        return app(Threads::class);
    }

    /* ------------------------------------------------------------ */
    /*  Reads                                                        */
    /* ------------------------------------------------------------ */

    public function getEnginesProperty(): array
    {
        return app(Engines::class)->available();
    }

    public function getThreadsProperty(): array
    {
        return $this->threads()->all();
    }

    public function getThreadProperty(): ?array
    {
        return $this->threadId ? $this->threads()->find($this->threadId) : null;
    }

    /** Three canned prompts for an empty thread, built from the page */
    public function getSuggestionsProperty(): array
    {
        $page = $this->pageSlug ? app(PageRepository::class)->find($this->pageSlug) : null;
        $refs = collect($page?->components ?? [])->pluck('component_ref')->filter()->values();

        $has = fn (string $needle) => $refs->contains(fn ($ref) => str_contains((string) $ref, $needle));

        $suggestions = [];

        if ($has('hero')) {
            $suggestions[] = 'Tighten the hero headline and make the subheading one sentence shorter.';
        }

        $suggestions[] = $has('faq')
            ? 'Add two more questions to the FAQ that a first-time buyer would ask.'
            : 'Add an FAQ section near the bottom of this page with four questions.';

        $suggestions[] = $has('nav') || $has('header')
            ? 'Make the navigation links a little larger and increase their spacing.'
            : 'Give every button on this page slightly rounder corners.';

        return array_slice($suggestions, 0, 3);
    }

    /* ------------------------------------------------------------ */
    /*  Threads                                                      */
    /* ------------------------------------------------------------ */

    /** The id the composer should send to — creating a thread when needed */
    public function ensureThread(): string
    {
        if ($this->threadId && $this->threads()->find($this->threadId)) {
            return $this->threadId;
        }

        $thread = $this->threads()->create($this->engine ?: (app(Engines::class)->default() ?? 'claude'));
        $this->threadId = $thread['id'];

        return $thread['id'];
    }

    public function newThread(): void
    {
        $this->threadId = $this->threads()->create($this->engine ?: 'claude')['id'];
        $this->historyOpen = false;
    }

    public function open(string $id): void
    {
        if ($thread = $this->threads()->find($id)) {
            $this->threadId = $id;

            if ($thread['engine'] && app(Engines::class)->isAvailable($thread['engine'])) {
                $this->engine = $thread['engine'];
            }
        }

        $this->historyOpen = false;
    }

    public function rename(string $id, string $title): void
    {
        $this->threads()->rename($id, $title);
    }

    public function delete(string $id): void
    {
        $this->threads()->delete($id);

        if ($this->threadId === $id) {
            $this->threadId = $this->threads()->all()[0]['id'] ?? null;
        }
    }

    public function setEngine(string $engine): void
    {
        if (!app(Engines::class)->isAvailable($engine)) {
            return;
        }

        $this->engine = $engine;

        if ($this->threadId) {
            $this->threads()->setEngine($this->threadId, $engine);
        }
    }

    /* ------------------------------------------------------------ */
    /*  Selection context                                            */
    /* ------------------------------------------------------------ */

    #[On('studio:select-section')]
    public function noteSelection(string $id): void
    {
        $this->selected = $this->describe($id);
    }

    #[On('studio:deselect-section')]
    public function clearSelection(): void
    {
        $this->selected = null;
    }

    protected function describe(string $id): ?array
    {
        $page = $this->pageSlug ? app(PageRepository::class)->find($this->pageSlug) : null;
        $components = app(ComponentRepository::class);

        foreach ($page?->components ?? [] as $instance) {
            if (($instance['id'] ?? null) === $id) {
                $component = !empty($instance['component_ref']) ? $components->find($instance['component_ref']) : null;

                return ['id' => $id, 'ref' => $instance['component_ref'] ?? null, 'title' => $component?->title ?? 'Section', 'scope' => 'page'];
            }
        }

        if ($page?->layout_ref && ($layout = app(LayoutRepository::class)->find($page->layout_ref))) {
            foreach ($layout['components'] ?? [] as $instance) {
                if (($instance['id'] ?? null) === $id) {
                    $component = !empty($instance['component_ref']) ? $components->find($instance['component_ref']) : null;

                    return ['id' => $id, 'ref' => $instance['component_ref'] ?? null, 'title' => $component?->title ?? 'Section', 'scope' => 'layout'];
                }
            }
        }

        return null;
    }

    public function render()
    {
        return view('studio::livewire.assistant-panel');
    }
}
