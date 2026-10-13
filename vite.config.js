import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';

/*
 * Themes and plugins from their own repos (themes/inverys, plugins/portfolio …)
 * are usually listed in .gitignore or .git/info/exclude. Tailwind skips such
 * folders — unless an @source names exactly that folder. Hence one line per
 * existing folder here, regenerated on every start of build and dev.
 */
function writeTailwindSources() {
    const lines = ['/* Auto-generated (vite.config.js) — do not edit by hand */'];
    for (const base of ['themes', 'plugins']) {
        if (!fs.existsSync(base)) continue;
        for (const entry of fs.readdirSync(base, { withFileTypes: true })) {
            if (entry.isDirectory() && !entry.name.startsWith('.')) lines.push(`@source '../../${base}/${entry.name}';`);
        }
    }
    const file = path.join('resources', 'css', 'sources.generated.css');
    const content = lines.join('\n') + '\n';
    if (!fs.existsSync(file) || fs.readFileSync(file, 'utf8') !== content) fs.writeFileSync(file, content);
}
writeTailwindSources();

export default defineConfig({
    // Relative instead of '/build/': lazily loaded parts (TinyMCE, code editor, icons)
    // are resolved relative to the loading script. With '/build/' they pointed
    // to the server root and went missing as soon as the CMS runs in a
    // subdirectory (e.g. /inverys-cms/public).
    base: './',
    plugins: [
        laravel({
            input: [
                'resources/css/backend/app.css',
                'resources/js/backend/app.js',
                'resources/css/frontend/app.css',
                'resources/js/frontend/app.js',
                // Editor preview (backend.pages.canvas)
                'resources/js/editor-canvas.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
