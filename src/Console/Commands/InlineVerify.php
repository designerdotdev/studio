<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Inline\EchoScanner;
use Designer\Studio\Services\Inline\Instrumenter;
use Designer\Studio\Support\DataBag;
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

    public function handle(EchoScanner $scanner, Instrumenter $instrumenter): int
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

        $this->newLine();

        if (!$this->inertness($sections, $instrumenter)) {
            return self::FAILURE;
        }

        $this->newLine();

        if (!$this->leakCheck()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Sentinels belong to the canvas and nowhere else. A leak into the draft
     * preview would mean one is a keystroke away from a published page file.
     */
    protected function leakCheck(): bool
    {
        $renderer = app(\Designer\Studio\Services\SectionRenderer::class);
        $components = app(\Designer\Studio\Services\Storage\ComponentRepository::class);
        $component = collect($components->all())->first(fn ($c) => $c->fields !== []);

        if (!$component) {
            $this->warn('Leak check skipped: no section with fields in the library.');

            return true;
        }

        $variables = $component->resolveVariables();
        $plain = $renderer->render($component, $variables);
        $canvas = $renderer->render($component, $variables, [], instrument: true);

        $ok = true;

        if (str_contains($plain, '<!--sf:') || str_contains($plain, 'data-sf-')) {
            $this->error('LEAK: the default (uninstrumented) render contains sentinels.');
            $ok = false;
        }

        if (!str_contains($canvas, '<!--sf:')) {
            $this->error('The canvas render contains no sentinels — instrumentation is not reaching it.');
            $ok = false;
        }

        if ($ok) {
            $this->info('Leak check: sentinels appear only when instrumentation is asked for.');
        }

        return $ok;
    }

    /**
     * The invariant: stripping the sentinels out of an instrumented render
     * must reproduce the plain render byte for byte. If this ever fails, a
     * sentinel is changing the page rather than just describing it.
     *
     * @param array<string, array<string, array>> $sections
     */
    protected function inertness(array $sections, Instrumenter $instrumenter): bool
    {
        $identical = 0;
        $diverged = [];
        $unrenderable = [];
        $sentinels = 0;

        foreach ($sections as $base => $fields) {
            $source = (string) file_get_contents($base . '.blade.php');

            // The defaults a section sees when nothing has been edited, the
            // way ComponentData::resolveVariables resolves them.
            $variables = [];
            foreach ($fields as $key => $config) {
                if (($config['type'] ?? 'text') === 'repeater') {
                    // Seed repeater with synthetic rows to exercise {{ $loop->index }}
                    // sentinels; build from sub_fields, using defaults where available.
                    $subFields = $config['sub_fields'] ?? [];

                    if ($subFields !== []) {
                        $rows = [];

                        // Create two synthetic rows to exercise loop indices.
                        for ($i = 0; $i < 2; $i++) {
                            $row = [];

                            foreach ($subFields as $subKey => $subConfig) {
                                $row[$subKey] = $subConfig['default'] ?? 'x';
                            }

                            // Include children for nestable repeaters.
                            $row['children'] = [];

                            // Wrap with DataBag to support both array and object access,
                            // matching SectionRenderer::context() behavior exactly.
                            $rows[] = DataBag::wrap($row);
                        }

                        $variables[$key] = $rows;
                    } else {
                        $variables[$key] = [];
                    }
                } else {
                    $variables[$key] = $config['default'] ?? '';
                }
            }

            $plain = $this->render($source, $variables);

            if ($plain === null) {
                // Layout files need $site/$slot globals a bare render has no
                // way to supply; they are not canvas sections.
                $unrenderable[] = basename($base);

                continue;
            }

            $marked = $this->render($instrumenter->weave($source, $fields), $variables);

            if ($marked === null) {
                $diverged[] = basename($base) . ' (instrumented render threw)';

                continue;
            }

            $sentinels += substr_count($marked, '<!--sf:');

            $stripped = $instrumenter->strip($marked);

            if ($stripped === $plain) {
                $identical++;

                continue;
            }

            $offset = 0;
            $limit = min(strlen($plain), strlen($stripped));

            while ($offset < $limit && $plain[$offset] === $stripped[$offset]) {
                $offset++;
            }

            $diverged[] = sprintf(
                "%s diverges at byte %d\n      plain: %s\n      strip: %s",
                basename($base),
                $offset,
                json_encode(substr($plain, max(0, $offset - 40), 90)),
                json_encode(substr($stripped, max(0, $offset - 40), 90))
            );
        }

        $this->info(sprintf(
            'Inertness: %d identical, %d diverged, %d unrenderable, %d sentinels rendered',
            $identical,
            count($diverged),
            count($unrenderable),
            $sentinels
        ));

        if ($unrenderable !== []) {
            $this->line('  unrenderable: ' . implode(', ', $unrenderable));
        }

        foreach ($diverged as $line) {
            $this->error('  ' . $line);
        }

        return $diverged === [];
    }

    protected function render(string $source, array $variables): ?string
    {
        try {
            return \Designer\Studio\Support\NestedBlade::render($source, $variables);
        } catch (\Throwable) {
            return null;
        }
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
