<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Templates\TemplateImporter;
use Designer\Studio\Services\Templates\TemplateSync;
use Illuminate\Console\Command;

class TemplatesImport extends Command
{
    protected $signature = 'studio:templates:import
        {template : Slug of a synced template}
        {--keep : Import alongside the current site instead of replacing it}';

    protected $description = 'Build a Studio site from a synced template repository';

    public function handle(TemplateSync $sync, TemplateImporter $importer): int
    {
        $slug = $this->argument('template');

        if (!$sync->directory($slug)) {
            $this->error("Template [{$slug}] is not synced.");
            $this->line('  Run: php artisan studio:templates:sync --template=' . $slug);

            return self::FAILURE;
        }

        $fresh = !$this->option('keep');

        if ($fresh && !$this->confirmReplacement($slug)) {
            return self::SUCCESS;
        }

        try {
            $report = $importer->import($slug, fresh: $fresh);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$slug}.");
        $this->newLine();

        $this->line(sprintf('  %d sections, %d pages, %d asset folders.',
            $report['sections'],
            count($report['pages']),
            $report['assets'],
        ));

        if ($report['layout']) {
            $this->line("  Shared layout: {$report['layout']}");
        }

        if ($report['pages'] !== []) {
            $this->line('  Pages: ' . implode(', ', array_map(fn ($p) => '/' . $p, $report['pages'])));
        }

        foreach ($report['notes'] as $note) {
            $this->line("  · {$note}");
        }

        foreach ($report['skipped'] as $page => $reason) {
            $this->warn("  • {$page} skipped — {$reason}");
        }

        $this->newLine();
        $this->line('Open ' . url(config('studio.path', 'studio')) . ' to edit it.');

        return self::SUCCESS;
    }

    /**
     * Importing replaces the whole site, so make the cost of that explicit
     * unless the caller has already opted out of prompts.
     */
    protected function confirmReplacement(string $slug): bool
    {
        if (!$this->input->isInteractive() || $this->option('no-interaction')) {
            return true;
        }

        return $this->confirm(
            "Importing {$slug} replaces every existing page, layout, and block. Continue?",
            true
        );
    }
}
