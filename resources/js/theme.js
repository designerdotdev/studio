/*
    The Theme panel's vocabulary: palettes, accents, corners and type pairings,
    and what a choice of them comes to as values for the Designer global
    variables. Pure — no document, no storage. The editor's panel
    (partials/theme-panel) applies the result to the canvas and saves it;
    Support\SiteTheme writes it into the site's stylesheet on publish.

    The same lists drive the template viewer's Theme panel in the Designer app
    (template-gallery/show.blade.php); keep the two in step.
*/

// A palette is the full set of Designer global variables. `own` is whatever
// the template ships; the rest are schemes a site could adopt as they are.
const light = (o) => ({ scheme: 'light', destructive: '#e7000b', ...o });
const dark = (o) => ({ scheme: 'dark', destructive: '#ff6b6b', ...o });
const PALETTES = [
    { id: 'own', name: 'Template' },
    { id: 'paper', name: 'Paper', ...light({ background: '#f7f6f3', foreground: '#141310', card: '#ffffff', muted: '#eeece7', mutedForeground: '#6b6a64', border: 'rgba(20, 19, 16, 0.10)', accent: '#1d4ed8', accentForeground: '#f7f6f3' }) },
    { id: 'snow', name: 'Snow', ...light({ background: '#f8f9fb', foreground: '#0f172a', card: '#ffffff', muted: '#eef1f5', mutedForeground: '#64748b', border: 'rgba(15, 23, 42, 0.09)', accent: '#2563eb', accentForeground: '#ffffff' }) },
    { id: 'sand', name: 'Sand', ...light({ background: '#f3ede3', foreground: '#2a2118', card: '#fbf8f2', muted: '#e9e0d2', mutedForeground: '#7a6a58', border: 'rgba(42, 33, 24, 0.12)', accent: '#b4532a', accentForeground: '#fbf8f2' }) },
    { id: 'mist', name: 'Mist', ...light({ background: '#f2f5f1', foreground: '#1b241d', card: '#fbfcfa', muted: '#e4eae2', mutedForeground: '#64716a', border: 'rgba(27, 36, 29, 0.10)', accent: '#2f6f5f', accentForeground: '#fbfcfa' }) },
    { id: 'midnight', name: 'Midnight', ...dark({ background: '#0b1220', foreground: '#e6ebf5', card: '#121b2c', muted: '#1a2438', mutedForeground: '#8b97ad', border: 'rgba(255, 255, 255, 0.10)', accent: '#6b8cff', accentForeground: '#0b1220' }) },
    { id: 'graphite', name: 'Graphite', ...dark({ background: '#0a0a0a', foreground: '#f4f4f5', card: '#141414', muted: '#1f1f1f', mutedForeground: '#9a9aa3', border: 'rgba(255, 255, 255, 0.10)', accent: '#a48cff', accentForeground: '#0a0a0a' }) },
    { id: 'forest', name: 'Forest', ...dark({ background: '#0c1410', foreground: '#e9f0ea', card: '#13201a', muted: '#1a2a22', mutedForeground: '#8fa397', border: 'rgba(255, 255, 255, 0.10)', accent: '#7bd389', accentForeground: '#0c1410' }) },
    { id: 'plum', name: 'Plum', ...dark({ background: '#14101a', foreground: '#f1ebf7', card: '#1d1726', muted: '#271f33', mutedForeground: '#a394b3', border: 'rgba(255, 255, 255, 0.10)', accent: '#d9a5ff', accentForeground: '#14101a' }) },
    { id: 'ember', name: 'Ember', ...dark({ background: '#140e0c', foreground: '#f6ede8', card: '#1e1512', muted: '#2a1d18', mutedForeground: '#b09a90', border: 'rgba(255, 255, 255, 0.10)', accent: '#ff8a5b', accentForeground: '#140e0c' }) },
];
const ACCENTS = [
    { id: 'own', name: 'Template' },
    { id: 'ink', name: 'Ink', ink: true },
    { id: 'blue', name: 'Blue', value: '#2563eb' },
    { id: 'violet', name: 'Violet', value: '#7c3aed' },
    { id: 'emerald', name: 'Emerald', value: '#059669' },
    { id: 'amber', name: 'Amber', value: '#d97706' },
    { id: 'orange', name: 'Orange', value: '#ea580c' },
    { id: 'rose', name: 'Rose', value: '#e11d48' },
    { id: 'cyan', name: 'Cyan', value: '#0891b2' },
];
const RADII = [
    { id: 'own', name: 'Template' },
    // r / medium / large drive the rounded-* scale (cards, panels, frames); control is every text button
    { id: 'sharp', name: 'Sharp', r: 0, medium: 0, large: 0, control: '0px' },
    { id: 'soft', name: 'Soft', r: 0.375, medium: 0.625, large: 1, control: '0.375rem' },
    { id: 'round', name: 'Round', r: 0.75, medium: 1, large: 1.5, control: '0.75rem' },
    { id: 'pill', name: 'Pill', r: 1.25, medium: 1.75, large: 2.5, control: '9999px' },
];
const TYPES = [
    { id: 'own', name: 'Template', sample: 'Aa' },
    { id: 'inter', name: 'Inter', display: "'Inter', ui-sans-serif, system-ui, sans-serif", sans: "'Inter', ui-sans-serif, system-ui, sans-serif", load: 'Inter:wght@400;500;600;700', css: "'Inter'" },
    { id: 'geist', name: 'Geist', display: "'Geist', ui-sans-serif, system-ui, sans-serif", sans: "'Geist', ui-sans-serif, system-ui, sans-serif", load: 'Geist:wght@400;500;600;700', css: "'Geist'" },
    { id: 'manrope', name: 'Manrope', display: "'Manrope', ui-sans-serif, system-ui, sans-serif", sans: "'Manrope', ui-sans-serif, system-ui, sans-serif", load: 'Manrope:wght@400;500;600;700;800', css: "'Manrope'" },
    { id: 'grotesk', name: 'Space Grotesk', display: "'Space Grotesk', ui-sans-serif, system-ui, sans-serif", sans: "'Inter', ui-sans-serif, system-ui, sans-serif", load: 'Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600', css: "'Space Grotesk'", who: '+ Inter' },
    { id: 'instrument', name: 'Instrument Serif', display: "'Instrument Serif', ui-serif, Georgia, serif", sans: "'Inter', ui-sans-serif, system-ui, sans-serif", load: 'Instrument+Serif&family=Inter:wght@400;500;600', css: "'Instrument Serif'", who: '+ Inter', serif: true },
    { id: 'fraunces', name: 'Fraunces', display: "'Fraunces', ui-serif, Georgia, serif", sans: "'DM Sans', ui-sans-serif, system-ui, sans-serif", load: 'Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=DM+Sans:wght@400;500;600', css: "'Fraunces'", who: '+ DM Sans', serif: true },
    { id: 'newsreader', name: 'Newsreader', display: "'Newsreader', ui-serif, Georgia, serif", sans: "'Inter', ui-sans-serif, system-ui, sans-serif", load: 'Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&family=Inter:wght@400;500;600', css: "'Newsreader'", who: '+ Inter', serif: true },
    { id: 'jakarta', name: 'Plus Jakarta Sans', display: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif", sans: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif", load: 'Plus+Jakarta+Sans:wght@400;500;600;700;800', css: "'Plus Jakarta Sans'" },
    { id: 'dmsans', name: 'DM Sans', display: "'DM Sans', ui-sans-serif, system-ui, sans-serif", sans: "'DM Sans', ui-sans-serif, system-ui, sans-serif", load: 'DM+Sans:wght@400;500;600;700', css: "'DM Sans'" },
];


const find = (list, id) => list.find((x) => x.id === id) || list[0];

// Relative luminance of a colour string (hex or rgb[a]) → which foreground reads on it
const luminance = (c) => {
    let r = 0, g = 0, b = 0;
    const hex = c.match(/^#([0-9a-f]{3,8})$/i);
    const rgb = c.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i);
    if (hex) {
        let h = hex[1]; if (h.length < 6) h = [...h].map((x) => x + x).join('');
        [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16) / 255);
    } else if (rgb) {
        [r, g, b] = [rgb[1], rgb[2], rgb[3]].map((x) => x / 255);
    }
    const lin = (x) => (x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4));
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
};
const onColor = (bg, lightFg, darkFg) => (luminance(bg) > 0.4 ? darkFg : lightFg);

/**
 * What the page should carry for a choice: `state` is { palette, accent,
 * radius, type } (ids; 'own' = the template's), `own` the template's own
 * values as read from the page (background, foreground, accent…).
 */
const resolve = (state, own) => {
    const p = find(PALETTES, state.palette);
    const a = find(ACCENTS, state.accent);
    const r = find(RADII, state.radius);
    const t = find(TYPES, state.type);
    const vars = {};
    let base = null;
    if (p.id !== 'own') {
        base = p;
        Object.assign(vars, {
            '--background': p.background, '--foreground': p.foreground,
            '--card': p.card, '--card-foreground': p.foreground, '--popover': p.card, '--popover-foreground': p.foreground,
            '--primary': p.foreground, '--primary-foreground': p.background,
            '--secondary': p.muted, '--secondary-foreground': p.foreground,
            '--muted': p.muted, '--muted-foreground': p.mutedForeground,
            '--accent': p.accent, '--accent-foreground': p.accentForeground, '--destructive': p.destructive,
            '--border': p.border, '--input': p.border, '--ring': p.accent,
            // the template tokens every template derives from these: the stronger hairline, the
            // tinted well behind accent content, and the shade band (the page's counterpart)
            '--color-border-strong': `color-mix(in srgb, ${p.foreground} 18%, transparent)`,
            '--color-accent-soft': `color-mix(in srgb, ${p.accent} 10%, transparent)`,
            '--color-shade': p.foreground, '--color-shade-foreground': p.background,
            '--color-shade-muted-foreground': `color-mix(in srgb, ${p.background} 65%, ${p.foreground})`,
            '--color-shade-border': `color-mix(in srgb, ${p.background} 12%, transparent)`,
        });
    }
    if (a.id !== 'own') {
        // The accent is the brand colour: buttons, links, the focus ring, and the tints and
        // hover shades a template mixes from it. "Ink" makes it the foreground again.
        const bg = (base || own || {}).background || '#ffffff';
        const fg = (base || own || {}).foreground || '#111111';
        const accent = a.ink ? fg : a.value;
        const onAccent = a.ink ? bg : onColor(accent, '#ffffff', '#111111');
        Object.assign(vars, {
            '--accent': accent, '--accent-foreground': onAccent, '--ring': accent,
            '--primary': accent, '--primary-foreground': onAccent,
            '--color-accent-soft': `color-mix(in srgb, ${accent} 10%, transparent)`,
            '--color-accent-deep': `color-mix(in srgb, ${accent} 82%, #000)`,
            '--color-accent-hover': `color-mix(in srgb, ${accent} 90%, #000)`,
            '--color-accent-bright': `color-mix(in srgb, ${accent} 75%, #fff)`,
            '--color-shade-accent': `color-mix(in srgb, ${accent} 70%, #fff)`,
        });
    }
    if (r.id !== 'own') {
        vars['--radius'] = r.r + 'rem'; vars['--radius-medium'] = r.medium + 'rem'; vars['--radius-large'] = r.large + 'rem';
        vars['--radius-control'] = r.control;
    }
    if (t.id !== 'own') {
        vars['--font-display'] = t.display; vars['--font-sans'] = t.sans;
    }
    return { vars, scheme: base ? base.scheme : null, font: t.id !== 'own' ? t : null, palette: p, accent: a, radius: r, type: t };
};

const ALL = ['--background', '--foreground', '--card', '--card-foreground', '--popover', '--popover-foreground', '--primary', '--primary-foreground',
             '--secondary', '--secondary-foreground', '--muted', '--muted-foreground', '--accent', '--accent-foreground', '--destructive',
             '--border', '--input', '--ring', '--radius', '--radius-medium', '--radius-large', '--radius-control', '--font-display', '--font-sans',
             '--color-border-strong', '--color-accent-soft', '--color-accent-deep', '--color-accent-hover', '--color-accent-bright', '--color-shade-accent',
             '--color-shade', '--color-shade-foreground', '--color-shade-muted-foreground', '--color-shade-border'];

export default { PALETTES, ACCENTS, RADII, TYPES, ALL, resolve };
