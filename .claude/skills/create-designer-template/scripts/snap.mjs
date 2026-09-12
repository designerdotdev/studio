#!/usr/bin/env node
/*
    snap.mjs — screenshot every page of a running site at the widths the
    taste pass needs, after scrolling through so reveals have fired.

        NODE_PATH=<dir-with-node_modules> node snap.mjs <base-url> <out-dir> [options]

    Options
      --widths 390,768,1024,1280,1440   (default)
      --pages /,/pricing                (default: every <loc> in /sitemap.xml, else /)
      --reduced-motion                  emulate prefers-reduced-motion: reduce
      --thumbnail                       only a 1440x900 shot of the first page at scroll 0 → <out-dir>/thumbnail.png
      --chunk 1800                      viewport height per shot (the page is scrolled and shot in steps)
      --settle 800                      ms to wait after the scroll-through, before shooting (raise to ~6000 for a hero beat that resolves)

    Needs `npm i playwright` somewhere on NODE_PATH; chromium is provisioned on this Mac.
    Prints one line per shot and a WARNING when a page scrolls horizontally.
*/
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let chromium;
try {
    ({ chromium } = require('playwright'));
} catch {
    console.error('playwright not found — run `npm i playwright` in the scratchpad and set NODE_PATH=<scratchpad>/node_modules');
    process.exit(2);
}

const args = process.argv.slice(2);
const base = (args[0] || '').replace(/\/$/, '');
const out = args[1];
if (!base || !out) {
    console.error('usage: snap.mjs <base-url> <out-dir> [--widths a,b] [--pages /,/x] [--reduced-motion] [--thumbnail] [--chunk N]');
    process.exit(2);
}
const opt = (name, def) => {
    const i = args.indexOf(name);
    return i > -1 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : def;
};
const widths = opt('--widths', '390,768,1024,1280,1440').split(',').map(Number);
const reduced = args.includes('--reduced-motion');
const thumbnail = args.includes('--thumbnail');
const chunk = Number(opt('--chunk', '1800'));
const settle = Number(opt('--settle', '800'));

fs.mkdirSync(out, { recursive: true });

async function discoverPages() {
    const explicit = opt('--pages', null);
    if (explicit) return explicit.split(',');
    try {
        const res = await fetch(base + '/sitemap.xml');
        if (res.ok) {
            const xml = await res.text();
            const locs = [...xml.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/g)].map((m) => m[1].trim());
            const paths = locs.map((u) => {
                try { return new URL(u).pathname; } catch { return u; }
            });
            if (paths.length) return [...new Set(paths)];
        }
    } catch {}
    return ['/'];
}

const slug = (p) => (p === '/' ? 'index' : p.replace(/^\//, '').replace(/\//g, '__').replace(/[^\w-]/g, '_'));

const browser = await chromium.launch();
const pages = await discoverPages();

if (thumbnail) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    await page.goto(base + pages[0], { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    const file = path.join(out, 'thumbnail.png');
    await page.screenshot({ path: file });
    console.log('thumbnail', file);
    await browser.close();
    process.exit(0);
}

let warnings = 0;
for (const width of widths) {
    const ctx = await browser.newContext({
        viewport: { width, height: 900 },
        deviceScaleFactor: 1,
        reducedMotion: reduced ? 'reduce' : 'no-preference',
        isMobile: width < 768,
        hasTouch: width < 768,
    });
    for (const p of pages) {
        const page = await ctx.newPage();
        try {
            await page.goto(base + p, { waitUntil: 'networkidle' });
        } catch (e) {
            console.log('FAIL', p, width, e.message);
            warnings++;
            await page.close();
            continue;
        }
        // Scroll through so IntersectionObserver / ScrollTrigger reveals fire.
        const height = await page.evaluate(async (wait) => {
            const step = Math.max(300, Math.floor(window.innerHeight * 0.8));
            for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
                window.scrollTo(0, y);
                await new Promise((r) => setTimeout(r, 90));
            }
            window.scrollTo(0, 0);
            await new Promise((r) => setTimeout(r, wait));
            return document.documentElement.scrollHeight;
        }, settle);
        // A revealed element that keeps `filter: blur(0)` holds a compositing layer that
        // Chromium's tall full-page capture can drop; blur(0) and none look identical.
        await page.addStyleTag({ content: '[data-reveal].is-visible { filter: none !important; }' });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 1) {
            console.log(`WARNING ${p} @${width}: horizontal overflow of ${overflow}px`);
            warnings++;
        }
        // Scroll-and-shoot rather than a full-page clip: Chromium's full-page capture
        // drops composited layers (transitions, filters) on very tall pages.
        // Smooth scrolling (html.scroll-smooth) would leave the capture mid-scroll.
        await page.addStyleTag({ content: '[data-reveal].is-visible { transition: none !important; } html { scroll-behavior: auto !important; }' });
        await page.setViewportSize({ width, height: chunk });
        let n = 0;
        for (let y = 0; y < height; y += chunk, n++) {
            await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
            await page.waitForTimeout(300);
            const file = path.join(out, `${slug(p)}-${width}${reduced ? '-rm' : ''}-${String(n).padStart(2, '0')}.png`);
            await page.screenshot({ path: file });
            console.log('shot', file);
        }
        await page.setViewportSize({ width, height: 900 });
        await page.close();
    }
    await ctx.close();
}
await browser.close();
console.log(`${warnings} warning(s)`);
process.exit(warnings ? 1 : 0);
