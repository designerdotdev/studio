<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Site\SiteInstaller;
use Designer\Studio\Services\Templates\TemplateSync;
use Designer\Studio\Support\SitePaths;
use Illuminate\Console\Command;

class TemplatesImport extends Command
{
    protected $signature = 'studio:templates:import
        {template : Slug of a template in studio.templates.catalog}
        {--force : Replace the site already installed in resources/designer}';

    protected $description = 'Install a site template into resources/designer and public/designer';

    public function handle(TemplateSync $sync, SiteInstaller $installer): int
    {
        $slug = $this->argument('template');

        if (!isset($sync->catalog()[$slug])) {
            $this->error("Template [{$slug}] is not in studio.templates.catalog.");
            $this->line('  Available: ' . (implode(', ', array_keys($sync->catalog())) ?: 'none'));

            return self::FAILURE;
        }

        $replace = (bool) $this->option('force');

        if (SitePaths::installed() && !$replace) {
            $this->error('A site is already installed in ' . SitePaths::relative(SitePaths::resources()) . '.');
            $this->line('  Pass --force to replace it (its pages, sections, data, and public files are deleted).');

            return self::FAILURE;
        }

        if ($replace && SitePaths::installed() && !$this->confirmReplacement($slug)) {
            return self::SUCCESS;
        }

        try {
            $report = $installer->install($slug, $replace);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Installed {$slug}.");
        $this->newLine();
        $this->line(sprintf('  %d files into %s and %s.', $report['files'], SitePaths::relative(SitePaths::resources()), SitePaths::relative(SitePaths::public())));
        $this->line(sprintf('  %d sections, %d pages the editor manages: %s', $report['sections'], count($report['pages']), implode(', ', array_map(fn ($p) => '/' . $p, $report['pages']))));

        if ($report['code_pages'] !== []) {
            $this->line('  Hand-written pages (edit in Code mode): ' . implode(', ', $report['code_pages']));
        }

        if ($report['runtime']['written']) {
            $this->line('  Added app/Providers/DesignerServiceProvider.php — it serves the site, with or without Studio.');
        }

        if (!$report['runtime']['registered']) {
            $this->warn('  Register App\\Providers\\DesignerServiceProvider in bootstrap/providers.php so the app serves the site.');
        }

        $this->newLine();
        $this->line('Open ' . url(config('studio.path', 'studio')) . ' to edit it.');

        return self::SUCCESS;
    }

    /**
     * Installing over a site deletes it, so make the cost of that explicit
     * unless the caller has already opted out of prompts.
     */
    protected function confirmReplacement(string $slug): bool
    {
        if (!$this->input->isInteractive() || $this->option('no-interaction')) {
            return true;
        }

        return $this->confirm(
            "Installing {$slug} deletes the site in resources/designer and public/designer, including any edits. Continue?",
            false
        );
    }
}
