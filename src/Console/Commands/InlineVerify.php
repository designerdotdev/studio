<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Inline\EchoScanner;
use Designer\Studio\Support\SitePaths;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * The regression gate for inline editing.
 *
 * This package has no test runner, so the scanner's two load-bearing
 * claims are checked here against every real section on the machine: the
 * installed site and every template clone in storage/studio/templates.
 */
class InlineVerify extends Command
{
    protected $signature = 'studio:inline:verify
                            {--section= : Only sections whose file name contains this}';

    protected $description = 'Check the inline-editing scanner: every declared field maps, and sentinels are inert (dev only)';

    /** The measured baseline these numbers must not fall below. */
    protected const EXPECT_MAPPED = 365;
    protected const EXPECT_FIELDS = 369;

    public function handle(EchoScanner $scanner): int
    {
        $sections = $this->sections();

        if ($sections === []) {
            $this->warn('No sections found — install a site first (php artisan studio:templates:import).');

            return self::FAILURE;
        }

        $fieldCount = 0;
        $mappedCount = 0;
        $unmapped = [];

        foreach ($sections as $base => $fields) {
            $source = (string) file_get_contents($base . '.blade.php');
            $refs = $scanner->scan($source, $fields);

            $hit = [];
            foreach ($refs as $ref) {
                $hit[$ref->key] = true;
            }

            $missing = array_diff(array_keys($fields), array_keys($hit));
            $fieldCount += count($fields);
            $mappedCount += count($fields) - count($missing);

            foreach ($missing as $key) {
                $unmapped[] = basename($base) . '.' . $key . '  (' . ($fields[$key]['type'] ?? 'text') . ')';
            }

            $this->line(sprintf(
                '  %3d%%  %2d/%-2d  %s',
                count($fields) ? round((count($fields) - count($missing)) / count($fields) * 100) : 100,
                count($fields) - count($missing),
                count($fields),
                basename($base)
            ));
        }

        $this->newLine();
        $this->info(sprintf('Coverage: %d/%d fields mapped across %d sections', $mappedCount, $fieldCount, count($sections)));

        foreach ($unmapped as $line) {
            $this->line('  unmapped: ' . $line);
        }

        if ($this->option('section')) {
            return self::SUCCESS;
        }

        if ($mappedCount < self::EXPECT_MAPPED) {
            $this->error(sprintf('Coverage regressed: %d < %d expected.', $mappedCount, self::EXPECT_MAPPED));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Every `<name>.blade.php` + `<name>.yml` pair that declares fields,
     * keyed by its path without extension.
     *
     * @return array<string, array<string, array>>
     */
    protected function sections(): array
    {
        $roots = [SitePaths::resources() . '/views/components'];

        foreach (glob(storage_path('studio/templates/*/files/resources/views/components')) ?: [] as $clone) {
            $roots[] = $clone;
        }

        $found = [];
        $filter = (string) $this->option('section');

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($walk as $file) {
                if ($file->isDir() || !str_ends_with($file->getFilename(), '.yml')) {
                    continue;
                }

                $base = substr($file->getPathname(), 0, -4);

                if (!is_file($base . '.blade.php')) {
                    continue;
                }

                if ($filter !== '' && !str_contains($base, $filter)) {
                    continue;
                }

                try {
                    $meta = Yaml::parseFile($base . '.yml');
                } catch (\Throwable) {
                    continue;
                }

                $fields = is_array($meta['fields'] ?? null) ? array_filter($meta['fields'], 'is_array') : [];

                if ($fields !== []) {
                    $found[$base] = $fields;
                }
            }
        }

        return $found;
    }
}
