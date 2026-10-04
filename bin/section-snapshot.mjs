#!/usr/bin/env node
// Draws one section to a PNG for SectionSnapshot (Services/Assistant).
//
//   node section-snapshot.mjs <document.html> <origin> <output.png> [width]
//
// Run from the app's root: Playwright is the app's dependency, resolved from
// its node_modules. The document is served to the browser as if it lived at
// <origin>, so its styles, fonts and images load from the running site.
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';

const [input, origin, output, width = '1440'] = process.argv.slice(2);

if (!input || !origin || !output) {
    console.error('usage: section-snapshot.mjs <document.html> <origin> <output.png> [width]');
    process.exit(2);
}

const { chromium } = createRequire(join(process.cwd(), 'package.json'))('playwright');
const url = `${origin}/__studio-section-snapshot`;
// A document handed to the browser has no address of its own, so Chromium
// treats it as public and refuses its requests to a local site without this
const browser = await chromium.launch({
    args: ['--disable-features=LocalNetworkAccessChecks,BlockInsecurePrivateNetworkRequests,PrivateNetworkAccessRespectPreflightResults'],
});

try {
    const context = await browser.newContext({
        viewport: { width: Number(width), height: 900 },
        deviceScaleFactor: 1,
        ignoreHTTPSErrors: true,
        reducedMotion: 'reduce',
    });
    const page = await context.newPage();

    await page.route(url, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: readFileSync(input, 'utf8') }));
    await page.goto(url, { waitUntil: 'load', timeout: 30000 });
    // The stylesheet is compiled in the page, and images arrive after it
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await page.evaluate(() => document.fonts.ready);

    // The thumbnail document clips to one screen and centres a short section; a picture wants the whole section
    await page.addStyleTag({ content: 'html, body { overflow: visible !important; min-height: 0 !important; } body { display: block !important; }' });
    await page.waitForTimeout(400);

    const section = page.locator('.studio-preview-fit');
    const box = await section.boundingBox();

    if (!box || box.height < 8) {
        console.error('EMPTY');
        process.exit(3);
    }

    await section.screenshot({ path: output, animations: 'disabled' });
} finally {
    await browser.close();
}
