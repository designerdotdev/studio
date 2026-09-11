<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\SiteRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\DataBag;

/**
 * The variables every section can read without being handed them.
 *
 * The site's runtime shares `$site` and one variable per collection with
 * every page and component, so a section may write `@foreach ($posts as …)`
 * with nothing passed on its tag. Studio renders the same way, from the
 * draft documents instead of the files: `$site` is the site document's
 * data and each collection appears under the name of its file.
 */
class RenderContext
{
    /** @var array<string, array> workspace => globals */
    protected array $cache = [];

    public function __construct(
        protected SiteRepository $site,
        protected CollectionRepository $collections,
        protected StudioStorage $storage,
    ) {}

    /** @return array<string, mixed> variable name => value */
    public function globals(): array
    {
        $workspace = $this->storage->workspace();

        if (isset($this->cache[$workspace])) {
            return $this->cache[$workspace];
        }

        $globals = [];

        foreach ($this->collections->all() as $name => $doc) {
            $variable = $doc['source'] ?? null;

            if (is_string($variable) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $variable)) {
                $globals[$variable] = DataBag::wrap(self::rows($doc));
            }
        }

        // Last, so a collection named "site" can never displace it
        $globals['site'] = new DataBag($this->site->data());

        return $this->cache[$workspace] = $globals;
    }

    /** A collection's rows as the site reads them (Studio's own ids removed). */
    public static function rows(array $doc): array
    {
        $rows = array_values($doc['rows'] ?? []);

        if (!empty($doc['studio_ids'])) {
            $rows = array_map(function (array $row) {
                unset($row['id']);

                return $row;
            }, $rows);
        }

        return $rows;
    }

    /** Drop what was read so far (after an edit within the same request). */
    public function forget(): void
    {
        $this->cache = [];
    }
}
