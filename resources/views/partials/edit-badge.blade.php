{{-- Injected by InjectEditBadge before </body> on pages the site's runtime serves. Self-contained: every rule is scoped to the id and resets first, so it survives any site's CSS. --}}
<style>
    #designer-studio-badge, #designer-studio-badge span { all: revert; box-sizing: border-box; }
    #designer-studio-badge {
        --dsb-ease: cubic-bezier(.32, .72, 0, 1);
        position: fixed; left: 50%; bottom: 15px; z-index: 2147483000;
        display: inline-flex; align-items: center; gap: 5px;
        height: 25px; padding: 0 8px 1px 8px; margin: 0;
        border-radius: 999px; border: 0;
        background: linear-gradient(to bottom, #262626, #171717);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .08),
            0 0 0 1px rgba(0, 0, 0, .9),
            0 1px 2px rgba(0, 0, 0, .12),
            0 8px 24px -6px rgba(0, 0, 0, .28);
        color: #fafafa; text-decoration: none; white-space: nowrap; cursor: pointer;
        font: 600 11px/1 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        letter-spacing: -.005em;
        -webkit-font-smoothing: antialiased;
        transform: translateX(-50%) scale(1);
        transform-origin: center;
        transition: transform .3s var(--dsb-ease), box-shadow .3s var(--dsb-ease), background .2s ease;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }
    #designer-studio-badge:hover {
        transform: translateX(-50%) scale(1.05);
        background: linear-gradient(to bottom, #2e2e2e, #1c1c1c);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .1),
            0 0 0 1px rgba(0, 0, 0, .9),
            0 2px 4px rgba(0, 0, 0, .12),
            0 14px 32px -8px rgba(0, 0, 0, .36);
    }
    #designer-studio-badge:active { transform: translateX(-50%) scale(1.02); transition-duration: .12s; }
    #designer-studio-badge:focus-visible { outline: 2px solid #fafafa; outline-offset: 2px; box-shadow: 0 0 0 4px #171717; }

    #designer-studio-badge .dsb-icon { position: relative; display: block; width: 12px; height: 12px; flex: none; }
    #designer-studio-badge .dsb-icon svg {
        position: absolute; inset: 0; display: block; width: 12px; height: 12px;
        transition: transform .35s var(--dsb-ease), opacity .2s ease, filter .25s ease;
    }
    #designer-studio-badge .dsb-pencil { opacity: 0; transform: rotate(-60deg) scale(.4); filter: blur(2px); }
    #designer-studio-badge:hover .dsb-logo,
    #designer-studio-badge:focus-visible .dsb-logo { opacity: 0; transform: rotate(60deg) scale(.4); filter: blur(2px); }
    #designer-studio-badge:hover .dsb-pencil,
    #designer-studio-badge:focus-visible .dsb-pencil { opacity: 1; transform: rotate(0) scale(1); filter: blur(0); transition-delay: .04s; }

    @media (prefers-reduced-motion: reduce) {
        #designer-studio-badge, #designer-studio-badge .dsb-icon svg { transition: opacity .15s ease; }
        #designer-studio-badge:hover, #designer-studio-badge:active { transform: translateX(-50%); }
        #designer-studio-badge .dsb-pencil, #designer-studio-badge:hover .dsb-logo { transform: none; filter: none; }
    }
    @media print { #designer-studio-badge { display: none; } }
</style>
<a id="designer-studio-badge" href="{{ $url }}" title="Edit this page in Designer Studio">
    <span class="dsb-icon" aria-hidden="true">
        <svg class="dsb-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 72 75" fill="none"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/></svg>
        <svg class="dsb-pencil" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
    </span>
    <span>Edit Page</span>
</a>
<script>if (window.top !== window.self) document.getElementById('designer-studio-badge')?.remove();</script>
