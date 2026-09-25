<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Templates\TemplateExporter;
use Designer\Studio\Support\TemplateLink;
use Illuminate\Console\Command;

class TemplatesExport extends Command
{
    protected $signature = 'studio:templates:export
        {--force : Overwrite changes made to the template folder outside Studio since the last export}';

    protected $description = 'Write the installed site back into the linked template folder (see studio:templates:link)';

    public function handle(TemplateLink $link, TemplateExporter $exporter): int
    {
        if (($dir = $link->directory()) === null) {
            $this->error('No template is linked. Run studio:templates:link <slug|path> first.');

            return self::FAILURE;
        }

        try {
            $report = $exporter->export(force: (bool) $this->option('force'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Exported to {$dir}/files.");
        $this->line($report['written'] === 0 && $report['deleted'] === 0 && !$report['pages']
            ? '  Nothing had changed.'
            : sprintf('  %d file(s) written, %d removed%s.', $report['written'], $report['deleted'], $report['pages'] ? ', template.json pages updated' : ''));

        return self::SUCCESS;
    }
}
