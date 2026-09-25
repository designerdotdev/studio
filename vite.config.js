import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';

const packageDir = import.meta.dirname;

const ASSET_FILES = ['studio.js', 'studio-css.css'];

/**
 * After each build, re-publish the compiled assets into any host app that
 * has published copies (public/vendor/studio). Keeps `vite build --watch`
 * (npm run dev) in sync with hosts that serve published assets. Set
 * STUDIO_PUBLISH_DIR to publish somewhere else; hosts without published
 * assets need nothing — they're served from the package dist/ directly.
 */
function publishToHost() {
    return {
        name: 'studio-publish-assets',
        closeBundle() {
            const target = process.env.STUDIO_PUBLISH_DIR
                || path.resolve(packageDir, '../../../public/vendor/studio');

            // Only re-publish where assets were already published once
            if (!process.env.STUDIO_PUBLISH_DIR && !fs.existsSync(target)) {
                return;
            }

            fs.mkdirSync(target, { recursive: true });

            for (const file of ASSET_FILES) {
                const source = path.resolve(packageDir, 'dist', file);
                if (fs.existsSync(source)) {
                    fs.copyFileSync(source, path.join(target, file));
                }
            }

            console.log(`  studio → published assets to ${target}`);
        },
    };
}

export default defineConfig({
    plugins: [
        tailwindcss(),
        publishToHost(),
    ],
    build: {
        outDir: 'dist',
        // esbuild.monaco.mjs also writes into dist/; all outputs have fixed
        // names, so never wipe the directory (watch mode would delete the
        // Monaco assets at startup otherwise).
        emptyOutDir: false,
        manifest: true,
        rollupOptions: {
            input: {
                'studio': 'resources/js/studio.js',
                'studio-css': 'resources/css/studio.css',
            },
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
});
