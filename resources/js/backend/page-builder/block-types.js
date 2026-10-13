// i18n-ignore-file: The names and defaults here are English fallback values.
// What is shown is translation.builder.block_* or default_* (PageBuilder.typeName,
// localizeDefaults); a block type without a translation appears with this name.
export const blockTypes = [
    // Layout
    { slug: 'container', name: 'Container', category: 'layout', icon: 'bi-box', hasChildren: true, defaults: { settings: { class: '', padding: 'py-8', full_width: true, max_width: 'max-w-7xl', background: '' } } },
    { slug: 'columns', name: 'Columns', category: 'layout', icon: 'bi-layout-three-columns', hasChildren: true, defaults: { settings: { class: '', columns: '2', gap: 'gap-4' } } },
    { slug: 'column', name: 'Column', category: 'layout', icon: 'bi-layout-sidebar', hasChildren: true, internal: true, defaults: { settings: { class: '' } } },
    { slug: 'section', name: 'Section', category: 'layout', icon: 'bi-layout-text-window', hasChildren: true, defaults: { settings: { class: '', background: '', padding: 'py-8', style: '', align: '' } } },
    { slug: 'hero', name: 'Hero', category: 'layout', icon: 'square-mouse-pointer', defaults: { data: { eyebrow: '', title: '', text: '', primary_text: '', primary_url: '#', secondary_text: '', secondary_url: '', image: '', image_alt: '' }, settings: { class: '', layout: 'split', style: 'glow', size: 'normal', grid: true } } },
    { slug: 'spacer', name: 'Spacer', category: 'layout', icon: 'bi-distribute-vertical', defaults: { settings: { class: '', height: '2rem' } } },
    { slug: 'divider', name: 'Divider', category: 'layout', icon: 'bi-hr', defaults: { settings: { class: 'my-6' } } },

    // Content
    { slug: 'heading', name: 'Heading', category: 'content', icon: 'bi-type-h1', defaults: { data: { text: '', level: 'h2', alignment: 'left' }, settings: { class: '' } } },
    { slug: 'text', name: 'Text', category: 'content', icon: 'bi-text-paragraph', defaults: { data: { content: '' }, settings: { class: '' } } },
    { slug: 'image', name: 'Image', category: 'content', icon: 'bi-image', defaults: { data: { src: '', alt: '', caption: '' }, settings: { class: '', width: 'full' } } },
    { slug: 'video', name: 'Video', category: 'content', icon: 'bi-play-circle', defaults: { data: { url: '', poster: '' }, settings: { class: '', autoplay: false, controls: true } } },
    { slug: 'quote', name: 'Quote', category: 'content', icon: 'bi-quote', defaults: { data: { text: '', author: '', cite: '' }, settings: { class: '' } } },
    { slug: 'code', name: 'Code', category: 'content', icon: 'bi-code-slash', defaults: { data: { code: '', language: 'html' }, settings: { class: '' } } },
    { slug: 'stats', name: 'Key Figures', category: 'content', icon: 'chart-column', defaults: { data: { items: [] }, settings: { class: '', style: 'cards', align: 'center' } } },
    { slug: 'pricing', name: 'Pricing Table', category: 'content', icon: 'tag', defaults: { data: { plans: [] }, settings: { class: '' } } },
    { slug: 'logos', name: 'Logo Strip', category: 'content', icon: 'images', defaults: { data: { items: [] }, settings: { class: '', style: 'muted' } } },
    { slug: 'list', name: 'List', category: 'content', icon: 'bi-list-ul', defaults: { data: { items: ['Item 1', 'Item 2', 'Item 3'], style: 'unordered' }, settings: { class: '' } } },

    // Interactive
    { slug: 'accordion', name: 'Accordion', category: 'interactive', icon: 'bi-chevron-bar-expand', hasChildren: true, defaults: { settings: { class: '' } } },
    { slug: 'accordion-item', name: 'Accordion Section', category: 'interactive', icon: 'bi-chevron-bar-expand', hasChildren: true, internal: true, defaults: { data: { title: 'Section' }, settings: { class: '' } } },
    { slug: 'tabs', name: 'Tabs', category: 'interactive', icon: 'bi-window-stack', hasChildren: true, defaults: { settings: { class: '' } } },
    { slug: 'tab-item', name: 'Tab', category: 'interactive', icon: 'bi-window-stack', hasChildren: true, internal: true, defaults: { data: { title: 'Tab' }, settings: { class: '' } } },
    { slug: 'button', name: 'Button', category: 'interactive', icon: 'bi-hand-index', defaults: { data: { text: 'Click me', url: '#', style: 'primary', target: '_self' }, settings: { class: '' } } },
    { slug: 'buttons', name: 'Buttons', category: 'interactive', icon: 'mouse-pointer-click', defaults: { data: { items: [] }, settings: { class: '', size: '', align: '' } } },
    { slug: 'link-group', name: 'Link Group', category: 'interactive', icon: 'bi-link-45deg', defaults: { data: { links: [{ text: 'Link 1', url: '#', target: '_self' }] }, settings: { class: '' } } },
    { slug: 'alert', name: 'Alert', category: 'interactive', icon: 'bi-exclamation-triangle', defaults: { data: { title: '', content: '', type: 'info' }, settings: { class: '' } } },
    { slug: 'card', name: 'Card', category: 'interactive', icon: 'bi-card-heading', defaults: { data: { title: '', content: '', image: '', url: '', icon: '', link_text: '' }, settings: { class: '', style: '', align: '', icon_style: '' } } },
    { slug: 'slider', name: 'Slider', category: 'interactive', icon: 'bi-images', defaults: { data: { slides: [{ image: '', title: '', text: '', url: '' }], autoplay: true, interval: 5000, show_arrows: true, show_dots: true }, settings: { class: '', height: '400px' } } },
];

// Plugin block type injection hook
window.__inverysPluginBlockTypes = window.__inverysPluginBlockTypes || [];
window.__inverysBlockSettingsRenderers = window.__inverysBlockSettingsRenderers || {};
