<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Support\StudioAssets;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PublishAssets extends Command
{
    protected $signature = 'studio:publish
        {--remove : Remove the published assets and serve from the package again}';

    protected $description = 'Publish the compiled Studio assets to public/vendor/studio';

    public function handle(Filesystem $files): int
    {
        $target = public_path(StudioAssets::PUBLISH_PATH);

        if ($this->option('remove')) {
            if (! $files->isDirectory($target)) {
                $this->info('No published assets found — already serving from the package.');

                return self::SUCCESS;
            }

            $files->deleteDirectory($target);
            $this->info('Published assets removed. Assets are served from the package again.');

            return self::SUCCESS;
        }

        $dist = dirname(__DIR__, 3) . '/dist';

        if (! is_dir($dist)) {
            $this->error("No compiled assets found at {$dist}. Run `npm run build` in the package first.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($target);

        foreach (StudioAssets::FILES as $file) {
            if (! file_exists($dist . '/' . $file)) {
                $this->error("Missing {$file} in the package dist/. Run `npm run build` in the package first.");

                return self::FAILURE;
            }

            $files->copy($dist . '/' . $file, $target . '/' . $file);
            $this->line("  <info>✓</info> {$file} → " . str_replace(base_path() . '/', '', $target) . "/{$file}");
        }

        $this->newLine();
        $this->info('Studio assets published. They now take precedence over the package copies —');
        $this->info('re-run this command (or run `npm run dev` in the package) after rebuilding, or use --remove to revert.');

        return self::SUCCESS;
    }
}
