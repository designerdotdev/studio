import * as esbuild from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Builds the slim Monaco assets into dist/ as flat, stably-named files
 * (the asset route only allows [a-zA-Z0-9._-]+ — no subdirectories).
 * Runs after `vite build`; Vite has emptyOutDir off so these survive.
 * Adapted from the DevDojo components repo's esbuild.config.js.
 */
const packageDir = import.meta.dirname;
const outDir = path.join(packageDir, 'dist');

export const MONACO_FILES = [
    'studio-monaco.js',
    'studio-monaco.css',
    'monaco-editor-worker.js',
    'monaco-html-worker.js',
    'monaco-css-worker.js',
    'monaco-json-worker.js',
    'codicon.ttf',
];

await esbuild.build({
    entryPoints: {
        'studio-monaco': 'resources/js/monaco/studio-monaco.js',
        'monaco-editor-worker': 'resources/js/monaco/editor-worker.js',
        'monaco-html-worker': 'resources/js/monaco/html-worker.js',
        'monaco-css-worker': 'resources/js/monaco/css-worker.js',
        'monaco-json-worker': 'resources/js/monaco/json-worker.js',
    },
    outdir: outDir,
    bundle: true,
    format: 'iife',
    minify: true,
    sourcemap: false,
    platform: 'browser',
    loader: { '.ttf': 'file' },
    // Stable name (codicon.ttf, no hash): the CSS references it
    // relatively and AssetController allowlists exact filenames.
    assetNames: '[name]',
});

// Mirror vite.config.js's publishToHost(): keep hosts that serve
// published assets in sync. Same target resolution, same opt-in.
const target = process.env.STUDIO_PUBLISH_DIR
    || path.resolve(packageDir, '../../../public/vendor/studio');

if (process.env.STUDIO_PUBLISH_DIR || fs.existsSync(target)) {
    fs.mkdirSync(target, { recursive: true });
    for (const file of MONACO_FILES) {
        const source = path.join(outDir, file);
        if (fs.existsSync(source)) {
            fs.copyFileSync(source, path.join(target, file));
        }
    }
    console.log(`  studio → published Monaco assets to ${target}`);
}

console.log(`✓ Built Monaco assets to ${outDir}`);
