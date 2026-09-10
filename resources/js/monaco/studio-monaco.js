/**
 * Studio's slim Monaco build for the dev-mode source editor.
 *
 * Bundles the editor core plus only the languages Code mode opens: HTML,
 * CSS and JSON get worker-backed language services; YAML, PHP, Markdown and
 * JavaScript get highlighting only (YAML is validated server-side by
 * symfony/yaml on save, and a TypeScript service would dwarf the bundle).
 * Loaded lazily by Studio.codeEditor() on the first file or modal open;
 * never part of studio.js.
 *
 * Adapted from the DevDojo components package's monaco-editor.js.
 */
import * as monaco from 'monaco-editor/esm/vs/editor/edcore.main.js';
import 'monaco-editor/esm/vs/basic-languages/html/html.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/yaml/yaml.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/css/css.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/php/php.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/markdown/markdown.contribution.js';
// Highlighting only — the TypeScript language service is far too heavy for
// the handful of script snippets Code mode ever opens.
import 'monaco-editor/esm/vs/basic-languages/javascript/javascript.contribution.js';
import 'monaco-editor/esm/vs/language/html/monaco.contribution.js';
import 'monaco-editor/esm/vs/language/css/monaco.contribution.js';
import 'monaco-editor/esm/vs/language/json/monaco.contribution.js';

// vs-dark with Studio's editor-chrome tokens (see resources/css/studio.css
// @theme): shell #0b0b0d, panel #121215, ink #f4f4f6, soft #9d9da7,
// faint #61616b, accent #4c7dfa. Monaco themes need literal colors —
// CSS variables don't resolve here.
const studioDark = {
    base: 'vs-dark',
    inherit: true,
    rules: [
        { background: '0b0b0d', token: '' },
        { foreground: '61616b', token: 'comment' },
    ],
    colors: {
        'editor.foreground': '#f4f4f6',
        'editor.background': '#0b0b0d',
        'editor.selectionBackground': '#4c7dfa4d',
        'editor.lineHighlightBackground': '#ffffff0f',
        'editorCursor.foreground': '#f4f4f6',
        'editorWhitespace.foreground': '#ffffff26',
        'editorLineNumber.foreground': '#61616b',
        'editorLineNumber.activeForeground': '#9d9da7',
        'editorWidget.background': '#121215',
        'editorWidget.border': '#2a2a30',
        'editorSuggestWidget.background': '#121215',
        'editorSuggestWidget.selectedBackground': '#4c7dfa33',
        'input.background': '#0b0b0d',
        'scrollbarSlider.background': '#ffffff1a',
        'scrollbarSlider.hoverBackground': '#ffffff2a',
        'focusBorder': '#4c7dfa',
    },
};

// The light counterpart, keyed to the same tokens under html.studio-light:
// shell #ffffff, panel #fbfbfc, ink #17171a, soft #5c5c66, faint #8a8a94.
const studioLight = {
    base: 'vs',
    inherit: true,
    rules: [
        { background: 'ffffff', token: '' },
        { foreground: '8a8a94', token: 'comment' },
    ],
    colors: {
        'editor.foreground': '#17171a',
        'editor.background': '#ffffff',
        'editor.selectionBackground': '#4c7dfa26',
        'editor.lineHighlightBackground': '#11111408',
        'editorCursor.foreground': '#17171a',
        'editorLineNumber.foreground': '#b4b4bd',
        'editorLineNumber.activeForeground': '#5c5c66',
        'editorWidget.background': '#ffffff',
        'editorWidget.border': '#e6e6ea',
        'focusBorder': '#4c7dfa',
    },
};

let booted = false;

function boot(workers) {
    if (booted) return;
    booted = true;

    window.MonacoEnvironment = {
        getWorker(workerId, label) {
            if (label === 'html' || label === 'handlebars' || label === 'razor') {
                return new Worker(workers.html);
            }
            if (label === 'css' || label === 'less' || label === 'scss') {
                return new Worker(workers.css);
            }
            if (label === 'json') {
                return new Worker(workers.json);
            }
            return new Worker(workers.editor);
        },
    };

    monaco.editor.defineTheme('studio-dark', studioDark);
    monaco.editor.defineTheme('studio-light', studioLight);
}

/** The editor chrome's current theme, read off the root class. */
function currentTheme() {
    return document.documentElement.classList.contains('studio-light')
        ? 'studio-light'
        : 'studio-dark';
}

window.StudioMonaco = {
    monaco,

    /**
     * A file-backed model, one per open Code-mode tab, so switching tabs
     * keeps each file's undo history, folds and cursor.
     */
    model(path, language, value) {
        const uri = monaco.Uri.parse('studio://workspace/' + path.replace(/^\/+/, ''));

        return monaco.editor.getModel(uri) || monaco.editor.createModel(value, language, uri);
    },

    /** Follow the editor chrome when the user flips light/dark. */
    syncTheme() {
        if (booted) monaco.editor.setTheme(currentTheme());
    },

    /**
     * Create an editor in `host`. `workers` carries the same-origin URLs
     * for the base, HTML, CSS and JSON workers (resolved by StudioAssets on the
     * PHP side — they may include ?v= cache busters, which Worker() accepts).
     */
    create(host, { language = 'html', value = '', workers }) {
        boot(workers);

        const mono = getComputedStyle(document.body).getPropertyValue('--font-mono').trim();

        return monaco.editor.create(host, {
            value,
            language,
            theme: currentTheme(),
            fontSize: 12.5,
            fontFamily: mono || undefined,
            automaticLayout: true,
            minimap: { enabled: false },
            wordWrap: 'on',
            lineNumbers: 'on',
            lineNumbersMinChars: 3,
            padding: { top: 12 },
            scrollBeyondLastLine: false,
            autoIndent: 'advanced',
            formatOnPaste: true,
            // The modal clips overflow; render completion widgets
            // position:fixed so they aren't cut off.
            fixedOverflowWidgets: true,
            stickyScroll: { enabled: false },
        });
    },
};
