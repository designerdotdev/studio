{{-- /template — every template in studio.template_preview.path, previewed without installing --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template previews</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
    <style>
        :root {
            color-scheme: dark;
            --shell: #0b0b0d; --panel: #121215; --raised: #1a1a1f;
            --line: rgba(255, 255, 255, .10); --line-strong: rgba(255, 255, 255, .18);
            --ink: #f4f4f6; --soft: #9d9da7; --faint: #70707a;
            --accent: #4c7dfa; --ok: #34d399; --warn: #fbbf24; --danger: #f37272;
        }
        @media (prefers-color-scheme: light) {
            :root {
                color-scheme: light;
                --shell: #f4f4f5; --panel: #ffffff; --raised: #eceef1;
                --line: rgba(17, 17, 20, .08); --line-strong: rgba(17, 17, 20, .16);
                --ink: #141417; --soft: #5f6069; --faint: #85858f;
                --accent: #3b6ff0; --ok: #0b8a5f; --warn: #9a6207; --danger: #c63d3d;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--shell); color: var(--ink); font: 13px/1.5 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        code { font-family: 'Geist Mono', ui-monospace, 'SF Mono', Menlo, monospace; font-size: 11.5px; }

        .bar { position: sticky; top: 0; z-index: 2; display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; padding: 14px 16px; background: color-mix(in srgb, var(--shell) 90%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .bar h1 { margin: 0; font-size: 13px; font-weight: 600; }
        .bar span { color: var(--soft); font-variant-numeric: tabular-nums; }
        .bar code { margin-left: auto; color: var(--faint); overflow-wrap: anywhere; }

        main { max-width: 1680px; margin: 0 auto; padding: 16px; display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        .card { display: grid; grid-template-rows: auto 1fr; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; transition: border-color .15s ease; }
        .card:hover { border-color: var(--line-strong); }
        .shot { aspect-ratio: 16 / 10; background: var(--raised); border-bottom: 1px solid var(--line); overflow: hidden; }
        .shot img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: top; }
        .shot.empty { display: grid; place-items: center; color: var(--faint); font-size: 12px; }
        .meta { display: grid; gap: 4px; padding: 11px 13px 13px; align-content: start; }
        .row { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .name { font-size: 14px; font-weight: 600; letter-spacing: -.01em; }
        .dot { width: 9px; height: 9px; border-radius: 50%; box-shadow: inset 0 0 0 1px var(--line-strong); flex: none; }
        .kind { color: var(--faint); }
        .status { margin-left: auto; font-size: 11px; padding: 1px 8px; border-radius: 999px; border: 1px solid currentColor; opacity: .9; }
        .status.review { color: var(--accent); }
        .status.revise, .status.building { color: var(--warn); }
        .status.killed { color: var(--danger); }
        .status.shipped { color: var(--ok); }
        .status.catalog { color: var(--faint); }
        .description { margin: 2px 0 0; color: var(--soft); font-size: 12.5px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .none { grid-column: 1 / -1; color: var(--soft); padding: 40px 0; text-align: center; }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Template previews</h1>
        <span>{{ count($templates) }} {{ \Illuminate\Support\Str::plural('template', count($templates)) }}</span>
        <code>{{ $root }}</code>
    </header>

    <main>
        @forelse ($templates as $template)
            <a class="card" href="{{ url($prefix . '/' . $template['slug']) }}">
                @if ($template['thumbnail'])
                    <div class="shot"><img src="{{ url($prefix . '/' . $template['slug'] . '/_thumbnail') }}" alt="{{ $template['name'] }} home page" loading="lazy"></div>
                @else
                    <div class="shot empty">No thumbnail yet</div>
                @endif
                <div class="meta">
                    <div class="row">
                        @if ($template['accent'])
                            <i class="dot" style="background: {{ $template['accent'] }}"></i>
                        @endif
                        <span class="name">{{ $template['name'] }}</span>
                        @if ($template['status'])
                            <span class="status {{ $template['status'] }}">{{ $template['status'] }}</span>
                        @endif
                    </div>
                    <code class="kind">{{ $template['slug'] }}@if ($template['category']) · {{ $template['category'] }}@endif @if ($template['theme']) · {{ $template['theme'] }}@endif</code>
                    @if ($template['description'])
                        <p class="description">{{ $template['description'] }}</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="none">No template repositories (folders with a template.json) in {{ $root }}.</p>
        @endforelse
    </main>
</body>
</html>
