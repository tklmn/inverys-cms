// Copy TinyMCE assets to public/vendor/tinymce for self-hosted usage
const fs = require('fs');
const path = require('path');

const src = path.join(__dirname, 'node_modules/tinymce');
const dest = path.join(__dirname, 'public/vendor/tinymce');

function copyDir(srcDir, destDir) {
    if (!fs.existsSync(destDir)) {
        fs.mkdirSync(destDir, { recursive: true });
    }
    for (const entry of fs.readdirSync(srcDir, { withFileTypes: true })) {
        const srcPath = path.join(srcDir, entry.name);
        const destPath = path.join(destDir, entry.name);
        if (entry.isDirectory()) {
            copyDir(srcPath, destPath);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    }
}

// Copy only the directories TinyMCE needs at runtime
const dirs = ['skins', 'icons', 'themes', 'models', 'plugins'];
for (const dir of dirs) {
    const s = path.join(src, dir);
    if (fs.existsSync(s)) {
        copyDir(s, path.join(dest, dir));
    }
}

// Copy tinymce.min.js as fallback
const mainFile = path.join(src, 'tinymce.min.js');
if (fs.existsSync(mainFile)) {
    fs.copyFileSync(mainFile, path.join(dest, 'tinymce.min.js'));
}

// Language packs for the editor UI (menus, dialogs). TinyMCE 8 ships none;
// tinymce-i18n redistributes the official ones from tiny.cloud. The backend
// loads the file matching the user's language (App\Support\ScriptTexts).
const langs = path.join(__dirname, 'node_modules/tinymce-i18n/langs8');
if (fs.existsSync(langs)) {
    copyDir(langs, path.join(dest, 'langs'));
}

console.log('Copied TinyMCE assets to public/vendor/tinymce');
