/**
 * Studio's slim Monaco build for the dev-mode source editor.
 *
 * Bundles the editor core plus only the languages the code modal needs:
 * HTML (monarch highlighting + worker-backed language service) and YAML
 * (highlighting only — YAML validation happens server-side via
 * symfony/yaml on save). Loaded lazily by Studio.codeEditor() on first
 * modal open; never part of studio.js.
 *
 * Adapted from the DevDojo components package's monaco-editor.js.
 */
import * as monaco from 'monaco-editor/esm/vs/editor/edcore.main.js';
import 'monaco-editor/esm/vs/basic-languages/html/html.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/yaml/yaml.contribution.js';
import 'monaco-editor/esm/vs/language/html/monaco.contribution.js';

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

let booted = false;

function boot(workers) {
    if (booted) return;
    booted = true;

    window.MonacoEnvironment = {
        getWorker(workerId, label) {
            if (label === 'html' || label === 'handlebars' || label === 'razor') {
                return new Worker(workers.html);
            }
            return new Worker(workers.editor);
        },
    };

    monaco.editor.defineTheme('studio-dark', studioDark);
}

window.StudioMonaco = {
    monaco,

    /**
     * Create an editor in `host`. `workers` carries the same-origin URLs
     * for the base and HTML workers (resolved by StudioAssets on the PHP
     * side — they may include ?v= cache busters, which Worker() accepts).
     */
    create(host, { language = 'html', value = '', workers }) {
        boot(workers);

        const mono = getComputedStyle(document.body).getPropertyValue('--font-mono').trim();

        return monaco.editor.create(host, {
            value,
            language,
            theme: 'studio-dark',
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
