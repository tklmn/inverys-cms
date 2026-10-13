// Texts and locale for backend scripts.
//
// The layout outputs them as JSON (<script type="application/json" id="ui-i18n">,
// App\Support\ScriptTexts). Scripts do not write their own texts, they call
//   t('insert_image', 'Insert image')
// — the second value only applies if the key is missing. Placeholders as in
// Laravel: t('saved_count', ':count saved', { count: 3 }).

let data = null;

function load() {
    if (data) return data;
    try {
        data = JSON.parse(document.getElementById('ui-i18n')?.textContent || '{}');
    } catch {
        data = {};
    }
    return data;
}

export function t(key, fallback, replace) {
    let text = load().texts?.[key] ?? fallback ?? key;
    for (const [name, value] of Object.entries(replace || {})) {
        text = text.split(':' + name).join(String(value));
    }
    return text;
}

export function locale() {
    return load().locale || document.documentElement.lang || 'en';
}

// TinyMCE language file ({ code, url }) or null for English
export function tinymceLanguage() {
    return load().tinymce || null;
}

window.uiT = t;
