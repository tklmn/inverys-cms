<?php

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlHelper
{
    private static ?HTMLPurifier $purifier = null;

    /**
     * Sanitize HTML content, stripping dangerous tags/attributes while preserving safe markup.
     */
    public static function clean(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            // class also on paragraphs, headings and lists: content uses
            // Tailwind classes (text-center, max-w-2xl mx-auto …) for its layout.
            // Without them, texts silently lost their alignment. mark/small/sub/sup
            // are pure text markup.
            $config->set('HTML.Allowed', 'h1[class],h2[class],h3[class],h4[class],h5[class],h6[class],p[class],br,hr[class],strong,b,em,i,u,s,del,mark,small,sub,sup,a[href|title|target|rel|class],ul[class],ol[class],li[class],blockquote[class],pre[class],code[class],img[src|alt|width|height|class|loading],table[class],thead,tbody,tr,th,td,div[class],span[class],figure[class],figcaption[class],section[class],nav[class],video[src|controls|width|height|autoplay|muted|poster],source[src|type],address');
            $config->set('HTML.TargetBlank', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $cachePath = storage_path('app/htmlpurifier');
            if (! is_dir($cachePath)) {
                mkdir($cachePath, 0775, true);
            }
            $config->set('Cache.SerializerPath', $cachePath);

            // Add custom HTML5 elements not natively supported by HTMLPurifier
            $def = $config->getHTMLDefinition(true);
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
            $def->addAttribute('img', 'loading', 'Text');

            self::$purifier = new HTMLPurifier($config);
        }

        // Resolve relative storage paths to absolute asset URLs before purifying
        $html = self::resolveStoragePaths($html);

        return self::localizeLinks(self::$purifier->purify($html));
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
