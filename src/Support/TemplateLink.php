<?php

namespace Designer\Studio\Support;

use Illuminate\Support\Facades\File;

/**
 * A development link from the installed site back to the template folder
 * it was installed from.
 *
 * Installing copies a template's `files/` into resources/designer and
 * public/designer, and from then on the two drift apart. While a link is
 * set, every change Studio writes to the site (a publish, a Code mode save,
 * a media upload) is exported back into the template's `files/` at the end
 * of the request, so the template repo can be edited in Studio and committed
 * from its own folder. The link is honored only in the local environment.
 *
 * State lives in `<studio.storage_path>/link.json`:
 *
 *   { "template": "/abs/path/to/templates/starter",
 *     "exported": "<fingerprint of files/ after the last export>",
 *     "blocked":  "<why the last export refused>" }
 */
final class TemplateLink
{
    public const FILE = 'link.json';

    protected ?array $state = null;

    protected bool $pending = false;

    /** Links are a development tool; nothing outside local ever follows one. */
    public static function enabled(): bool
    {
        return app()->isLocal();
    }

    public function path(): string
    {
        return rtrim(config('studio.storage_path', storage_path('studio')), '/') . '/' . self::FILE;
    }

    /** The linked template folder, or null when there is none to honor. */
    public function directory(): ?string
    {
        if (!self::enabled()) {
            return null;
        }

        $dir = $this->state()['template'] ?? null;

        return is_string($dir) && self::looksLikeTemplate($dir) ? $dir : null;
    }

    public function slug(): ?string
    {
        return ($dir = $this->directory()) === null ? null : basename($dir);
    }

    public function linked(): bool
    {
        return $this->directory() !== null;
    }

    /** Point the link at a template folder and accept its current contents as the baseline. */
    public function link(string $dir, ?string $fingerprint): void
    {
        $this->save(['template' => rtrim($dir, '/'), 'exported' => $fingerprint, 'blocked' => null]);
    }

    public function unlink(): void
    {
        File::delete($this->path());
        $this->state = [];
    }

    /** Fingerprint of the template's files/ as the last export left them. */
    public function exported(): ?string
    {
        $value = $this->state()['exported'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Record a fresh baseline (after an export, or after re-importing the template). */
    public function baseline(?string $fingerprint): void
    {
        $this->save(['exported' => $fingerprint, 'blocked' => null] + $this->state());
    }

    /** Why the last export refused, shown in the editor until one succeeds. */
    public function blocked(): ?string
    {
        $value = $this->state()['blocked'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function block(?string $message): void
    {
        $this->save(['blocked' => $message] + $this->state());
    }

    /* ------------------------------------------------------------ */
    /*  Per-request: did anything write to the site?                 */
    /* ------------------------------------------------------------ */

    /** Called by every code path that writes site files; the provider exports at the end of the request. */
    public function touch(): void
    {
        $this->pending = true;
    }

    public function consumePending(): bool
    {
        $pending = $this->pending;
        $this->pending = false;

        return $pending;
    }

    /* ------------------------------------------------------------ */

    public static function looksLikeTemplate(string $dir): bool
    {
        return is_file($dir . '/template.json') && is_dir($dir . '/files/resources/views/pages');
    }

    protected function state(): array
    {
        if ($this->state === null) {
            $decoded = is_file($this->path()) ? json_decode((string) file_get_contents($this->path()), true) : null;
            $this->state = is_array($decoded) ? $decoded : [];
        }

        return $this->state;
    }

    protected function save(array $state): void
    {
        $state = array_intersect_key($state, array_flip(['template', 'exported', 'blocked']));

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->state = $state;
    }
}
