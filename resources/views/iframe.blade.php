<x-studio::layouts.iframe>
    @push('iframe-head')
        <style>
            /* ---- Designer Studio editing overlay (never shipped to production pages) ---- */
            /* The canvas's scrollbar is hidden until used: no native bar, and
               StudioScrollThumb (studio.js) draws one over the edge while the
               page scrolls */
            html {
                scrollbar-width: none;
            }

            html::-webkit-scrollbar,
            body::-webkit-scrollbar {
                display: none;
            }

            .studio-section {
                position: relative;
                --studio-rail-h: 50px;
            }

            /* The Assistant's pick tool: a dashed violet outline on whatever is
               under the pointer inside a section; the next click reports it.
               Violet, dashed, and with every Edit-mode affordance hidden, so
               it can't be mistaken for selecting a section (solid blue). */
            html.studio-element-select,
            html.studio-element-select * {
                cursor: crosshair !important;
            }

            /* Only the deepest hovered element — :hover also matches every
               ancestor under the pointer, which would nest the outlines */
            html.studio-element-select [data-section-content] *:hover:not(:has(:hover)) {
                outline: 2px dashed #8b5cf6 !important;
                outline-offset: 2px;
                background-color: rgba(139, 92, 246, 0.07) !important;
                border-radius: 3px;
            }

            /* What the Assistant's composer is holding (studio:context): the
               picked element keeps the pick tool's dashed violet for as long
               as the chat has it — an outline only, so the element's own
               shape and colours are left alone. With no element, the section
               wears the ring, just inside its edge. */
            [data-studio-context] {
                outline: 1.5px dashed #8b5cf6 !important;
                outline-offset: 3px !important;
            }

            .studio-section.is-context::after {
                outline: 1.5px dashed rgba(139, 92, 246, 0.9);
                outline-offset: -4px;
            }

            /* The section being edited on its own is the subject already */
            html.studio-focus .studio-section.is-context::after {
                outline: none;
            }

            html.studio-element-select .studio-section::after,
            html.studio-element-select .studio-chip,
            html.studio-element-select .studio-toolbar,
            html.studio-element-select .studio-insert,
            html.studio-element-select .studio-region,
            html.studio-element-select .studio-fhalo,
            html.studio-element-select .studio-fchip,
            html.studio-element-select .studio-cursor,
            html.studio-element-select .studio-item-flank,
            html.studio-element-select .studio-item-toolbar {
                display: none !important;
            }

            /* A violet edge on the whole canvas while it is armed. It follows
               the corners the editor cuts off the canvas (--studio-canvas-corners,
               from studio:corners). */
            html.studio-element-select body::after {
                content: '';
                position: fixed;
                inset: 0;
                pointer-events: none;
                z-index: 2147483001;
                border-radius: var(--studio-canvas-corners, 0);
                box-shadow: inset 0 0 0 2px rgba(139, 92, 246, 0.75);
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

            /* The outline. The editor shows the canvas through a rounded
               window, so an outline whose corner reaches a corner of the
               canvas would be cut off there: the runtime gives each section
               the radius that keeps it inside (--studio-corners, set by
               StudioPreview.roundCorners() from where the section sits). */
            .studio-section::after {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                z-index: 2147483000;
                border-radius: var(--studio-corners, 0);
                background-color: transparent;
                transition: box-shadow 120ms ease, background-color 220ms ease;
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

            /* ---- Section chrome rail ----
               The chip, the hidden badge and the floating toolbar ride in one
               sticky, zero-impact rail so a tall section keeps its controls
               within reach while you scroll it. The rail is a real in-flow box
               (sticky needs one) whose height the content takes straight back,
               and it is tall enough that the sticky clamp parks the toolbar
               just inside the section's bottom edge instead of letting it
               trail into the next one. */
            .studio-rail {
                position: sticky;
                top: 0;
                z-index: 2147483005;
                height: var(--studio-rail-h);
                pointer-events: none;
            }

            .studio-rail + [data-section-content] {
                margin-top: calc(var(--studio-rail-h) * -1);
            }

            /* ---- Focus: one section is being edited ----
               The page steps aside. Every other section (and the regions and
               add pills) leaves the layout, and the section being edited
               becomes one card on a quiet dotted canvas — its own page
               background kept inside the card (--studio-site-bg, read from
               the body by setFocus()), a hairline ring and a soft shadow
               around it, the same 32px of canvas on every side. Its rail
               leaves the card and becomes a bar across the top of the canvas
               (--studio-bar-h) carrying the chip and the toolbar; the card
               scrolls under it. Done, Esc or a click on the canvas around
               the card brings the page back. */
            html.studio-focus {
                --studio-bar-h: 56px;
                --studio-focus-gap: 32px;
            }

            html.studio-focus,
            html.studio-focus body {
                background: #ececee !important;
            }

            html.studio-focus body {
                background-image: radial-gradient(rgba(0, 0, 0, 0.13) 1px, transparent 1.2px) !important;
                background-size: 22px 22px !important;
                background-position: 11px 11px !important;
                min-height: 100vh;
            }

            html.studio-focus .studio-section:not(.is-editing),
            html.studio-focus .studio-region,
            html.studio-focus .studio-insert,
            html.studio-focus .studio-tb-btn.is-move,
            html.studio-focus .studio-tb-sep.is-move {
                display: none !important;
            }

            /* No overflow on the card itself and nothing that transforms it
               (the lift animates `top`): either would capture the fixed bar
               inside the card. The content carries the rounded clip. */
            html.studio-focus .studio-section.is-editing {
                margin: calc(var(--studio-bar-h) + var(--studio-focus-gap)) auto 0;
                width: calc(100% - var(--studio-focus-gap) * 2);
                max-width: 1440px;
                border-radius: 14px;
                background: var(--studio-site-bg, #fff);
                box-shadow:
                    0 0 0 1px rgba(0, 0, 0, 0.07),
                    0 1px 2px rgba(0, 0, 0, 0.05),
                    0 40px 80px -32px rgba(0, 0, 0, 0.35);
                animation: studio-lift 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
            }

            html.studio-focus .studio-section.is-editing::after {
                box-shadow: none;
            }

            html.studio-focus .studio-section.is-editing [data-section-content] {
                border-radius: 14px;
                overflow: clip;
            }

            /* A section that opens something past its own edge — a header's
               dropdown, a mega panel — is not cut off at the card: the menu
               hangs over the canvas as it hangs over the page. The clip is
               redrawn as the card's rounded rectangle plus everything below
               its bottom edge, so the corners stay round and only the way
               down is open. Asked of what the section holds (a header, a
               nav, anything expanded), so a hero whose glow bleeds past its
               bottom edge still stops at the card. Where shape() is not
               understood the clip is simply lifted. Raised one step so the
               menu covers the line written under the card. */
            html.studio-focus .studio-section.is-editing [data-section-content]:has(header, nav, [aria-expanded="true"]) {
                position: relative;
                z-index: 1;
                overflow: visible;
                clip-path: shape(
                    from 14px 0,
                    hline to calc(100% - 14px),
                    arc to 100% 14px of 14px cw,
                    vline to calc(100% - 14px),
                    arc to calc(100% - 14px) 100% of 14px cw,
                    hline to calc(100% + 100vw),
                    vline to calc(100% + 200vh),
                    hline to -100vw,
                    vline to 100%,
                    hline to 14px,
                    arc to 0 calc(100% - 14px) of 14px cw,
                    vline to 14px,
                    arc to 14px 0 of 14px cw,
                    close
                );
            }

            /* The rail is the bar: the width of the canvas, fixed to its top
               edge, a frosted strip of the canvas's own grey so the card
               reads through it as it scrolls under. It takes the pointer —
               a click on it is not a click "around the card". */
            html.studio-focus .studio-section.is-editing .studio-rail {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: var(--studio-bar-h);
                pointer-events: auto;
                background: rgba(246, 246, 247, 0.8);
                backdrop-filter: blur(14px) saturate(1.4);
                -webkit-backdrop-filter: blur(14px) saturate(1.4);
                box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.08);
                animation: studio-fade 240ms ease both;
            }

            html.studio-focus .studio-section.is-editing .studio-rail + [data-section-content] {
                margin-top: 0;
            }

            html.studio-focus .studio-section.is-editing .studio-chip,
            html.studio-focus .studio-section.is-editing .studio-hidden-badge {
                left: 16px;
                top: calc((var(--studio-bar-h) - 24px) / 2);
                opacity: 1;
                transform: none;
            }

            html.studio-focus .studio-section.is-editing .studio-toolbar {
                right: 12px;
                top: calc((var(--studio-bar-h) - 32px) / 2);
                opacity: 1;
                transform: none;
                pointer-events: auto;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12), 0 8px 24px -12px rgba(0, 0, 0, 0.4);
            }

            /* The way back, written under the card */
            html.studio-focus body::after {
                content: 'Editing this section on its own\00a0\00a0\00b7\00a0\00a0 Done or Esc brings the page back';
                display: block;
                padding: 18px 0 40px;
                text-align: center;
                font-family: Geist, ui-sans-serif, system-ui, -apple-system, sans-serif;
                font-size: 12px;
                letter-spacing: -0.005em;
                color: rgba(60, 60, 67, 0.55);
                animation: studio-fade 480ms ease both;
            }

            @keyframes studio-lift {
                from { opacity: 0; top: 12px; }
                to { opacity: 1; top: 0; }
            }

            @keyframes studio-fade {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @media (prefers-reduced-motion: reduce) {
                html.studio-focus .studio-section.is-editing,
                html.studio-focus .studio-section.is-editing .studio-rail,
                html.studio-focus body::after { animation: none; }
            }

            /* Name chip — a small dark pill with the scope as a dot */
            .studio-chip {
                position: absolute;
                top: 10px;
                left: 10px;
                z-index: 2147483002;
                display: flex;
                align-items: center;
                gap: 6px;
                height: 24px;
                padding: 0 9px 0 8px;
                border-radius: 7px;
                background: rgba(17, 17, 19, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18), 0 8px 24px -10px rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                color: rgba(255, 255, 255, 0.92);
                font-family: Geist, ui-sans-serif, system-ui, -apple-system, sans-serif;
                font-size: 11.5px;
                font-weight: 500;
                letter-spacing: -0.005em;
                line-height: 1;
                opacity: 0;
                transform: translateY(-3px);
                transition: opacity 130ms ease, transform 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
                pointer-events: none;
                white-space: nowrap;
            }

            .studio-chip::before {
                content: '';
                width: 6px;
                height: 6px;
                border-radius: 999px;
                background: #4c7dfa;
                box-shadow: 0 0 0 2px rgba(76, 125, 250, 0.22);
            }

            .studio-section.is-layout .studio-chip::before {
                background: #8b5cf6;
                box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.22);
            }

            .studio-section.is-block .studio-chip::before {
                background: #14b8a6;
                box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.22);
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

            /* A collection-bound list: one outline around every row, in the
               colour of the collection badge — data, not a repeater item */
            .studio-fhalo.is-collection {
                box-shadow: inset 0 0 0 1.5px #4053ff;
                background: rgba(64, 83, 255, 0.045);
                border-radius: 8px;
            }

            .studio-fchip.is-item { background: #e08c2e; }
            .studio-fchip.is-collection { background: #4053ff; }
            .studio-fchip.is-undeclared { background: rgba(88, 88, 98, 0.92); }
            .studio-fchip.is-on { opacity: 1; }

            .studio-fchip-src {
                font-weight: 500;
                opacity: 0.75;
                font-variant-numeric: tabular-nums;
            }

            /* ---- Type cursor ----
               Over an editable field the pointer is this badge: a circle
               with one square corner, that corner sitting exactly on the
               pointer (an arrow's tip, not a tag beside it). The outer
               element only translates (written per pointer event); the
               inner shape carries the entrance/exit scale from that same
               corner, so it grows out of the pointer rather than popping
               in beside it. No badge = the native cursor = nothing to
               edit here. */
            .studio-cursor {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 2147483006;
                pointer-events: none;
                transform: translate3d(-100px, -100px, 0);
                will-change: transform;
            }

            .studio-cursor-shape {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                border-radius: 0 50% 50% 50%;
                background: #4053ff;
                color: #fff;
                box-shadow: 0 1px 2px rgba(12, 12, 20, 0.2), 0 6px 16px -4px rgba(64, 83, 255, 0.55);
                opacity: 0;
                transform: scale(0.4);
                transform-origin: 0 0;
                transition: opacity 110ms cubic-bezier(0.2, 0, 0, 1), transform 160ms cubic-bezier(0.2, 0, 0, 1), background-color 120ms ease;
            }

            .studio-cursor.is-on .studio-cursor-shape {
                opacity: 1;
                transform: scale(1);
            }

            .studio-cursor.is-item .studio-cursor-shape { background: #e08c2e; }
            .studio-cursor.is-toggle .studio-cursor-shape { background: #e5484d; }
            .studio-cursor.is-undeclared .studio-cursor-shape { background: rgba(70, 70, 80, 0.92); }

            .studio-cursor-shape svg { width: 15px; height: 15px; margin: 1px 0 0 1px; }

            /* The badge replaces the native cursor over the site while it
               shows; the moment it is gone, so is the replacement. Typing
               keeps the I-beam — there the caret is the affordance. */
            html.studio-cursor-on:not(.studio-editing) [data-section-content],
            html.studio-cursor-on:not(.studio-editing) [data-section-content] * {
                cursor: none !important;
            }

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

            /* ---- Collection card ----
               A collection-bound list, opened in place: its rows in a card
               beside it, the clicked one unfolded into its fields. Same
               material as the control above — it is the same idea, grown. */
            .studio-coll-ring {
                position: absolute;
                z-index: 2147483002;
                display: none;
                pointer-events: none;
                box-sizing: border-box;
                border-radius: 10px;
                box-shadow: 0 0 0 1.5px #4053ff, 0 0 0 5px rgba(64, 83, 255, 0.14);
            }

            .studio-coll-ring.is-on { display: block; }

            /* The row the card is pointing at */
            .studio-coll-spot {
                outline: 1.5px solid rgba(64, 83, 255, 0.9) !important;
                outline-offset: 2px;
                border-radius: 6px;
            }

            .studio-coll {
                position: absolute;
                z-index: 2147483007;
                display: none;
                flex-direction: column;
                width: 320px;
                max-height: min(560px, calc(100vh - 100px));
                border-radius: 14px;
                background: rgba(14, 14, 17, 0.97);
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.35), 0 24px 60px -18px rgba(0, 0, 0, 0.6), 0 8px 20px -10px rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                font-family: Geist, ui-sans-serif, system-ui, sans-serif;
                font-size: 12.5px;
                line-height: 1.4;
                letter-spacing: 0;
                text-align: left;
                color: rgba(255, 255, 255, 0.92);
                overflow: hidden;
                cursor: default;
            }

            .studio-coll.is-on {
                display: flex;
                animation: studio-coll-in 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
            }

            @keyframes studio-coll-in {
                from { opacity: 0; transform: translateY(4px) scale(0.985); }
                to { opacity: 1; transform: none; }
            }

            .studio-coll *, .studio-coll *::before, .studio-coll *::after { box-sizing: border-box; }

            .studio-coll svg { width: 14px; height: 14px; flex: none; }

            .studio-coll button {
                margin: 0;
                border: 0;
                background: none;
                color: inherit;
                font: inherit;
                cursor: pointer;
            }

            .studio-coll-head {
                display: flex;
                align-items: center;
                gap: 2px;
                padding: 8px 8px 8px 12px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }

            .studio-coll-mark { display: flex; margin-right: 6px; color: #7c8cff; }

            .studio-coll-title {
                flex: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-weight: 600;
                font-size: 13px;
            }

            .studio-coll-state {
                margin-right: 4px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.4);
                font-variant-numeric: tabular-nums;
                transition: color 150ms ease;
            }

            .studio-coll-state.is-saved { color: #4ade80; }
            .studio-coll-state.is-error { color: #f87171; }

            .studio-coll-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                border-radius: 7px;
                color: rgba(255, 255, 255, 0.55) !important;
                transition: background-color 120ms ease, color 120ms ease;
            }

            .studio-coll-icon:hover { background: rgba(255, 255, 255, 0.08); color: #fff !important; }

            .studio-coll-rows {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                padding: 4px;
                overscroll-behavior: contain;
            }

            .studio-coll-empty { margin: 0; padding: 18px 10px; text-align: center; color: rgba(255, 255, 255, 0.45); }

            .studio-coll-row { border-radius: 10px; }

            .studio-coll-row.is-open {
                margin: 2px 0;
                background: rgba(255, 255, 255, 0.045);
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.07);
            }

            .studio-coll-rowhead {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                padding: 7px 8px 7px 10px !important;
                border-radius: 10px;
                text-align: left;
                transition: background-color 120ms ease;
            }

            .studio-coll-row:not(.is-open) .studio-coll-rowhead:hover { background: rgba(255, 255, 255, 0.06); }

            .studio-coll-rowtext { display: flex; flex: 1; min-width: 0; flex-direction: column; }

            .studio-coll-rowtitle,
            .studio-coll-rowhint {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .studio-coll-rowtitle { font-weight: 500; }
            .studio-coll-rowhint { font-size: 11.5px; color: rgba(255, 255, 255, 0.42); }

            .studio-coll-chevron {
                display: flex;
                color: rgba(255, 255, 255, 0.3);
                transition: transform 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
            }

            .studio-coll-row.is-open .studio-coll-chevron { transform: rotate(90deg); }

            .studio-coll-form { display: flex; flex-direction: column; gap: 9px; padding: 2px 10px 10px; }

            .studio-coll-field { display: flex; flex-direction: column; gap: 4px; margin: 0; }

            .studio-coll-field.is-inline { flex-direction: row; align-items: center; justify-content: space-between; }

            .studio-coll-label { font-size: 11px; font-weight: 500; color: rgba(255, 255, 255, 0.5); }

            .studio-coll input[type="text"],
            .studio-coll input[type="number"],
            .studio-coll select,
            .studio-coll textarea {
                width: 100%;
                margin: 0;
                padding: 6px 9px;
                border-radius: 8px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                background: rgba(255, 255, 255, 0.05);
                color: #fff;
                font: inherit;
                line-height: 1.45;
                outline: none;
                box-shadow: none;
                transition: border-color 120ms ease, box-shadow 120ms ease;
            }

            .studio-coll textarea { min-height: 64px; resize: vertical; }

            .studio-coll input:focus,
            .studio-coll select:focus,
            .studio-coll textarea:focus {
                border-color: #5b6bff;
                box-shadow: 0 0 0 3px rgba(64, 83, 255, 0.28);
            }

            .studio-coll-switch { width: 16px; height: 16px; accent-color: #4053ff; }

            .studio-coll-tools { display: flex; align-items: center; gap: 2px; margin: 2px -4px -4px; }

            .studio-coll-spacer { flex: 1; }

            .studio-coll-tool {
                display: flex;
                align-items: center;
                gap: 5px;
                height: 26px;
                padding: 0 8px !important;
                border-radius: 7px;
                font-size: 11.5px !important;
                color: rgba(255, 255, 255, 0.55) !important;
                transition: background-color 120ms ease, color 120ms ease;
            }

            .studio-coll-tool svg { width: 12px; height: 12px; }
            .studio-coll-tool:hover:not(:disabled) { background: rgba(255, 255, 255, 0.08); color: #fff !important; }
            .studio-coll-tool:disabled { opacity: 0.3; cursor: default; }
            .studio-coll-tool.is-danger:hover:not(:disabled) { background: rgba(248, 113, 113, 0.14); color: #fca5a5 !important; }

            .studio-coll-foot {
                padding: 8px 12px 9px;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                font-size: 11px;
                color: rgba(255, 255, 255, 0.38);
            }

            html.studio-preview .studio-coll,
            html.studio-preview .studio-coll-ring,
            html.studio-element-select .studio-coll,
            html.studio-element-select .studio-coll-ring { display: none !important; }

            @media (prefers-reduced-motion: reduce) {
                .studio-coll.is-on { animation: none; }
            }

            /* ---- Repeater item controls ----
               Flanking + buttons (add before/after) and a small toolbar
               (drag + delete) for the item tier — orange like the item
               halo/chip/cursor. Own elements, own pointer-events: the chip
               above is pointer-events:none by design, so an interactive
               control can't live inside it. Positioned from JS
               (paintItemControls) through the same rAF write phase as the
               halo/chip. */
            .studio-item-flank {
                position: fixed;
                z-index: 2147483007;
                display: none;
                align-items: center;
                justify-content: center;
                width: 22px;
                height: 22px;
                margin: -11px 0 0 -11px;
                border-radius: 999px;
                background: #e08c2e;
                color: #fff;
                border: 2px solid rgba(255, 255, 255, 0.9);
                box-shadow: 0 3px 10px -2px rgba(0, 0, 0, 0.45);
                cursor: pointer;
            }

            .studio-item-flank.is-on { display: flex; }

            .studio-item-flank button {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
                padding: 0;
                border: 0;
                background: none;
                color: inherit;
                cursor: pointer;
            }

            .studio-item-flank svg {
                width: 12px;
                height: 12px;
            }

            .studio-item-toolbar {
                position: fixed;
                z-index: 2147483007;
                display: none;
                align-items: center;
                gap: 2px;
                padding: 3px;
                margin: 0 0 0 6px;
                border-radius: 9px;
                background: rgba(12, 12, 14, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.45);
            }

            .studio-item-toolbar.is-on { display: flex; }

            .studio-item-toolbar button {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                border: 0;
                border-radius: 6px;
                background: transparent;
                color: rgba(255, 255, 255, 0.7);
                cursor: pointer;
                transition: background 100ms ease, color 100ms ease;
                padding: 0;
            }

            .studio-item-toolbar button:hover {
                background: rgba(255, 255, 255, 0.14);
                color: #fff;
            }

            .studio-item-toolbar .studio-item-drag {
                cursor: grab;
            }

            .studio-item-toolbar .studio-item-drag:active {
                cursor: grabbing;
            }

            .studio-item-toolbar .studio-item-remove:hover {
                background: rgba(243, 114, 114, 0.2);
                color: #f37272;
            }

            .studio-item-toolbar svg {
                width: 13px;
                height: 13px;
            }

            /* Preview mode owns the canvas — no item chrome either */
            html.studio-preview .studio-item-flank,
            html.studio-preview .studio-item-toolbar {
                display: none !important;
            }

            /* Toasts raised on the canvas (a toggle switched off, a row
               deleted from the collection card) are handed to the editor
               window by studio.js's toast(), so they join its one stack —
               there is nothing to style here. */

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

            /* ---- Section toolbar ----
               One dark glass bar at the top-right of a hovered or selected
               section: ··· (more), ↑ ↓ (only where there is somewhere to
               go), Ask AI (developer mode), Edit — the one filled button,
               in the section's scope colour. Always wins pointer events
               over insert zones. */
            .studio-toolbar {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 2147483005;
                display: flex;
                align-items: center;
                gap: 2px;
                height: 32px;
                padding: 3px;
                border-radius: 9px;
                background: rgba(17, 17, 19, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2), 0 12px 32px -12px rgba(0, 0, 0, 0.55);
                backdrop-filter: blur(12px) saturate(1.3);
                -webkit-backdrop-filter: blur(12px) saturate(1.3);
                font-family: Geist, ui-sans-serif, system-ui, -apple-system, sans-serif;
                opacity: 0;
                transform: translateY(-3px);
                transition: opacity 130ms ease, transform 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
                pointer-events: none;
            }

            .studio-section:hover .studio-toolbar,
            .studio-section.is-selected .studio-toolbar {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }

            .studio-tb-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                height: 24px;
                min-width: 24px;
                padding: 0 6px;
                border: 0;
                border-radius: 6px;
                background: transparent;
                color: rgba(255, 255, 255, 0.72);
                font: inherit;
                font-size: 12px;
                font-weight: 500;
                letter-spacing: -0.005em;
                line-height: 1;
                white-space: nowrap;
                cursor: pointer;
                transition: background-color 100ms ease, color 100ms ease, transform 100ms ease;
            }

            .studio-tb-btn:hover {
                background: rgba(255, 255, 255, 0.1);
                color: #fff;
            }

            .studio-tb-btn:active {
                transform: scale(0.96);
            }

            .studio-tb-btn svg {
                width: 14px;
                height: 14px;
                flex: none;
            }

            .studio-tb-btn.is-more.is-open {
                background: rgba(255, 255, 255, 0.12);
                color: #fff;
            }

            /* Ask AI — violet, the Assistant's colour everywhere in Studio */
            .studio-tb-btn.is-ai {
                padding: 0 9px 0 7px;
                color: #c4b5fd;
            }

            .studio-tb-btn.is-ai:hover,
            .studio-tb-btn.is-ai.is-open {
                background: rgba(139, 92, 246, 0.24);
                color: #ede9fe;
            }

            /* It opens a menu: Ask, Improve, Variations */
            .studio-tb-btn.is-ai {
                padding-right: 5px;
            }

            .studio-tb-btn .studio-tb-caret {
                width: 12px;
                height: 12px;
                margin-left: -2px;
                opacity: 0.7;
            }

            /* Edit — filled, in the section's scope colour */
            .studio-tb-btn.is-edit {
                padding: 0 10px 0 8px;
                background: #4c7dfa;
                color: #fff;
                font-weight: 600;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
            }

            .studio-tb-btn.is-edit:hover {
                background: #3d6cea;
            }

            .studio-section.is-layout .studio-tb-btn.is-edit { background: #8b5cf6; }
            .studio-section.is-layout .studio-tb-btn.is-edit:hover { background: #7c4ddf; }
            .studio-section.is-block .studio-tb-btn.is-edit { background: #14b8a6; }
            .studio-section.is-block .studio-tb-btn.is-edit:hover { background: #0f9b8e; }

            /* A short section (a nav bar) keeps its chrome on itself, centred
               on its own height — hung below, it sat on the next section and
               read as that section's. `is-short` + --studio-h are set from JS
               on hover. */
            .studio-section.is-short .studio-toolbar {
                top: max(0px, calc((var(--studio-h, 60px) - 32px) / 2));
            }

            .studio-section.is-short .studio-chip,
            .studio-section.is-short .studio-hidden-badge {
                top: max(0px, calc((var(--studio-h, 60px) - 24px) / 2));
            }

            .studio-tb-btn.is-done { display: none; }
            .studio-section.is-editing .studio-tb-btn.is-done { display: flex; }
            .studio-section.is-editing .studio-tb-btn.is-edit:not(.is-done) { display: none; }

            .studio-tb-sep {
                width: 1px;
                height: 14px;
                margin: 0 3px;
                background: rgba(255, 255, 255, 0.12);
                flex: none;
            }

            /* Dev-mode-only members of the bar hide as a unit */
            .studio-tb-sep.studio-devmode-only { display: none !important; }
            html.studio-devmode .studio-tb-sep.studio-devmode-only { display: block !important; }

            /* ---- Add section ----
               A pill on the bottom edge of the hovered (or selected)
               section, and on the top edge of the first. At rest the zone
               takes no pointer events, so hovering the seam from the next
               section down is honest about which section is hovered. */
            .studio-insert {
                position: absolute;
                left: 0;
                right: 0;
                height: 32px;
                z-index: 2147483004;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                opacity: 0;
                pointer-events: none;
                transition: opacity 130ms ease;
            }

            .studio-insert--bottom { bottom: -16px; }

            .studio-section:hover .studio-insert--bottom,
            .studio-section.is-selected .studio-insert--bottom {
                opacity: 1;
                pointer-events: auto;
            }

            /* Only the first section has a top pill. It is a quiet band on the
               page's top edge that shows its pill when the pointer reaches
               it — a short first section (a nav) would otherwise wear a
               chip, a toolbar and two pills at once. */
            .studio-insert--top {
                top: 0;
                height: 22px;
                align-items: flex-start;
                padding-top: 8px;
                pointer-events: auto;
            }

            .studio-insert--top::before {
                display: none;
            }

            .studio-insert--top:hover {
                opacity: 1;
            }

            /* The seam itself: a hairline in the scope colour */
            .studio-insert::before {
                content: '';
                position: absolute;
                left: 0;
                right: 0;
                top: 50%;
                height: 2px;
                margin-top: -1px;
                background: #4c7dfa;
                opacity: 0.9;
            }

            .studio-insert button {
                position: relative;
                display: flex;
                align-items: center;
                gap: 5px;
                height: 26px;
                padding: 0 11px 0 9px;
                border: 0;
                border-radius: 999px;
                background: #4c7dfa;
                color: #fff;
                font-family: Geist, ui-sans-serif, system-ui, -apple-system, sans-serif;
                font-size: 11.5px;
                font-weight: 600;
                letter-spacing: -0.005em;
                cursor: pointer;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18), 0 6px 18px -6px rgba(76, 125, 250, 0.7);
                transition: transform 120ms cubic-bezier(0.2, 0.8, 0.2, 1), background 120ms ease;
            }

            .studio-insert button:hover {
                background: #3d6cea;
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
            }

            .studio-insert button.studio-add-layout {
                background: #8b5cf6;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18), 0 6px 18px -6px rgba(139, 92, 246, 0.7);
            }

            .studio-insert button.studio-add-layout:hover {
                background: #7c4ddf;
            }

            /* Boundary between the layout and the page — offers both targets */
            .studio-insert--boundary::before {
                background: linear-gradient(90deg, #8b5cf6, #4c7dfa);
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

            .studio-menu-item .studio-menu-hint {
                margin-left: auto;
                padding-left: 14px;
                font-size: 11px;
                font-weight: 400;
                color: rgba(255, 255, 255, 0.36);
            }

            .studio-menu-item .studio-menu-hint + .studio-menu-kbd {
                margin-left: 0;
                padding-left: 8px;
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
                top: 10px;
                left: 10px;
                height: 24px;
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
            collectionsUrl: @js(route('studio.api.collections.show', ['name' => '__NAME__'])),
            csrf: @js(csrf_token()),
            paths: @js($componentPaths),
            contracts: @js($componentContracts),
            // The Ask AI menu offers Variations where a section can be drawn and redrawn
            designVariations: @js(\Designer\Studio\Support\DevMode::enabled() && app(\Designer\Studio\Services\Assistant\ImageVariations::class)->sectionsAvailable()),
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

                @php
                    $next = $sections[$loop->index + 1] ?? null;
                    $isHeader = $isLayout && $section['docIndex'] < $layoutBeforeCount;
                    $nextIsPage = $next && $next['scope'] === 'page';
                    $nextIsFooter = $next && $next['scope'] === 'layout' && $next['docIndex'] > $layoutBeforeCount;
                    $scopeLabel = fn ($scope, $docIndex) => $scope === 'layout' ? $layoutLabel($docIndex) : 'Add section';
                @endphp

                {{-- Add above — the first section only; every other seam is the section above's bottom pill --}}
                @if($loop->first)
                    <div class="studio-insert studio-insert--top {{ $isLayout ? 'studio-insert--layout' : '' }}">
                        <button type="button" class="{{ $isLayout ? 'studio-add-layout' : '' }}" onclick="Studio.preview.addAt('{{ $section['scope'] }}', {{ $section['docIndex'] }}, event)">
                            {!! $plus !!} {{ $scopeLabel($section['scope'], $section['docIndex']) }}
                        </button>
                    </div>
                @endif

                {{-- Section chrome — chip, state badge and toolbar, in one sticky rail --}}
                <div class="studio-rail">

                    @if($section['hidden'])
                        <span class="studio-hidden-badge">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                            Hidden
                        </span>
                    @else
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
                    @endif

                    {{-- Toolbar: ··· · ↑ ↓ · Ask AI (a menu: Ask, Improve, Variations) · Edit --}}
                    <div class="studio-toolbar" onclick="event.stopPropagation()">
                        <button type="button" class="studio-tb-btn is-more" onclick="Studio.preview.moreMenu('{{ $section['id'] }}', event)" title="More" aria-label="More actions" aria-haspopup="menu">
                            <svg viewBox="0 0 20 20" fill="currentColor"><circle cx="4.5" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="15.5" cy="10" r="1.6"/></svg>
                        </button>
                        @if(!$section['docFirst'] || !$section['docLast'])
                            <span class="studio-tb-sep is-move"></span>
                            @unless($section['docFirst'])
                                <button type="button" class="studio-tb-btn is-move" onclick="Studio.preview.action('{{ $section['id'] }}', 'move-up', event)" title="Move up (⌘↑)" aria-label="Move up">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15.5v-11M5.5 9 10 4.5 14.5 9"/></svg>
                                </button>
                            @endunless
                            @unless($section['docLast'])
                                <button type="button" class="studio-tb-btn is-move" onclick="Studio.preview.action('{{ $section['id'] }}', 'move-down', event)" title="Move down (⌘↓)" aria-label="Move down">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4.5v11M5.5 11l4.5 4.5 4.5-4.5"/></svg>
                                </button>
                            @endunless
                        @endif
                        @if(\Designer\Studio\Support\DevMode::enabled())
                            <span class="studio-tb-sep studio-devmode-only"></span>
                            <button type="button" class="studio-tb-btn is-ai studio-devmode-only" onclick="Studio.preview.aiMenu('{{ $section['id'] }}', event)" title="Ask the Assistant about this section (A)" aria-haspopup="menu">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
                                Ask AI
                                <svg class="studio-tb-caret" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.72a.75.75 0 0 1 1.06 0L10 11.44l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.78a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </button>
                        @endif
                        <span class="studio-tb-sep"></span>
                        <button type="button" class="studio-tb-btn is-edit" onclick="Studio.preview.openInspector('{{ $section['id'] }}', event)" title="Edit this section (E)">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 6h9M15 6h2M3 14h2M8 14h9"/><circle cx="13" cy="6" r="2"/><circle cx="6" cy="14" r="2"/></svg>
                            Edit
                        </button>
                        {{-- While this section is being edited, Edit reads Done --}}
                        <button type="button" class="studio-tb-btn is-edit is-done" onclick="Studio.preview.exitFocus(event)" title="Done editing (Esc)">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 10.5 3.5 3.5 7.5-8"/></svg>
                            Done
                        </button>
                    </div>

                </div>{{-- /studio-rail --}}

                {{-- Rendered section --}}
                <div data-section-content>{!! $rendered !!}</div>

                {{-- Add below — this section's own seam; a layout/page boundary offers both targets --}}
                @if($isHeader && $nextIsPage)
                    <div class="studio-insert studio-insert--bottom studio-insert--boundary">
                        <button type="button" class="studio-add-layout" onclick="Studio.preview.addAt('layout', {{ $layoutBeforeCount }}, event)">
                            {!! $plus !!} Add to header
                        </button>
                        <button type="button" onclick="Studio.preview.addAt('page', 0, event)">
                            {!! $plus !!} Add section
                        </button>
                    </div>
                @elseif($section['scope'] === 'page' && $nextIsFooter)
                    <div class="studio-insert studio-insert--bottom studio-insert--boundary">
                        <button type="button" onclick="Studio.preview.addAt('page', {{ $pageSectionCount }}, event)">
                            {!! $plus !!} Add section
                        </button>
                        <button type="button" class="studio-add-layout" onclick="Studio.preview.addAt('layout', {{ $next['docIndex'] }}, event)">
                            {!! $plus !!} Add to footer
                        </button>
                    </div>
                @else
                    <div class="studio-insert studio-insert--bottom {{ $isLayout ? 'studio-insert--layout' : '' }}">
                        <button type="button" class="{{ $isLayout ? 'studio-add-layout' : '' }}" onclick="Studio.preview.addAt('{{ $section['scope'] }}', {{ $section['docIndex'] + 1 }}, event)">
                            {!! $plus !!} {{ $scopeLabel($section['scope'], $section['docIndex'] + 1) }}
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
    <div class="studio-cursor" id="studio-cursor"><span class="studio-cursor-shape"></span></div>
    <div class="studio-control" id="studio-control"></div>
    {{-- The collection card + the ring that keeps its list outlined while
         it is open. Document coordinates: they scroll with the page. --}}
    <div class="studio-coll-ring" id="studio-collection-ring"></div>
    <div class="studio-coll" id="studio-collection" role="dialog" aria-label="Collection rows"></div>

    {{-- Repeater item controls — positioned from JS (paintItemControls),
         same rAF write phase as the halo/chip above. Their own visibility
         is independent of the halo's (see resolveHover()'s isOverItemControls
         guard), so hovering these buttons never hides them. --}}
    <div class="studio-item-flank studio-item-flank--before" id="studio-item-before">
        <button type="button" onclick="Studio.preview.itemAction('add-before', event)" title="Add item before">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        </button>
    </div>
    <div class="studio-item-flank studio-item-flank--after" id="studio-item-after">
        <button type="button" onclick="Studio.preview.itemAction('add-after', event)" title="Add item after">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        </button>
    </div>
    <div class="studio-item-toolbar" id="studio-item-toolbar" onclick="event.stopPropagation()">
        <button type="button" class="studio-item-drag" onmousedown="Studio.preview.startItemDrag(event)" title="Drag to reorder">
            <svg viewBox="0 0 20 20" fill="currentColor"><circle cx="7" cy="5" r="1.4"/><circle cx="13" cy="5" r="1.4"/><circle cx="7" cy="10" r="1.4"/><circle cx="13" cy="10" r="1.4"/><circle cx="7" cy="15" r="1.4"/><circle cx="13" cy="15" r="1.4"/></svg>
        </button>
        <button type="button" class="studio-item-remove" onclick="Studio.preview.itemAction('remove', event)" title="Delete item">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
    </div>
</x-studio::layouts.iframe>
