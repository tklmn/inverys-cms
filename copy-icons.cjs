/**
 * Copies the Lucide icons used in the backend from node_modules to
 * resources/icons/lucide/. The files are committed so an installation
 * works without npm.
 *
 * Need a new icon? Add its name below and run `node copy-icons.cjs`.
 * Available names: https://lucide.dev/icons
 */
const fs = require('fs');
const path = require('path');

const ICONS = [
    // Navigation
    'layout-dashboard', 'file-text', 'image', 'users', 'shield', 'key-round',
    'settings', 'search', 'shield-check', 'bell', 'scroll-text', 'trash-2',
    'info', 'palette', 'puzzle', 'folder-tree', 'plug', 'server',
    // Actions
    'plus', 'pencil', 'eye', 'trash', 'copy', 'download', 'upload', 'save',
    'x', 'check', 'refresh-cw', 'rotate-ccw', 'external-link', 'filter',
    'arrow-up-down', 'ellipsis', 'ellipsis-vertical', 'grip-vertical',
    'grip-horizontal', 'log-out', 'send',
    // Direction
    'chevron-right', 'chevron-left', 'chevron-down', 'chevron-up',
    'arrow-right', 'arrow-left', 'arrow-up', 'arrow-down', 'panel-left',
    // Status
    'circle-check', 'circle-alert', 'triangle-alert', 'circle-x', 'circle-help',
    'loader-circle', 'clock', 'calendar', 'lock', 'unlock', 'star',
    'trending-up', 'trending-down', 'minus',
    // Content
    'file', 'files', 'folder', 'folder-open', 'link', 'tag', 'globe',
    'sliders-horizontal', 'code', 'bell-off', 'file-code', 'shield-alert',
    'circle-arrow-up', 'music', 'film', 'image-off', 'type', 'align-left',
    'monitor', 'smartphone', 'cpu', 'package', 'history', 'git-compare',
    'sliders-horizontal', 'file-code', 'shield-check', 'folder-open', 'save',
    'newspaper', 'clipboard-list', 'images', 'cookie', 'layers', 'square-pen',
    'shield-x', 'shield-off', 'mail-open', 'eraser',
    'layout-grid', 'list', 'columns-3', 'sun', 'moon', 'user', 'user-plus',
    'user-round', 'mail', 'database', 'hard-drive', 'activity', 'inbox',
    // Replacements for Bootstrap Icons names (see App\Support\Icon::ALIASES)
    'hash', 'chart-column', 'box', 'log-in', 'panel-top', 'check-check',
    'chevrons-up-down', 'circle', 'circle-plus', 'clipboard', 'cloud-upload',
    'rows-3', 'eye-off', 'mouse-pointer-click', 'house', 'separator-horizontal',
    'text-cursor-input', 'file-plus', 'book-text', 'book-open', 'panels-top-left',
    'zap', 'list-checks', 'paperclip', 'circle-play', 'quote', 'circle-dot',
    'rss', 'gauge', 'square', 'phone', 'toggle-left', 'toggle-right', 'heading-1',
    'blocks', 'square-check', 'building-2', 'at-sign', 'cloud', 'zap', 'languages',
    // Frontend (Default-Theme)
    'menu', 'search-x', 'arrow-up', 'user-plus', 'log-out', 'chevron-down', 'chevron-right',
    // Icons for content (App\Support\ContentIcons — keep both lists in sync)
    'zap', 'rocket', 'sparkles', 'wand-sparkles', 'layers', 'blocks', 'layout-dashboard', 'palette',
    'puzzle', 'image', 'code', 'terminal', 'database', 'server', 'cloud', 'workflow',
    'monitor-smartphone', 'smartphone', 'gauge', 'infinity',
    'shield-check', 'lock', 'users', 'user', 'handshake', 'heart', 'star', 'award',
    'file-text', 'book-open', 'pen-tool', 'lightbulb', 'target', 'chart-column', 'trending-up',
    'search', 'globe', 'languages', 'leaf', 'gift', 'briefcase', 'building-2',
    'message-square', 'mail', 'phone', 'map-pin', 'clock', 'calendar', 'headphones', 'life-buoy',
    'settings', 'check', 'circle-check', 'arrow-right', 'minus', 'square-mouse-pointer',
    // Quick search in the header (ui/command.js)
    'corner-down-left',
    // System information → Components (SBOM)
    'boxes', 'file-json',
    // Settings → SEO (social networks, redirects)
    'share-2', 'split',
];

const src = path.join(__dirname, 'node_modules', 'lucide-static', 'icons');
const dest = path.join(__dirname, 'resources', 'icons', 'lucide');

if (!fs.existsSync(src)) {
    console.error('lucide-static not found — please run `npm install`.');
    process.exit(1);
}

fs.mkdirSync(dest, { recursive: true });

let copied = 0;
const missing = [];

for (const name of ICONS) {
    const file = path.join(src, `${name}.svg`);

    if (!fs.existsSync(file)) {
        missing.push(name);
        continue;
    }

    fs.copyFileSync(file, path.join(dest, `${name}.svg`));
    copied++;
}

console.log(`${copied} icons copied to resources/icons/lucide/.`);

if (missing.length) {
    console.warn(`Unknown icon names: ${missing.join(', ')}`);
    process.exit(1);
}
