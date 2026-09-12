<x-studio::layouts.iframe>
    @push('iframe-head')
        <style>
            /* ---- Designer Studio editing overlay (never shipped to production pages) ---- */
            .studio-section {
                position: relative;
            }

            /* Element-select mode (the Assistant's crosshair): outline whatever
               is under the pointer inside a section; the next click reports it */
            html.studio-element-select,
            html.studio-element-select * {
                cursor: crosshair !important;
            }

            html.studio-element-select [data-section-content] *:hover {
                outline: 2px solid #4c7dfa !important;
                outline-offset: 1px;
            }

            /* Scroll-reveal systems (site templates tag elements with data-reveal
               and hide them until their own script observes them into view)
               would leave freshly re-rendered markup invisible on the canvas —
               the template's observer only ever saw the original nodes. The
               canvas is an editing surface, so everything simply stays shown. */
            .studio-section [data-reveal] {
                opacity: 1 !important;
                visibility: visible !important;
                transform: none !important;
                translate: none !important;
                filter: none !important;
                transition: none !important;
                animation: none !important;
            }

            .studio-section::after {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                z-index: 2147483000;
                transition: box-shadow 120ms ease;
            }

            .studio-section:hover::after,
            .studio-section.is-hinted::after {
                box-shadow: inset 0 0 0 1.5px rgba(76, 125, 250, 0.75);
            }

            .studio-section.is-selected::after {
                box-shadow: inset 0 0 0 2px #4c7dfa;
            }

            .studio-section [data-section-content] {
                cursor: default;
            }

            /* Name chip */
            .studio-chip {
                position: absolute;
                top: 0;
                left: 0;
                z-index: 2147483002;
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 3px 9px 4px;
                border-radius: 0 0 8px 0;
                background: #4c7dfa;
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: -0.01em;
                line-height: 1.4;
                opacity: 0;
                transform: translateY(-2px);
                transition: opacity 120ms ease, transform 120ms ease;
                pointer-events: none;
                white-space: nowrap;
            }

            .studio-section:hover .studio-chip,
            .studio-section.is-hinted .studio-chip,
            .studio-section.is-selected .studio-chip {
                opacity: 1;
                transform: translateY(0);
            }

            /* ---- Field and item tiers ---- */
            .studio-fhalo {
                position: fixed;
                z-index: 2147483003;
                pointer-events: none;
                box-sizing: border-box;
                border-radius: 3px;
                box-shadow: inset 0 0 0 1.5px #4c7dfa;
                opacity: 0;
                transition: opacity 100ms ease;
            }

            .studio-fhalo.is-item {
                box-shadow: inset 0 0 0 1.5px #e08c2e;
                border-radius: 5px;
            }

            .studio-fhalo.is-code {
                box-shadow: inset 0 0 0 1.5px rgba(148, 148, 158, 0.55);
            }

            /* An undeclared echo — dashed, not solid: there is no field
               here yet to select, only one the chip offers to create. */
            .studio-fhalo.is-undeclared {
                box-shadow: none;
                border: 1.5px dashed rgba(148, 148, 158, 0.85);
            }

            .studio-fhalo.is-on {
                opacity: 1;
            }

            .studio-fchip {
                position: fixed;
                z-index: 2147483004;
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 2px 7px 3px;
                border-radius: 4px 4px 0 0;
                background: #4c7dfa;
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 10.5px;
                font-weight: 600;
                line-height: 1.45;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transition: opacity 100ms ease;
            }

            .studio-fchip.is-item { background: #e08c2e; }
            .studio-fchip.is-code { background: rgba(88, 88, 98, 0.92); }
            .studio-fchip.is-undeclared { background: rgba(88, 88, 98, 0.92); }
            .studio-fchip.is-on { opacity: 1; }

            .studio-fchip-src {
                font-weight: 500;
                opacity: 0.75;
                font-variant-numeric: tabular-nums;
            }

            /* ---- Oversized type cursor ---- */
            .studio-cursor {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 2147483006;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                margin: 14px 0 0 14px;
                border-radius: 8px 8px 8px 2px;
                background: #4c7dfa;
                color: #fff;
                box-shadow: 0 4px 12px -2px rgba(12, 12, 20, 0.4);
                pointer-events: none;
                opacity: 0;
                transform: translate3d(-100px, -100px, 0) scale(0.8);
                transition: opacity 90ms ease, transform 90ms ease, background-color 120ms ease;
                will-change: transform;
            }

            .studio-cursor.is-on {
                opacity: 1;
            }

            .studio-cursor.is-item { background: #e08c2e; border-radius: 8px 8px 2px 8px; }
            .studio-cursor.is-code { background: rgba(70, 70, 80, 0.92); }
            .studio-cursor.is-toggle { background: #e5484d; }

            .studio-cursor svg { width: 14px; height: 14px; }
            .studio-cursor span { font-size: 13px; font-weight: 700; line-height: 1; }

            /* Preview mode owns the canvas — no field chrome at all */
            html.studio-preview .studio-fhalo,
            html.studio-preview .studio-fchip,
            html.studio-preview .studio-cursor {
                display: none !important;
            }

            /* ---- Link/select/colour control ----
               A separate floating element from the chip on purpose: the
               chip is pointer-events:none by design (it must never block
               the hover it describes), so an interactive widget cannot
               live inside it. */
            .studio-control {
                position: fixed;
                z-index: 2147483007;
                display: none;
                align-items: center;
                gap: 6px;
                padding: 6px;
                border-radius: 8px;
                background: rgba(12, 12, 14, 0.96);
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 10px 28px -8px rgba(0, 0, 0, 0.5);
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 12px;
                color: #fff;
            }

            .studio-control.is-on { display: flex; }

            .studio-control input[type="text"],
            .studio-control select {
                height: 26px;
                min-width: 180px;
                padding: 0 8px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: rgba(255, 255, 255, 0.06);
                color: #fff;
                font: inherit;
            }

            .studio-control input[type="color"] {
                width: 28px;
                height: 26px;
                padding: 0;
                border: none;
                background: none;
            }

            /* The add-field control: a button, not an input — promoting an
               undeclared echo has no value to type. */
            .studio-control button {
                height: 26px;
                padding: 0 10px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: #4c7dfa;
                color: #fff;
                font: inherit;
                font-weight: 600;
                white-space: nowrap;
                cursor: pointer;
            }

            .studio-control button:hover {
                background: #3d6ae0;
            }

            html.studio-preview .studio-control { display: none !important; }

            /* ---- Toggle switch-off toast ----
               studio.js's toast() helper renders straight into whichever
               document it runs in; the canvas document never loads
               studio.css (only studio.js, via @studioIframeCore), so the
               toast markup needs its own styling here — a dark-chrome
               match for the halo/cursor/control overlay above it, not the
               editor window's design tokens. */
            #studio-toasts {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 2147483008;
                display: flex;
                flex-direction: column-reverse;
                align-items: center;
                gap: 8px;
                pointer-events: none;
            }

            .studio-toast {
                pointer-events: auto;
                display: flex;
                align-items: center;
                gap: 9px;
                max-width: 420px;
                padding: 9px 14px 9px 11px;
                border-radius: 12px;
                background: rgba(12, 12, 14, 0.96);
                border: 1px solid rgba(255, 255, 255, 0.12);
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 13px;
                font-weight: 500;
                box-shadow: 0 10px 28px -8px rgba(0, 0, 0, 0.5);
                cursor: pointer;
                opacity: 0;
                transform: translateY(8px) scale(0.97);
                transition: opacity 200ms ease, transform 200ms cubic-bezier(0.21, 1.02, 0.73, 1);
            }

            .studio-toast.is-visible {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

            .studio-toast__action {
                margin-left: 4px;
                padding: 3px 10px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.06);
                color: #fff;
                font: inherit;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                white-space: nowrap;
            }

            .studio-toast__action:hover {
                background: rgba(255, 255, 255, 0.12);
            }

            .studio-toast__icon { display: flex; width: 16px; height: 16px; }
            .studio-toast__icon svg { width: 16px; height: 16px; }
            .studio-toast--success .studio-toast__icon { color: #4ade80; }
            .studio-toast--error .studio-toast__icon { color: #f87171; }
            .studio-toast--info .studio-toast__icon { color: #4c7dfa; }

            html.studio-preview #studio-toasts { display: none !important; }

            /* A file dragged over an image field — a class on the target
               element itself, not a second overlay writer. */
            .studio-drop-target {
                outline: 2px dashed #4c7dfa !important;
                outline-offset: -2px;
            }

            /* ---- Inline text editing ---- */
            [data-sf-edit],
            [contenteditable="true"],
            [contenteditable="plaintext-only"] {
                outline: none;
                background: rgba(76, 125, 250, 0.1);
                box-shadow: 0 0 0 1.5px #4c7dfa;
                border-radius: 2px;
            }

            /* The halo and chip would only fight the caret while typing */
            html.studio-editing .studio-fhalo,
            html.studio-editing .studio-fchip,
            html.studio-editing .studio-cursor {
                opacity: 0 !important;
            }

            /* Floating toolbar — always wins pointer events over insert zones */
            .studio-toolbar {
                position: absolute;
                top: 8px;
                right: 8px;
                z-index: 2147483005;
                display: flex;
                align-items: center;
                gap: 2px;
                padding: 3px;
                border-radius: 10px;
                background: rgba(12, 12, 14, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                opacity: 0;
                transform: translateY(-4px);
                transition: opacity 130ms ease, transform 130ms ease;
                pointer-events: none;
            }

            .studio-section:hover .studio-toolbar,
            .studio-section.is-selected .studio-toolbar {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }

            .studio-toolbar button {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                border: 0;
                border-radius: 7px;
                background: transparent;
                color: rgba(255, 255, 255, 0.65);
                cursor: pointer;
                transition: background 100ms ease, color 100ms ease;
                padding: 0;
            }

            .studio-toolbar button:hover {
                background: rgba(255, 255, 255, 0.12);
                color: #fff;
            }

            .studio-toolbar button.studio-danger:hover {
                background: rgba(243, 114, 114, 0.18);
                color: #f37272;
            }

            .studio-toolbar button:disabled {
                opacity: 0.3;
                pointer-events: none;
            }

            .studio-toolbar svg {
                width: 14px;
                height: 14px;
            }

            .studio-toolbar .studio-toolbar-sep {
                width: 1px;
                height: 14px;
                margin: 0 2px;
                background: rgba(255, 255, 255, 0.14);
            }

            /* Insert affordance between sections */
            .studio-insert {
                position: absolute;
                left: 0;
                right: 0;
                height: 28px;
                z-index: 2147483004;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 130ms ease;
            }

            .studio-insert:hover {
                opacity: 1;
            }

            .studio-insert--top { top: -14px; }
            .studio-insert--bottom { bottom: -14px; }

            /* The very first insert zone sits flush against the top edge and
               stops short of the toolbar so both stay easy to hit */
            .studio-section:first-of-type .studio-insert--top {
                top: 0;
                height: 22px;
            }

            .studio-section:first-of-type .studio-insert--top::before {
                top: 1px;
                margin-top: 0;
            }

            .studio-section:first-of-type .studio-insert--top button {
                margin-top: -4px;
            }

            .studio-insert::before {
                content: '';
                position: absolute;
                left: 12px;
                right: 12px;
                top: 50%;
                height: 2px;
                margin-top: -1px;
                border-radius: 2px;
                background: #4c7dfa;
                box-shadow: 0 0 12px rgba(76, 125, 250, 0.55);
            }

            .studio-insert button {
                position: relative;
                display: flex;
                align-items: center;
                gap: 5px;
                height: 26px;
                padding: 0 12px;
                border: 0;
                border-radius: 999px;
                background: #4c7dfa;
                color: #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 11.5px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 16px -2px rgba(76, 125, 250, 0.55);
                transition: transform 120ms ease, background 120ms ease;
            }

            .studio-insert button:hover {
                background: #3d6ef2;
                transform: scale(1.04);
            }

            .studio-insert svg {
                width: 12px;
                height: 12px;
            }

            /* Global blocks — teal chrome (synced everywhere they're placed) */
            .studio-section.is-block:hover::after,
            .studio-section.is-block.is-hinted::after {
                box-shadow: inset 0 0 0 1.5px rgba(20, 184, 166, 0.75);
            }

            .studio-section.is-block.is-selected::after {
                box-shadow: inset 0 0 0 2px #14b8a6;
            }

            .studio-section.is-block .studio-chip {
                background: #14b8a6;
            }

            /* Layout sections — violet chrome so shared scope is obvious */
            .studio-section.is-layout:hover::after,
            .studio-section.is-layout.is-hinted::after {
                box-shadow: inset 0 0 0 1.5px rgba(139, 92, 246, 0.75);
            }

            .studio-section.is-layout.is-selected::after {
                box-shadow: inset 0 0 0 2px #8b5cf6;
            }

            .studio-section.is-layout .studio-chip {
                background: #8b5cf6;
            }

            .studio-chip-scope {
                display: inline-flex;
                align-items: center;
                padding: 1px 5px;
                border-radius: 4px;
                background: rgba(255, 255, 255, 0.22);
                font-size: 9px;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }

            .studio-insert--layout::before {
                background: #8b5cf6;
                box-shadow: 0 0 12px rgba(139, 92, 246, 0.55);
            }

            .studio-insert button.studio-add-layout {
                background: #8b5cf6;
                box-shadow: 0 4px 16px -2px rgba(139, 92, 246, 0.55);
            }

            .studio-insert button.studio-add-layout:hover {
                background: #7c4ddf;
            }

            /* Boundary between the layout and the page — offers both targets */
            .studio-insert--boundary {
                gap: 6px;
            }

            .studio-insert--boundary::before {
                background: linear-gradient(90deg, #8b5cf6, #4c7dfa);
                box-shadow: 0 0 12px rgba(103, 108, 248, 0.5);
            }

            /* Empty layout/content region placeholders */
            .studio-region {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 26px 16px;
                border: 0;
                width: 100%;
                background:
                    repeating-linear-gradient(-45deg, rgba(139, 92, 246, 0.045) 0, rgba(139, 92, 246, 0.045) 8px, transparent 8px, transparent 16px),
                    #faf9fe;
                box-shadow: inset 0 0 0 1.5px rgba(139, 92, 246, 0.25);
                cursor: pointer;
                font-family: ui-sans-serif, system-ui, sans-serif;
                transition: box-shadow 130ms ease, background-color 130ms ease;
            }

            .studio-region:hover {
                box-shadow: inset 0 0 0 1.5px rgba(139, 92, 246, 0.55);
            }

            .studio-region-label {
                font-size: 12px;
                font-weight: 600;
                letter-spacing: -0.01em;
                color: #7c5ce0;
            }

            .studio-region-pill {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                height: 24px;
                padding: 0 11px;
                border-radius: 999px;
                background: #8b5cf6;
                color: #fff;
                font-size: 11px;
                font-weight: 600;
            }

            .studio-region svg {
                width: 11px;
                height: 11px;
            }

            .studio-region--content {
                padding: 72px 16px;
                background:
                    radial-gradient(circle at 50% 40%, rgba(76, 125, 250, 0.05), transparent 65%),
                    #fff;
                box-shadow: inset 0 0 0 1.5px rgba(76, 125, 250, 0.22);
                flex-direction: column;
                gap: 6px;
            }

            .studio-region--content:hover {
                box-shadow: inset 0 0 0 1.5px rgba(76, 125, 250, 0.5);
            }

            .studio-region--content .studio-region-label {
                color: #18181b;
                font-size: 14.5px;
            }

            .studio-region--content .studio-region-hint {
                margin: 0 0 12px;
                font-size: 12.5px;
                color: #71717a;
            }

            .studio-region--content .studio-region-pill {
                height: 32px;
                padding: 0 16px;
                font-size: 12.5px;
                background: #4c7dfa;
                box-shadow: 0 8px 24px -6px rgba(76, 125, 250, 0.55);
            }

            /* Fixed-position sections (sticky headers) — rendered in place in
               the editor so the page stays easy to work on; the chip tag says
               why it behaves differently on the live site */
            .studio-section.is-fixed [data-section-content] > * {
                position: relative !important;
                top: auto !important;
                left: auto !important;
                right: auto !important;
                bottom: auto !important;
            }

            .studio-chip-fixed {
                display: inline-flex;
                align-items: center;
                gap: 3px;
            }

            /* Dev-mode-only chrome (toggled from the editor via localStorage) */
            .studio-devmode-only {
                display: none !important;
            }

            html.studio-devmode .studio-devmode-only {
                display: flex !important;
            }

            /* ---- Preview mode ----
               The canvas as a visitor sees it: no outlines, chips, toolbars
               or insert affordances, and links navigate. Editing chrome is
               only suppressed visually here — Studio.preview also refuses to
               select or add while the mode is on. */
            html.studio-preview .studio-chip,
            html.studio-preview .studio-toolbar,
            html.studio-preview .studio-insert,
            html.studio-preview .studio-region {
                display: none !important;
            }

            html.studio-preview .studio-section::after {
                display: none !important;
            }

            html.studio-preview .studio-section {
                cursor: auto;
            }

            /* ---- Context menu — dark, Linear-grade, elastic ---- */
            .studio-menu {
                position: fixed;
                z-index: 2147483008;
                min-width: 216px;
                padding: 5px;
                border-radius: 12px;
                background: rgba(23, 23, 28, 0.96);
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow:
                    0 0 0 1px rgba(0, 0, 0, 0.45),
                    0 16px 48px -12px rgba(0, 0, 0, 0.65),
                    inset 0 1px 0 rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(16px) saturate(1.4);
                -webkit-backdrop-filter: blur(16px) saturate(1.4);
                font-family: ui-sans-serif, system-ui, sans-serif;
                opacity: 0;
                transform: scale(0.9);
                transition:
                    opacity 140ms ease,
                    transform 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
                user-select: none;
                -webkit-user-select: none;
            }

            .studio-menu.is-open {
                opacity: 1;
                transform: scale(1);
            }

            .studio-menu.is-closing {
                opacity: 0;
                transform: scale(0.96);
                transition: opacity 110ms ease, transform 110ms ease;
                pointer-events: none;
            }

            .studio-menu-header {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 5px 9px 6px;
                font-size: 10.5px;
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: rgba(255, 255, 255, 0.38);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .studio-menu-item {
                display: flex;
                align-items: center;
                gap: 9px;
                width: 100%;
                padding: 6px 9px;
                border: 0;
                border-radius: 8px;
                background: transparent;
                color: rgba(255, 255, 255, 0.82);
                font-family: inherit;
                font-size: 12.5px;
                font-weight: 500;
                letter-spacing: -0.005em;
                text-align: left;
                cursor: pointer;
                transition: background 90ms ease, color 90ms ease;
                white-space: nowrap;
            }

            .studio-menu-item:hover {
                background: rgba(255, 255, 255, 0.09);
                color: #fff;
            }

            .studio-menu-item:disabled {
                opacity: 0.32;
                pointer-events: none;
            }

            .studio-menu-item svg {
                width: 14px;
                height: 14px;
                flex-shrink: 0;
                opacity: 0.72;
            }

            .studio-menu-item:hover svg {
                opacity: 1;
            }

            .studio-menu-item .studio-menu-kbd {
                margin-left: auto;
                padding-left: 18px;
                font-family: ui-monospace, 'SF Mono', Menlo, monospace;
                font-size: 10.5px;
                font-weight: 500;
                color: rgba(255, 255, 255, 0.32);
            }

            .studio-menu-item.studio-menu-danger:hover {
                background: rgba(243, 114, 114, 0.14);
                color: #f89b9b;
            }

            .studio-menu-sep {
                height: 1px;
                margin: 4px 7px;
                background: rgba(255, 255, 255, 0.08);
            }

            /* Hidden sections — dimmed with stripes in the editor only */
            .studio-section.is-hidden [data-section-content] {
                opacity: 0.35;
                filter: grayscale(0.6);
            }

            .studio-section.is-hidden::before {
                content: '';
                position: absolute;
                inset: 0;
                z-index: 2147483001;
                pointer-events: none;
                background: repeating-linear-gradient(
                    -45deg,
                    rgba(120, 120, 135, 0.08) 0,
                    rgba(120, 120, 135, 0.08) 10px,
                    transparent 10px,
                    transparent 20px
                );
            }

            .studio-hidden-badge {
                position: absolute;
                top: 8px;
                left: 8px;
                z-index: 2147483002;
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 3px 8px;
                border-radius: 6px;
                background: rgba(12, 12, 14, 0.85);
                color: rgba(255, 255, 255, 0.85);
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 10.5px;
                font-weight: 600;
                pointer-events: none;
            }

            .studio-hidden-badge svg {
                width: 11px;
                height: 11px;
            }

            /* Empty page state */
            .studio-empty {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                background:
                    radial-gradient(circle at 50% 30%, rgba(76, 125, 250, 0.04), transparent 60%),
                    #fff;
                font-family: ui-sans-serif, system-ui, sans-serif;
            }

            .studio-empty-inner {
                text-align: center;
                padding: 24px;
                max-width: 380px;
            }

            .studio-empty-mark {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 52px;
                height: 52px;
                margin: 0 auto 18px;
                border-radius: 14px;
                background: #0b0b0d;
                box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.35);
            }

            .studio-empty h2 {
                margin: 0 0 6px;
                font-size: 17px;
                font-weight: 650;
                letter-spacing: -0.02em;
                color: #18181b;
            }

            .studio-empty p {
                margin: 0 0 20px;
                font-size: 13.5px;
                line-height: 1.6;
                color: #71717a;
            }

            .studio-empty button {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 38px;
                padding: 0 18px;
                border: 0;
                border-radius: 10px;
                background: #4c7dfa;
                color: #fff;
                font-size: 13.5px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 8px 24px -6px rgba(76, 125, 250, 0.55);
                transition: transform 120ms ease, background 120ms ease;
            }

            .studio-empty button:hover {
                background: #3d6ef2;
                transform: translateY(-1px);
            }

            .studio-empty svg {
                width: 14px;
                height: 14px;
            }
        </style>
    @endpush

    {{-- Live-edit state. Sections re-render on the server (POST
         studio.api.render) so the canvas uses the same Blade engine as the
         published page — only the current values and the section refs need
         to travel with the document. --}}
    <script>
        window.__studioPreview = {
            refs: @js(collect($sections)->pluck('ref', 'id')->toArray()),
            variables: @js($componentVariables),
            bindings: @js($componentBindings ?? []),
            blocks: @js($blockByInstance),
            renderUrl: @js(route('studio.api.render')),
            csrf: @js(csrf_token()),
            paths: @js($componentPaths),
            contracts: @js($componentContracts),
        };
    </script>

    @php
        $renderedBefore = count(array_filter($sections, fn ($s) => $s['scope'] === 'layout' && $s['docIndex'] < $layoutBeforeCount));
        $renderedPage = count(array_filter($sections, fn ($s) => $s['scope'] === 'page'));

        // Boundary positions: first page section (layout header ends above it)
        // and first footer section (page content ends above it)
        $firstPageIdx = null;
        $firstFooterIdx = null;
        foreach ($sections as $i => $s) {
            if ($firstPageIdx === null && $s['scope'] === 'page') {
                $firstPageIdx = $i;
            }
            if ($firstFooterIdx === null && $s['scope'] === 'layout' && $s['docIndex'] > $layoutBeforeCount) {
                $firstFooterIdx = $i;
            }
        }
    @endphp

    @if(count($sections) > 0 || $layout)
        {{-- Empty layout header region --}}
        @if($layout && $renderedBefore === 0)
            <button type="button" class="studio-region" onclick="Studio.preview.addAt('layout', 0, event)">
                <span class="studio-region-label">{{ $layout['name'] }} — header</span>
                <span class="studio-region-pill">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Add section
                </span>
            </button>
        @endif

        @foreach($sections as $section)
            @if($layout && $renderedPage === 0 && $loop->index === $renderedBefore)
                @include('studio::partials.canvas-content-placeholder', ['page' => $page])
            @endif

            @php
                $rendered = app(\Designer\Studio\Services\SectionRenderer::class)
                    ->renderHtml(
                        $section['html'],
                        $componentVariables[$section['id']] ?? [],
                        $section['ref'],
                        $section['fields'] ?? [],
                        instrument: true,
                    );
                $isLayout = $section['scope'] === 'layout';
                $isBlock = !empty($section['block']);
            @endphp

            <div
                data-section="{{ $section['id'] }}"
                data-scope="{{ $section['scope'] }}"
                data-ref="{{ $section['ref'] }}"
                data-title="{{ $section['title'] }}"
                data-doc-index="{{ $section['docIndex'] }}"
                data-doc-first="{{ $section['docFirst'] ? '1' : '0' }}"
                data-doc-last="{{ $section['docLast'] ? '1' : '0' }}"
                data-hidden="{{ $section['hidden'] ? '1' : '0' }}"
                data-block="{{ $isBlock ? '1' : '0' }}"
                class="studio-section {{ $section['hidden'] ? 'is-hidden' : '' }} {{ $isLayout ? 'is-layout' : '' }} {{ $isBlock ? 'is-block' : '' }} {{ $section['fixed'] ? 'is-fixed' : '' }}"
                onclick="Studio.preview.select('{{ $section['id'] }}', event)"
            >
                @php
                    $plus = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>';
                    $layoutLabel = fn ($docIndex) => $docIndex <= $layoutBeforeCount ? 'Add to header' : 'Add to footer';
                @endphp

                {{-- Insert above (into this section's own document; boundaries offer both) --}}
                @if($layout && $loop->index === $firstPageIdx && $renderedBefore > 0)
                    <div class="studio-insert studio-insert--top studio-insert--boundary">
                        <button type="button" class="studio-add-layout" onclick="Studio.preview.addAt('layout', {{ $layoutBeforeCount }}, event)">
                            {!! $plus !!} Add to header
                        </button>
                        <button type="button" onclick="Studio.preview.addAt('page', 0, event)">
                            {!! $plus !!} Add section
                        </button>
                    </div>
                @elseif($layout && $loop->index === $firstFooterIdx)
                    <div class="studio-insert studio-insert--top studio-insert--boundary">
                        <button type="button" onclick="Studio.preview.addAt('page', {{ $pageSectionCount }}, event)">
                            {!! $plus !!} Add section
                        </button>
                        <button type="button" class="studio-add-layout" onclick="Studio.preview.addAt('layout', {{ $section['docIndex'] }}, event)">
                            {!! $plus !!} Add to footer
                        </button>
                    </div>
                @else
                    <div class="studio-insert studio-insert--top {{ $isLayout ? 'studio-insert--layout' : '' }}">
                        <button type="button" class="{{ $isLayout ? 'studio-add-layout' : '' }}" onclick="Studio.preview.addAt('{{ $section['scope'] }}', {{ $section['docIndex'] }}, event)">
                            {!! $plus !!}
                            {{ $isLayout ? $layoutLabel($section['docIndex']) : 'Add section' }}
                        </button>
                    </div>
                @endif

                {{-- Name chip --}}
                <span class="studio-chip">
                    {{ $section['title'] }}
                    @if($isBlock)
                        <span class="studio-chip-scope">Global</span>
                    @elseif($isLayout)
                        <span class="studio-chip-scope">Layout</span>
                    @endif
                    @if($section['fixed'])
                        <span class="studio-chip-scope studio-chip-fixed" title="Position: fixed on the live site — shown in place here so the page stays easy to work on">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:9px;height:9px"><path d="M10 2a1 1 0 0 1 1 1v5.586l2.293 2.293A1 1 0 0 1 12.586 13H10.75v4.25a.75.75 0 0 1-1.5 0V13H7.414a1 1 0 0 1-.707-1.707L9 9.586V3a1 1 0 0 1 1-1Z"/></svg>
                            Fixed
                        </span>
                    @endif
                </span>

                @if($section['hidden'])
                    <span class="studio-hidden-badge">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                        Hidden
                    </span>
                @endif

                {{-- Toolbar --}}
                <div class="studio-toolbar" onclick="event.stopPropagation()">
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'move-up', event)" title="Move up" {{ $section['docFirst'] ? 'disabled' : '' }}>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd"/></svg>
                    </button>
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'move-down', event)" title="Move down" {{ $section['docLast'] ? 'disabled' : '' }}>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.53 13.53a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 1.06-1.06L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25Z" clip-rule="evenodd"/></svg>
                    </button>
                    <span class="studio-toolbar-sep"></span>
                    @if(!$isBlock && !$isLayout)
                        <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'make-global', event)" title="Make global — reuse this section on any page">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.196 12.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 12.87Z"/><path d="M3.196 8.87l-.825.483a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .758 0l7.25-4.25a.75.75 0 0 0 0-1.294l-.825-.484-5.666 3.322a2.25 2.25 0 0 1-2.276 0L3.196 8.87Z"/><path d="M10.38 1.103a.75.75 0 0 0-.76 0l-7.25 4.25a.75.75 0 0 0 0 1.294l7.25 4.25a.75.75 0 0 0 .76 0l7.25-4.25a.75.75 0 0 0 0-1.294l-7.25-4.25Z"/></svg>
                        </button>
                    @endif
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'duplicate', event)" title="Duplicate (⌘D)">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    </button>
                    @if(\Designer\Studio\Support\DevMode::enabled())
                        <button type="button" class="studio-devmode-only" onclick="Studio.preview.openCode('{{ $section['ref'] }}', '{{ $section['title'] }}', event)" title="Edit source code — .blade.php + .yml (dev mode)">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                    @endif
                    <button type="button" onclick="Studio.preview.action('{{ $section['id'] }}', 'toggle-hidden', event)" title="{{ $section['hidden'] ? 'Show' : 'Hide' }}">
                        @if($section['hidden'])
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                        @else
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                        @endif
                    </button>
                    <span class="studio-toolbar-sep"></span>
                    <button type="button" class="studio-danger" onclick="Studio.preview.action('{{ $section['id'] }}', 'delete', event)" title="Delete (⌫)">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193v-.443A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Zm-1.586 4.914a.75.75 0 1 0-1.498.086l.5 8.5a.75.75 0 0 0 1.498-.086l-.5-8.5Zm4.67.086a.75.75 0 1 0-1.498-.086l-.5 8.5a.75.75 0 0 0 1.498.086l.5-8.5Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                {{-- Rendered section --}}
                <div data-section-content>{!! $rendered !!}</div>

                {{-- Insert below (last section only — other boundaries use the next section's top zone) --}}
                @if($loop->last)
                    <div class="studio-insert studio-insert--bottom {{ $isLayout ? 'studio-insert--layout' : '' }}">
                        <button type="button" class="{{ $isLayout ? 'studio-add-layout' : '' }}" onclick="Studio.preview.addAt('{{ $section['scope'] }}', {{ $section['docIndex'] + 1 }}, event)">
                            {!! $plus !!}
                            {{ $isLayout ? $layoutLabel($section['docIndex'] + 1) : 'Add section' }}
                        </button>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Empty page content when the layout has no footer to inject before --}}
        @if($layout && $renderedPage === 0 && count($sections) === $renderedBefore)
            @include('studio::partials.canvas-content-placeholder', ['page' => $page])
        @endif

        {{-- Empty layout footer region --}}
        @if($layout && $layoutAfterCount === 0)
            <button type="button" class="studio-region" onclick="Studio.preview.addAt('layout', {{ $layoutComponentCount }}, event)">
                <span class="studio-region-label">{{ $layout['name'] }} — footer</span>
                <span class="studio-region-pill">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Add section
                </span>
            </button>
        @endif
    @else
        <div class="studio-empty">
            <div class="studio-empty-inner">
                <div class="studio-empty-mark">
                    <svg width="24" height="25" viewBox="0 0 72 75" fill="none">
                        <path fill="#fff" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2>{{ $page->title }} is empty</h2>
                <p>Add your first section from the library — heroes, features, pricing, testimonials, and more.</p>
                <button type="button" onclick="Studio.preview.addAt('page', null, event)">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Add a section
                </button>
            </div>
        </div>
    @endif

    {{-- Field/item tier overlay, positioned from JS --}}
    <div class="studio-fhalo" id="studio-fhalo"></div>
    <div class="studio-fchip" id="studio-fchip"></div>
    <div class="studio-cursor" id="studio-cursor"></div>
    <div class="studio-control" id="studio-control"></div>
</x-studio::layouts.iframe>
