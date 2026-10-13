<?php

namespace App\Helpers;

use App\Support\RichTextImages;
use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlHelper
{
    private static ?HTMLPurifier $purifier = null;

    /**
     * Sanitize HTML content, stripping dangerous tags/attributes while preserving safe markup.
     *
     * $responsiveImages: images from the media library get srcset/sizes,
     * width/height, loading and decoding (App\Support\RichTextImages). Runs
     * after purification, on the trusted output; off for HTML that is handed
     * on as data (API) instead of being shown.
     */
    public static function clean(?string $html, bool $responsiveImages = true): string
    {
        if (empty($html)) {
            return '';
        }

        self::$purifier ??= self::makePurifier();

        // Resolve relative storage paths to absolute asset URLs before purifying
        $html = self::resolveStoragePaths($html);

        $html = self::localizeLinks(self::announceNewTabLinks(self::$purifier->purify($html)));

        return $responsiveImages ? RichTextImages::enhance($html) : $html;
    }

    /**
     * IDs the layouts use themselves (skip link, header, menus, search, scroll button).
     * Content must not take them over: a heading with id="main-content" would
     * capture the skip link, a duplicate would break the scripts behind them.
     */
    private const RESERVED_IDS = [
        'main-content', 'main-nav', 'nav-mobile-menu', 'nav-mobile-toggle', 'search-overlay',
        'search-overlay-heading', 'search-overlay-input', 'scroll-to-top', 'theme-custom-css',
    ];

    /** Bump whenever the custom definition in makePurifier() changes (invalidates the serializer cache) */
    private const DEFINITION_REV = 2;

    private static function makePurifier(): HTMLPurifier
    {
        $config = HTMLPurifier_Config::createDefault();

        // class also on paragraphs, headings and lists: content uses
        // Tailwind classes (text-center, max-w-2xl mx-auto …) for its layout.
        // Without them, texts silently lost their alignment. mark/small/sub/sup
        // are pure text markup.
        //
        // Accessibility markup the editor produces must survive as well:
        // lang on block and inline elements (language of parts, WCAG 3.1.2),
        // id on headings (anchors), abbreviations, quotations, description
        // lists, table captions, header cells with scope, and link labels.
        $allowed = [
            'h1[class|id|lang]', 'h2[class|id|lang]', 'h3[class|id|lang]', 'h4[class|id|lang]', 'h5[class|id|lang]', 'h6[class|id|lang]',
            'p[class|lang]', 'br', 'hr[class]',
            'strong', 'b', 'em', 'i', 'u', 's', 'del', 'mark', 'small', 'sub', 'sup',
            'abbr[title|lang]', 'q[cite|lang]', 'cite[lang]',
            'a[href|title|target|rel|class|lang|aria-label]',
            'ul[class|lang]', 'ol[class|lang]', 'li[class|lang]',
            'dl[class|lang]', 'dt[class|lang]', 'dd[class|lang]',
            'blockquote[class|cite|lang]', 'pre[class|lang]', 'code[class|lang]',
            'img[src|alt|width|height|class|loading|role]',
            'table[class|lang]', 'caption[class|lang]', 'thead', 'tbody', 'tfoot', 'tr',
            'th[scope|colspan|rowspan|abbr|class|lang]', 'td[colspan|rowspan|headers|class|lang]',
            'div[class|lang]', 'span[class|lang]',
            'figure[class|lang]', 'figcaption[class|lang]', 'section[class|lang]', 'nav[class]',
            'video[src|controls|width|height|autoplay|muted|poster]', 'source[src|type]', 'address[lang]',
        ];
        $config->set('HTML.Allowed', implode(',', $allowed));
        // No forced target="_blank": the editor decides per link. Links that
        // open a new tab get rel="noopener noreferrer" (HTML.TargetNoopener /
        // HTML.TargetNoreferrer, on by default) and a note (announceNewTabLinks).
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        // id only where the allowlist names it (headings); duplicates within one text are dropped
        $config->set('Attr.EnableID', true);
        $config->set('Attr.IDBlacklist', self::RESERVED_IDS);

        $cachePath = storage_path('app/htmlpurifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.DefinitionID', 'inverys-rich-text');
        $config->set('HTML.DefinitionRev', self::DEFINITION_REV);

        // Add custom HTML5 elements and attributes not natively supported by
        // HTMLPurifier. Null when the definition comes from the cache.
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
            $def->addElement('section', 'Block', 'Flow', 'Common');
            $def->addElement('nav', 'Block', 'Flow', 'Common');
            $def->addElement('address', 'Block', 'Flow', 'Common');
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
            $def->addElement('video', 'Block', 'Optional: source', 'Common', [
                'src' => 'URI',
                'controls' => 'Bool',
                'autoplay' => 'Bool',
                'muted' => 'Bool',
                'poster' => 'URI',
                'width' => 'Length',
                'height' => 'Length',
            ]);
            $def->addElement('source', 'Block', 'Empty', 'Common', [
                'src' => 'URI',
                'type' => 'Text',
            ]);
            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
            // Decorative images (TinyMCE "Image is decorative": alt="" + role="presentation")
            $def->addAttribute('img', 'role', 'Enum#presentation,none');
            // Accessible name of a link, e.g. for "Read more" links
            $def->addAttribute('a', 'aria-label', 'Text');
            // Header cells a data cell belongs to (space separated ids)
            $def->addAttribute('td', 'headers', 'Text');
        }

        return new HTMLPurifier($config);
    }

    /**
     * Links that open a new tab say so to screen reader users (WCAG 3.2.5):
     * a visually hidden "(opens in a new tab)" after the link text, or — when
     * the link has an aria-label, which replaces its text — inside the label.
     * Runs on purified markup: attributes are double-quoted, <a> never nests.
     */
    public static function announceNewTabLinks(string $html): string
    {
        if (! str_contains($html, 'target="_blank"')) {
            return $html;
        }

        $note = __('translation.frontend.opens_in_new_tab');

        return preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#s', function (array $m) use ($note) {
            [$link, $attributes, $text] = $m;

            if (! preg_match('#\btarget="_blank"#', $attributes)) {
                return $link;
            }

            if (preg_match('#\baria-label="([^"]*)"#', $attributes, $label)) {
                $current = html_entity_decode($label[1], ENT_QUOTES | ENT_HTML5);
                if (str_contains($current, $note)) {
                    return $link;
                }

                return '<a'.str_replace($label[0], 'aria-label="'.e(trim($current.' '.$note)).'"', $attributes).'>'.$text.'</a>';
            }

            if (str_contains($text, $note)) {
                return $link;
            }

            return '<a'.$attributes.'>'.$text.'<span class="sr-only"> '.e($note).'</span></a>';
        }, $html) ?? $html;
    }

    /**
     * Turn links within the website (href="/contact") into the full address:
     * with base folder (installation in a subdirectory) and — in the
     * frontend of a translation — with language prefix (url() knows both).
     */
    public static function localizeLinks(string $html): string
    {
        return preg_replace_callback(
            '#href="(/(?!/)[^"]*)"#',
            fn ($m) => 'href="'.e(self::localUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5))).'"',
            $html
        );
    }

    /** Path within the website ("/contact") as full address; everything else unchanged */
    public static function localUrl(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }

    /**
     * Sanitize CSS by stripping dangerous patterns (XSS vectors).
     */
    public static function cleanCss(?string $css): string
    {
        if (empty($css)) {
            return '';
        }

        // Remove dangerous CSS patterns that can execute JavaScript or load external resources
        $patterns = [
            '/expression\s*\(/i',          // expression()
            '/url\s*\(\s*["\']?\s*javascript\s*:/i', // url(javascript:...)
            '/@import/i',                   // @import
            '/behavior\s*:/i',             // behavior: (IE-specific)
            '/-moz-binding\s*:/i',         // -moz-binding: (Firefox XBL)
            '/javascript\s*:/i',           // javascript: in any context
            '/vbscript\s*:/i',             // vbscript: in any context
            '/<\s*\/?\s*style/i',          // nested <style> tags
            '/<\s*\/?\s*script/i',         // <script> tags
        ];

        $css = preg_replace($patterns, '/* removed */', $css);

        return $css;
    }

    /**
     * Convert relative storage/... paths in src attributes to absolute asset() URLs.
     */
    public static function resolveStoragePaths(string $html): string
    {
        $storageUrl = asset('storage');

        return preg_replace(
            '/src=(["\'])storage\/([^"\']+)\1/',
            'src="'.$storageUrl.'/$2"',
            $html
        );
    }

    /**
     * Return a URL safe to place in an href/src, or '#' if its scheme is dangerous.
     * Allows http(s), mailto, tel, relative and anchor/query URLs; blocks javascript:,
     * data:, vbscript: and other executable schemes.
     */
    public static function safeUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '#';
        }

        // Relative, absolute-path, anchor or query URLs are safe. Paths of
        // our own website get base folder and language prefix.
        if (preg_match('#^(/|\./|\.\./|\#|\?)#', $url)) {
            return self::localUrl($url);
        }

        // Strip HTML entities/whitespace that could hide the scheme, then inspect it.
        $normalized = strtolower(preg_replace('/[\s\x00-\x1F]+/', '', $url));

        if (preg_match('/^[a-z][a-z0-9+.-]*:/', $normalized, $m)) {
            $scheme = rtrim($m[0], ':');
            if (! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                return '#';
            }
        }

        return $url;
    }
}
