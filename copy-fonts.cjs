// Copy Bootstrap Icons CSS and font files to public directory
const fs = require('fs');
const path = require('path');

// Create public/css directory if it doesn't exist
const cssDestDir = path.join(__dirname, 'public/css');
if (!fs.existsSync(cssDestDir)) {
    fs.mkdirSync(cssDestDir, { recursive: true });
}

// Copy Bootstrap Icons CSS file
const cssSource = path.join(__dirname, 'node_modules/bootstrap-icons/font/bootstrap-icons.css');
const cssDest = path.join(cssDestDir, 'bootstrap-icons.css');
fs.copyFileSync(cssSource, cssDest);
console.log(`Copied bootstrap-icons.css to ${cssDestDir}`);

// Create public/fonts directory if it doesn't exist
const fontDestDir = path.join(__dirname, 'public/fonts');
if (!fs.existsSync(fontDestDir)) {
    fs.mkdirSync(fontDestDir, { recursive: true });
}

// Copy bootstrap-icons font files
const fontSourceDir = path.join(__dirname, 'node_modules/bootstrap-icons/font/fonts');
fs.readdirSync(fontSourceDir).forEach(file => {
    const src = path.join(fontSourceDir, file);
    const dest = path.join(fontDestDir, file);
    fs.copyFileSync(src, dest);
    console.log(`Copied ${file} to ${fontDestDir}`);
});

// Update CSS file paths to point to the correct location
let cssContent = fs.readFileSync(cssDest, 'utf8');
cssContent = cssContent.replace(/\.\/fonts\//g, '../fonts/');
fs.writeFileSync(cssDest, cssContent);
console.log('Updated CSS file paths');
