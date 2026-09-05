<?php

namespace Designer\Studio\Services\Assistant;

use Symfony\Component\Process\ExecutableFinder;

/**
 * The local AI CLIs the Assistant can drive. Each engine is a binary on
 * the developer's machine (Claude Code's `claude`, OpenAI's `codex`), so
 * availability is decided by looking for it — config may pin a path.
 */
class Engines
{
    public const CLAUDE = 'claude';

    public const CODEX = 'codex';

    protected ?array $cache = null;

    /**
     * @return array<string, array{label: string, bin: ?string, ok: bool, hint: string}>
     */
    public function available(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $claude = $this->locate(self::CLAUDE, ['~/.claude/local/claude', '~/.local/bin/claude', '/opt/homebrew/bin/claude', '/usr/local/bin/claude']);
        $codex = $this->locate(self::CODEX, ['~/.local/bin/codex', '/opt/homebrew/bin/codex', '/usr/local/bin/codex']);

        return $this->cache = [
            self::CLAUDE => [
                'label' => 'Claude Code',
                'bin' => $claude,
                'ok' => $claude !== null,
                'hint' => $claude ? $claude : 'Install Claude Code (`npm i -g @anthropic-ai/claude-code`) and run `claude` once to log in.',
            ],
            self::CODEX => [
                'label' => 'Codex',
                'bin' => $codex,
                'ok' => $codex !== null,
                'hint' => $codex ? $codex : 'Install Codex (`npm i -g @openai/codex`) and run `codex` once to log in.',
            ],
        ];
    }

    public function isAvailable(string $engine): bool
    {
        return (bool) ($this->available()[$engine]['ok'] ?? false);
    }

    /** The first engine that is installed, or null */
    public function default(): ?string
    {
        $preferred = (string) config('studio.assistant.default', self::CLAUDE);

        if ($this->isAvailable($preferred)) {
            return $preferred;
        }

        foreach ($this->available() as $name => $engine) {
            if ($engine['ok']) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The argv that runs one turn non-interactively and streams JSON lines.
     *
     * @param  ?string  $session  a previous turn's session/thread id to continue
     */
    public function command(string $engine, string $prompt, ?string $session, string $systemPrompt): array
    {
        $bin = $this->available()[$engine]['bin'] ?? null;

        if ($bin === null) {
            throw new \RuntimeException("The {$engine} CLI is not installed.");
        }

        if ($engine === self::CODEX) {
            // Codex reads the system guidance as part of the prompt; there is
            // no separate system-prompt flag in `exec`.
            $full = $systemPrompt . "\n\n---\n\n" . $prompt;

            $argv = $session
                ? [$bin, 'exec', 'resume', '--json', '--skip-git-repo-check', $session, $full]
                : [$bin, 'exec', '--json', '--skip-git-repo-check', '-C', base_path(), '--full-auto', $full];

            if ($model = config('studio.assistant.engines.codex.model')) {
                array_splice($argv, 2, 0, ['--model', $model]);
            }

            return $argv;
        }

        $argv = [
            $bin, '-p', $prompt,
            '--output-format', 'stream-json',
            '--verbose',
            '--include-partial-messages',
            '--permission-mode', 'acceptEdits',
            '--dangerously-skip-permissions',
            '--append-system-prompt', $systemPrompt,
        ];

        if ($session) {
            array_push($argv, '--resume', $session);
        }

        if ($model = config('studio.assistant.engines.claude.model')) {
            array_push($argv, '--model', $model);
        }

        return $argv;
    }

    protected function locate(string $engine, array $fallbacks): ?string
    {
        $configured = config("studio.assistant.engines.{$engine}.bin");

        if (is_string($configured) && $configured !== '') {
            $expanded = $this->expand($configured);

            return is_executable($expanded) ? $expanded : null;
        }

        $found = (new ExecutableFinder)->find($engine, null, [
            $this->expand('~/.local/bin'),
            $this->expand('~/.claude/local'),
            '/opt/homebrew/bin',
            '/usr/local/bin',
        ]);

        if ($found) {
            return $found;
        }

        foreach ($fallbacks as $path) {
            $expanded = $this->expand($path);

            if (is_executable($expanded)) {
                return $expanded;
            }
        }

        return null;
    }

    protected function expand(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '';

            return $home . substr($path, 1);
        }

        return $path;
    }
}
