<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Templates\TemplateSync;
use Illuminate\Console\Command;
use RuntimeException;

class TemplatesSync extends Command
{
    protected $signature = 'studio:templates:sync
        {--template= : Sync a single template by slug}
        {--force : Discard uncommitted or unpushed work in a clone}
        {--no-prune : Keep folders that have left the catalog}
        {--list : Show what is catalogued and what is on disk, without touching git}';

    protected $description = 'Clone or update the site templates listed in studio.templates.catalog';

    public function handle(TemplateSync $sync): int
    {
        if ($this->option('list')) {
            return $this->list($sync);
        }

        if ($sync->catalog() === []) {
            $this->warn('studio.templates.catalog is empty — nothing to sync.');
            $this->line('  Add entries as slug => git URL, for example:');
            $this->line("      'monarch' => 'https://github.com/site-templates/monarch',");

            return self::SUCCESS;
        }

        try {
            $result = $sync->sync(
                only: $this->option('template') ?: null,
                force: (bool) $this->option('force'),
                prune: !$this->option('no-prune'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['updated'] as $slug) {
            $this->info("  ✓ {$slug}");
        }

        foreach ($result['skipped'] as $slug => $reason) {
            $this->warn("  • {$slug} skipped — {$reason} (--force to discard)");
        }

        foreach ($result['pruned'] as $slug) {
            $this->warn("  − pruned {$slug} (gone from the catalog)");
        }

        foreach ($result['failed'] as $slug => $problem) {
            $this->error("  ✗ {$slug} — {$problem}");
        }

        $this->newLine();
        $this->line(sprintf(
            'Synced %d template(s) into %s; %d failed, %d skipped.',
            count($result['updated']),
            $sync->path(),
            count($result['failed']),
            count($result['skipped']),
        ));

        if ($result['updated'] !== []) {
            $this->line('Install one with: php artisan studio:templates:import ' . $result['updated'][0]);
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }

    protected function list(TemplateSync $sync): int
    {
        $catalog = $sync->catalog();
        $synced = $sync->synced();
        $rows = [];

        foreach ($catalog as $slug => $url) {
            $rows[$slug] = [$slug, isset($synced[$slug]) ? 'synced' : 'not synced', $url];
        }

        foreach ($synced as $slug => $dir) {
            if (!isset($rows[$slug])) {
                $rows[$slug] = [$slug, 'on disk, not catalogued', $dir];
            }
        }

        if ($rows === []) {
            $this->warn('No templates catalogued or synced.');

            return self::SUCCESS;
        }

        ksort($rows);
        $this->table(['Template', 'Status', 'Source'], array_values($rows));

        return self::SUCCESS;
    }
}
