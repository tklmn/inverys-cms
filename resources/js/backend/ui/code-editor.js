/**
 * Code editor with syntax highlighting (CodeMirror 6).
 *
 * Only loaded when a page contains a <textarea data-code-editor>
 * (see app.js) — the rest of the backend does not carry the ~250 KB (gzip).
 *
 * The textarea remains the source of truth: every change in the editor is
 * written back and fires an `input` event. Existing code that listens
 * to the textarea (unsaved marker, saving) therefore keeps working
 * unchanged.
 *
 * Setting content from outside:
 *   textarea.dispatchEvent(new CustomEvent('code-editor:load', {
 *       detail: { content, language: 'blade', disabled: false },
 *   }));
 *
 * Colors come from tokens (--code-*, tokens.css) so the editor looks right
 * in dark mode without its own theme.
 */

import { EditorView, basicSetup } from 'codemirror';
import { EditorState, Compartment } from '@codemirror/state';
import { keymap } from '@codemirror/view';
import { indentWithTab } from '@codemirror/commands';
import { HighlightStyle, syntaxHighlighting, indentUnit } from '@codemirror/language';
import { tags as t } from '@lezer/highlight';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { php } from '@codemirror/lang-php';
import { xml } from '@codemirror/lang-xml';
import { yaml } from '@codemirror/lang-yaml';
import { markdown } from '@codemirror/lang-markdown';

// Language as per ThemeEditorController::detectLanguage()
const LANGUAGES = {
    // Blade is HTML with embedded PHP — {{ }} is not highlighted
    // separately, but the surrounding markup is.
    blade: () => php({ baseLanguage: html().language }),
    php: () => php({ baseLanguage: html().language }),
    html: () => html(),
    css: () => css(),
    javascript: () => javascript(),
    json: () => json(),
    xml: () => xml(),
    yaml: () => yaml(),
    markdown: () => markdown(),
};

const highlight = HighlightStyle.define([
    { tag: [t.keyword, t.controlKeyword, t.moduleKeyword, t.operatorKeyword], color: 'var(--code-keyword)' },
    { tag: [t.string, t.special(t.string), t.regexp], color: 'var(--code-string)' },
    { tag: [t.number, t.bool, t.null, t.atom], color: 'var(--code-number)' },
    { tag: [t.comment, t.lineComment, t.blockComment], color: 'var(--code-comment)', fontStyle: 'italic' },
    { tag: [t.tagName, t.typeName, t.className], color: 'var(--code-tag)' },
    { tag: [t.attributeName, t.propertyName], color: 'var(--code-attr)' },
    { tag: [t.function(t.variableName), t.function(t.propertyName)], color: 'var(--code-function)' },
    { tag: [t.variableName, t.definition(t.variableName)], color: 'var(--code-variable)' },
    { tag: [t.meta, t.processingInstruction, t.angleBracket], color: 'var(--code-meta)' },
    { tag: t.heading, color: 'var(--code-tag)', fontWeight: '600' },
    { tag: t.link, color: 'var(--code-string)', textDecoration: 'underline' },
    { tag: t.invalid, color: 'var(--critical)' },
]);

const theme = EditorView.theme({
    '&': {
        height: '100%',
        backgroundColor: 'var(--surface-sunken)',
        color: 'var(--fg)',
        fontSize: 'var(--text-sm)',
    },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': {
        fontFamily: 'var(--font-mono)',
        lineHeight: '1.6',
    },
    '.cm-content': { caretColor: 'var(--accent)', padding: '0.75rem 0' },
    '.cm-cursor, .cm-dropCursor': { borderLeftColor: 'var(--accent)' },
    '.cm-gutters': {
        backgroundColor: 'var(--surface-sunken)',
        color: 'var(--fg-subtle)',
        borderRight: '1px solid var(--line)',
    },
    '.cm-activeLine': { backgroundColor: 'color-mix(in oklab, var(--accent) 6%, transparent)' },
    '.cm-activeLineGutter': { backgroundColor: 'transparent', color: 'var(--fg)' },
    '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection': {
        backgroundColor: 'color-mix(in oklab, var(--accent) 25%, transparent) !important',
    },
    '.cm-matchingBracket': {
        backgroundColor: 'color-mix(in oklab, var(--accent) 18%, transparent)',
        outline: '1px solid var(--accent-line)',
    },
    '.cm-searchMatch': { backgroundColor: 'var(--caution-soft)' },
    '.cm-panels': { backgroundColor: 'var(--surface)', color: 'var(--fg)', borderColor: 'var(--line)' },
    '.cm-tooltip': { backgroundColor: 'var(--surface)', border: '1px solid var(--line)', color: 'var(--fg)' },
    '.cm-foldPlaceholder': { backgroundColor: 'var(--surface)', border: '1px solid var(--line)', color: 'var(--fg-muted)' },
});

function languageFor(name) {
    const make = LANGUAGES[name];
    return make ? make() : [];
}

function enhance(textarea) {
    const language = new Compartment();
    const editable = new Compartment();
    let silent = false;

    const host = document.createElement('div');
    host.className = 'ui-code-editor';
    textarea.insertAdjacentElement('afterend', host);
    textarea.hidden = true;

    const view = new EditorView({
        parent: host,
        state: EditorState.create({
            doc: textarea.value,
            extensions: [
                basicSetup,
                keymap.of([
                    indentWithTab,
                    {
                        // Ctrl/Cmd+S saves just like in the textarea
                        key: 'Mod-s',
                        preventDefault: true,
                        run: () => {
                            textarea.dispatchEvent(new CustomEvent('code-editor:save', { bubbles: true }));
                            return true;
                        },
                    },
                ]),
                indentUnit.of('    '),
                EditorState.tabSize.of(4),
                language.of(languageFor(textarea.dataset.language)),
                editable.of(EditorView.editable.of(!textarea.disabled)),
                // Wrap long template lines instead of scrolling sideways
                EditorView.lineWrapping,
                syntaxHighlighting(highlight),
                theme,
                EditorView.updateListener.of((update) => {
                    if (!update.docChanged || silent) return;
                    textarea.value = update.state.doc.toString();
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                }),
            ],
        }),
    });

    textarea.addEventListener('code-editor:load', (event) => {
        const { content = '', language: lang = 'text', disabled = false } = event.detail || {};

        textarea.value = content;
        textarea.disabled = disabled;

        // Loading a file does not count as a change
        silent = true;
        view.dispatch({
            changes: { from: 0, to: view.state.doc.length, insert: content },
            effects: [
                language.reconfigure(languageFor(lang)),
                editable.reconfigure(EditorView.editable.of(!disabled)),
            ],
        });
        silent = false;

        view.scrollDOM.scrollTop = 0;
        if (!disabled) view.focus();
    });

    textarea.codeEditor = view;
}

document.querySelectorAll('textarea[data-code-editor]').forEach(enhance);
