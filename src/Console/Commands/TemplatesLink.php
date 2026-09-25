<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Site\SiteInstaller;
use Designer\Studio\Services\Site\SiteManifest;
use Designer\Studio\Services\Templates\TemplateCatalog;
use Designer\Studio\Services\Templates\TemplateExporter;
use Designer\Studio\Services\Templates\TemplatePreview;
use Designer\Studio\Services\Templates\TemplateSync;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\TemplateLink;
use Illuminate\Console\Command;

class TemplatesLink extends Command
{
    protected $signature = 'studio:templates:link
        {template? : A template slug in the local template folder (STUDIO_TEMPLATE_PREVIEW_PATH), or the absolute path of a template folder}
        {--install : Replace the installed site with this template before linking (deletes the site)}
        {--export : Overwrite the template folder with the installed site before linking}
        {--unlink : Remove the link}';

    protected $description = 'Link the installed site to a template folder, so what Studio writes is exported back into it (local only)';

    public function handle(TemplateLink $link, TemplateCatalog $catalog, TemplateSync $sync, SiteInstaller $installer, TemplateExporter $exporter): int
    {
        if ($this->option('unlink')) {
            return $this->unlink($link);
        }

        if (($argument = $this->argument('template')) === null) {
            return $this->status($link);
        }

        if (!TemplateLink::enabled()) {
            $this->error('Template links only work in the local environment (APP_ENV=local).');

            return self::FAILURE;
        }

        if (($dir = $this->resolve($argument, $catalog)) === null) {
            return self::FAILURE;
        }

        if ($problem = $sync->validate($dir)) {
            $this->error("[{$dir}] is not a template: {$problem}.");

            return self::FAILURE;
        }

        $slug = basename($dir);

        if (!$this->prepareSite($slug, $dir, $installer)) {
            return self::FAILURE;
        }

        $link->link($dir, null);

        // The export mirrors the whole site over files/, so the two have to
        // agree before the link is trusted: a site installed from somewhere
        // else (an older clone, say) would overwrite the folder on the first
        // publish. --export says that is wanted; --install went the other way.
        try {
            $report = $exporter->export(force: true, dryRun: !$this->option('export'));
        } catch (\Throwable $e) {
            $link->unlink();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $differs = $report['written'] > 0 || $report['deleted'] > 0 || $report['pages'];

        if ($differs && !$this->option('export')) {
            $link->unlink();
            $this->error(sprintf('The installed site differs from %s/files (%d file(s) would change, %d would be removed%s), so it was not linked.', $slug, $report['written'], $report['deleted'], $report['pages'] ? ', template.json pages too' : ''));
            $this->line('  --install replaces the installed site with the template folder (the usual choice).');
            $this->line('  --export overwrites the template folder with the installed site.');

            return self::FAILURE;
        }

        $link->baseline($exporter->fingerprint($dir));

        $this->info("Linked the site to {$dir}.");
        $this->newLine();
        $this->line(!$differs
            ? '  files/ matches the installed site.'
            : sprintf('  Exported now: %d file(s) written, %d removed%s.', $report['written'], $report['deleted'], $report['pages'] ? ', template.json pages updated' : ''));
        $this->line('  From here on, a publish, Code mode save or media change in Studio is written into ' . $slug . '/files at the end of the request.');
        $this->line('  Commit from the template folder as usual. studio:templates:export pushes by hand; --unlink stops it.');

        return self::SUCCESS;
    }

    protected function status(TemplateLink $link): int
    {
        if (!TemplateLink::enabled()) {
            $this->line('Template links only work in the local environment; none is honored here.');

            return self::SUCCESS;
        }

        if (($dir = $link->directory()) === null) {
            $this->line('No template is linked.');
            $this->line('  php artisan studio:templates:link <slug|path>');

            return self::SUCCESS;
        }

        $this->line("Linked to {$dir}");

        if ($blocked = $link->blocked()) {
            $this->warn('  Last export skipped: ' . $blocked);
        } else {
            $this->line('  Up to date with the last export.');
        }

        return self::SUCCESS;
    }

    protected function unlink(TemplateLink $link): int
    {
        $link->unlink();
        $this->info('Unlinked. Studio writes to ' . SitePaths::relative(SitePaths::resources()) . ' only.');

        return self::SUCCESS;
    }

    /** An absolute path is used as it is; a slug is looked up in the local template folder. */
    protected function resolve(string $argument, TemplateCatalog $catalog): ?string
    {
        if (str_contains($argument, '/')) {
            $dir = realpath($argument);

            if ($dir === false || !is_dir($dir)) {
                $this->error("[{$argument}] is not a folder.");

                return null;
            }

            return $dir;
        }

        if (!$catalog->local()) {
            $this->error("[{$argument}] can't be looked up: set STUDIO_TEMPLATE_PREVIEW_PATH to the folder holding the templates, or pass the template's absolute path.");

            return null;
        }

        $dir = TemplatePreview::make()->directory($argument);

        if ($dir === null) {
            $this->error("Template [{$argument}] is not in the local template folder.");

            return null;
        }

        return $dir;
    }

    /**
     * The site the link exports has to be this template: install it when the
     * app has no site yet, replace on --install, refuse anything else.
     */
    protected function prepareSite(string $slug, string $dir, SiteInstaller $installer): bool
    {
        $installed = SitePaths::installed() ? (SiteManifest::read()['template'] ?? null) : null;

        if (SitePaths::installed() && !$this->option('install')) {
            if ($installed !== null && $installed !== $slug) {
                $this->error("The installed site is [{$installed}], not [{$slug}]. Pass --install to replace it with {$slug} (deletes the site).");

                return false;
            }

            return true;
        }

        if (SitePaths::installed() && $this->input->isInteractive() && !$this->option('no-interaction')
            && !$this->confirm("Installing {$slug} deletes the site in resources/designer and public/designer, including any edits. Continue?", false)) {
            return false;
        }

        try {
            $report = $installer->installFrom($slug, $dir, replace: SitePaths::installed());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }

        $this->info("Installed {$slug} from {$dir}.");
        $this->line(sprintf('  %d files, %d sections, %d pages.', $report['files'], $report['sections'], count($report['pages'])));

        if (!$report['runtime']['registered']) {
            $this->warn('  Register App\\Providers\\DesignerServiceProvider in bootstrap/providers.php so the app serves the site.');
        }

        return true;
    }
}
