// Backend specific JavaScript
import '../shared/bootstrap';

// Expose SortableJS globally for inline plugin scripts (form-builder etc.)
import Sortable from 'sortablejs';
window.Sortable = Sortable;

// Import page specific scripts
import './pages/tree.js';
import './pages/editor.js';

// Backend UI: lists, detail panel, navigation, filters
import { t, tinymceLanguage, contentLanguages } from './ui/i18n.js';
import './ui/icons.js';
import './ui/detail-panel.js';
import './ui/navigation.js';
import './ui/list-filter.js';
import './ui/permission-picker.js';
import './ui/dropzone.js';
import './ui/setting-reset.js';
import './ui/media-picker.js';
import './ui/toast.js';
import './ui/color-field.js';
import './ui/compact.js';
import './ui/depends.js';
import './ui/image-field.js';
import './ui/focal-point.js';
import './ui/slug-field.js';
import './ui/char-count.js';
import './ui/copy.js';
import './ui/menu.js';
import './ui/translation-copy.js';
import './ui/command.js';
import './ui/tabs-scroll.js';

// Load the code editor only where it is needed (theme editor)
if (document.querySelector('textarea[data-code-editor]')) {
    import('./ui/code-editor.js');
}

// Import page builder
import './page-builder/index.js';

// TinyMCE — loaded on demand to reduce initial bundle size (~800KB)
let _tinymceReady = null;
function loadTinyMCE() {
    if (!_tinymceReady) {
        _tinymceReady = (async () => {
            const { default: tinymce } = await import('tinymce');
            await Promise.all([
                import('tinymce/themes/silver'),
                import('tinymce/icons/default'),
                import('tinymce/models/dom'),
                import('tinymce/plugins/link'),
                import('tinymce/plugins/lists'),
                import('tinymce/plugins/image'),
                import('tinymce/plugins/table'),
                import('tinymce/plugins/code'),
                import('tinymce/plugins/autolink'),
                import('tinymce/plugins/media'),
                import('tinymce/plugins/fullscreen'),
            ]);
            window.tinymce = tinymce;
            return tinymce;
        })();
    }
    return _tinymceReady;
}

/**
 * Initialize a TinyMCE editor on a <textarea> element.
 * Used by the Blade partial and page builder.
 * Lazy-loads TinyMCE on first call.
 *
 * @param {string|Element} selector - CSS selector or DOM element
 * @param {object} opts - Extra TinyMCE options to merge
 * @returns {Promise}
 */
window.initTinyMCE = async function (selector, opts = {}) {
    const tinymce = await loadTinyMCE();

    const baseUrl = (document.querySelector('meta[name="asset-url"]')?.content || '').replace(/\/+$/, '');
    const skinUrl = baseUrl + '/vendor/tinymce/skins/ui/oxide';
    const contentCss = baseUrl + '/vendor/tinymce/skins/content/default/content.min.css';

    const isDark = document.documentElement.classList.contains('dark');
    const editorLanguage = tinymceLanguage();
    // Installed content languages for the "Language" button (lang="…" on a passage)
    const contentLangs = contentLanguages();

    // Extract user's setup so we can merge it with ours (not override)
    const userSetup = opts.setup;
    delete opts.setup;

    const defaults = {
        selector: typeof selector === 'string' ? selector : undefined,
        target: typeof selector === 'string' ? undefined : selector,
        license_key: 'gpl',
        base_url: baseUrl + '/vendor/tinymce',
        document_base_url: baseUrl + '/',
        skin_url: isDark ? baseUrl + '/vendor/tinymce/skins/ui/oxide-dark' : skinUrl,
        content_css: isDark ? baseUrl + '/vendor/tinymce/skins/content/dark/content.min.css' : contentCss,
        plugins: 'link lists table code autolink media fullscreen image',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough' + (contentLangs.length > 1 ? ' language' : '') + ' | bullist numlist | link medialibrary image media table | alignleft aligncenter alignright | code fullscreen',
        ...(contentLangs.length > 1 ? { content_langs: contentLangs } : {}),
        menubar: false,
        // Accessibility (WCAG 2.2 AA): the image dialog offers "Image is decorative"
        // (alt="" + role="presentation") and keeps the alt text editable after insertion
        image_description: true,
        a11y_advanced_options: true,
        image_caption: true,
        image_advtab: false,
        image_title: false,
        // The image dialog's source field opens our media library
        file_picker_types: 'image',
        file_picker_callback: function (callback) {
            document.dispatchEvent(new CustomEvent('tinymce-media-request', {
                bubbles: true,
                detail: {
                    callback: function (storagePath, meta) {
                        callback(storagePath, { alt: meta?.decorative ? '' : (meta?.alt || '') });
                    },
                },
            }));
        },
        // Links: the editor chooses the same or a new tab (no forced target="_blank").
        // New-tab links get rel="noopener" here and a screen reader note on output.
        link_target_list: [
            { title: t('link_same_tab', 'Same tab'), value: '' },
            { title: t('link_new_tab', 'New tab'), value: '_blank' },
        ],
        link_title: true,
        // Tables: caption, header rows (thead + th) and header cells with scope
        table_appearance_options: true,
        table_advtab: false,
        table_cell_advtab: false,
        table_row_advtab: false,
        table_header_type: 'sectionCells',
        table_default_attributes: {},
        table_default_styles: {},
        table_toolbar: 'tableprops tabledelete | tablecaption tablerowheader tablecolheader tablecellprops | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol',
        statusbar: true,
        branding: false,
        promotion: false,
        resize: true,
        min_height: 200,
        convert_urls: false,
        relative_urls: false,
        // Store umlauts as characters (otherwise "&Uuml;" ends up in the database and search)
        entity_encoding: 'raw',
        // Editor UI in the user's language (English is built in)
        ...(editorLanguage ? { language: editorLanguage.code, language_url: editorLanguage.url } : {}),
        setup: function (editor) {
            editor.on('init', () => {
                // Set <base> in iframe so relative paths (storage/media/...) resolve correctly
                const doc = editor.getDoc();
                if (doc && !doc.querySelector('base')) {
                    const base = doc.createElement('base');
                    base.href = baseUrl + '/';
                    doc.head.insertBefore(base, doc.head.firstChild);
                }

                // Sync all TinyMCE editors to their textareas before form submit
                const form = editor.getElement()?.closest('form');
                if (form && !form._tinymceSyncAttached) {
                    form.addEventListener('submit', () => window.tinymce.triggerSave());
                    form._tinymceSyncAttached = true;
                }
            });
            // Media library button — opens our library directly, inserts image on select
            editor.ui.registry.addButton('medialibrary', {
                icon: 'image',
                tooltip: t('insert_image', 'Insert image'),
                onAction: function () {
                    document.dispatchEvent(new CustomEvent('tinymce-media-request', {
                        bubbles: true,
                        detail: {
                            editorId: editor.id,
                            callback: function (storagePath, meta) {
                                // Alt text from the library — never the file name. Decorative
                                // images get alt="" + role="presentation" (TinyMCE's own
                                // marking); without either, the image dialog asks for one.
                                const decorative = !!meta?.decorative;
                                const alt = decorative ? '' : (meta?.alt || '');
                                const missing = !decorative && alt === '';
                                // Built as a string: DOMUtils drops empty attributes, and alt=""
                                // must stay for decorative images. An image still waiting for its
                                // text gets none yet — TinyMCE would read alt="" as "decorative".
                                const attr = (value) => editor.dom.encode(String(value)).replace(/"/g, '&quot;');
                                editor.insertContent(
                                    '<img src="' + attr(storagePath) + '"'
                                    + (missing ? '' : ' alt="' + attr(alt) + '"')
                                    + (decorative ? ' role="presentation"' : '') + ' loading="lazy" />'
                                );

                                if (missing) {
                                    const inserted = editor.dom.select('img').find((node) => node.getAttribute('src') === storagePath && !node.hasAttribute('alt'));
                                    if (inserted) {
                                        editor.selection.select(inserted);
                                        // Dialog closed without a text: alt="" rather than none — without
                                        // alt, screen readers read out the (random) file name
                                        editor.once('CloseWindow', () => {
                                            if (inserted.isConnected && !inserted.hasAttribute('alt')) {
                                                inserted.setAttribute('alt', '');
                                                editor.setDirty(true);
                                                editor.nodeChanged();
                                            }
                                        });
                                        editor.execCommand('mceImage');
                                    }
                                }
                            },
                        },
                    }));
                },
            });

            // Sync content back to textarea on change
            if (opts.onChange) {
                // Only fully initialized, live instances have a serializer
                editor.on('change keyup', () => {
                    if (editor.initialized && !editor.removed) opts.onChange(editor.getContent());
                });
            }

            // Call user's setup too (so page builder can add its own event handlers)
            if (userSetup) {
                userSetup(editor);
            }
        },
    };

    return tinymce.init({ ...defaults, ...opts });
};

/**
 * Resolve a relative path (e.g. "storage/media/...") to a full asset URL.
 * Absolute URLs (http/https) are returned unchanged.
 */
window.resolveAssetUrl = function (path) {
    if (!path) return '';
    if (/^https?:\/\//.test(path)) return path;
    const baseUrl = (document.querySelector('meta[name="asset-url"]')?.content || '').replace(/\/+$/, '');
    return baseUrl + '/' + path.replace(/^\/+/, '');
};

