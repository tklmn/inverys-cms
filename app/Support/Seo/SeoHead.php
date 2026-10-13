<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Support\SeoSettings;

/**
 * The SEO tags of the current page for the <head>: description, robots,
 * canonical, verification codes, icons, Open Graph, Twitter and JSON-LD.
 *
 * Rendered by the core head hook frontend.partials.seo-head. Tags the
 * theme already prints itself (older themes with their own meta tags)
 * are left out, so nothing appears twice — the theme's version wins.
 */
final class SeoHead
{
    public function __construct(private readonly SeoContext $seo) {}

    public function render(): string
    {
        $seo = $this->seo;

        if (! $seo->indexableResponse()) {
            return '';
        }

        $existing = self::existingTags($seo->documentHead());
        $lines = [];
        $add = function (string $key, string $html) use (&$lines, $existing) {
            if (! isset($existing[$key])) {
                $lines[] = $html;
            }
        };

        $title = $this->title();
        $description = $seo->description ?? $this->headDescription();
        $socialTitle = $seo->socialTitle ?: $title;
        $socialDescription = $seo->socialDescription ?: $description;
        $canonical = $seo->canonicalUrl();

        if ($description !== null && $description !== '') {
            $add('name:description', self::meta('name', 'description', $description));
        }

        if ($seo->author !== null && trim($seo->author) !== '') {
            $add('name:author', self::meta('name', 'author', trim($seo->author)));
        }

        if (($robots = $seo->robotsDirectives()) !== null) {
            $add('name:robots', self::meta('name', 'robots', $robots));
        }

        // A noindex page names no canonical address — the two signals contradict each other
        if (! $seo->isNoindex()) {
            $add('rel:canonical', '<link rel="canonical" href="'.e($canonical).'">');
        }

        if ($seo->home) {
            foreach (SeoSettings::verificationTags() as $name => $code) {
                $add('name:'.$name, self::meta('name', $name, $code));
            }
        }

        foreach ($this->icons() as $rel => $html) {
            $add('rel:'.$rel, $html);
        }

        // Open Graph
        $locale = LanguageCodes::ogLocale($seo->contentLocale());
        $image = $this->image();

        $add('property:og:type', self::meta('property', 'og:type', $seo->type));
        $add('property:og:site_name', self::meta('property', 'og:site_name', (string) setting('site_name', __('translation.app_name'))));
        $add('property:og:title', self::meta('property', 'og:title', $socialTitle));
        if ($socialDescription !== null && $socialDescription !== '') {
            $add('property:og:description', self::meta('property', 'og:description', $socialDescription));
        }
        $add('property:og:url', self::meta('property', 'og:url', $canonical));
        if ($locale !== null) {
            $add('property:og:locale', self::meta('property', 'og:locale', $locale));

            if (! isset($existing['property:og:locale:alternate']) && $seo->emitsAlternates()) {
                foreach (array_keys($seo->alternates()) as $code) {
                    $alternate = LanguageCodes::ogLocale((string) $code);
                    if ($alternate !== null && $alternate !== $locale) {
                        $lines[] = self::meta('property', 'og:locale:alternate', $alternate);
                    }
                }
            }
        }

        if ($image !== null) {
            $imageAlt = $seo->imageAlt ?? Urls::mediaAlt($image['source']) ?? $socialTitle;
            $add('property:og:image', self::meta('property', 'og:image', $image['url']));
            if ($image['size'] !== null) {
                $add('property:og:image:width', self::meta('property', 'og:image:width', (string) $image['size'][0]));
                $add('property:og:image:height', self::meta('property', 'og:image:height', (string) $image['size'][1]));
            }
            $add('property:og:image:alt', self::meta('property', 'og:image:alt', $imageAlt));
        }

        if ($seo->type === SeoContext::TYPE_ARTICLE) {
            foreach (['published_time', 'modified_time', 'author', 'section'] as $field) {
                $value = $seo->article[$field] ?? null;
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format(\DateTimeInterface::ATOM);
                }
                if (is_string($value) && $value !== '') {
                    $add('property:article:'.$field, self::meta('property', 'article:'.$field, $value));
                }
            }
        }

        // Twitter / X
        $add('name:twitter:card', self::meta('name', 'twitter:card', $image !== null ? 'summary_large_image' : 'summary'));
        $add('name:twitter:title', self::meta('name', 'twitter:title', $socialTitle));
        if ($socialDescription !== null && $socialDescription !== '') {
            $add('name:twitter:description', self::meta('name', 'twitter:description', $socialDescription));
        }
        if ($image !== null) {
            $add('name:twitter:image', self::meta('name', 'twitter:image', $image['url']));
        }

        // JSON-LD — only for pages a controller has described
        if ($seo->filled && ! $seo->isNoindex()) {
            $data = StructuredData::build($seo, $title, $description, $image['url'] ?? null);
            if ($data !== null) {
                $nonce = csp_nonce();
                $lines[] = '<script type="application/ld+json"'.($nonce !== '' ? ' nonce="'.e($nonce).'"' : '').'>'.StructuredData::encode($data).'</script>';
            }
        }

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    /** Title without the site name: described one, otherwise the page's <title> */
    private function title(): string
    {
        $title = trim((string) $this->seo->title);

        if ($title === '' && preg_match('#<title[^>]*>(.*?)</title>#is', $this->seo->documentHead(), $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $title !== '' ? $title : (string) setting('site_name', __('translation.app_name'));
    }

    /** Description of a page no controller has described: its own meta tag or the default */
    private function headDescription(): ?string
    {
        foreach (self::metaTags($this->seo->documentHead()) as $tag) {
            if (($tag['key'] ?? '') === 'name:description' && ($tag['content'] ?? '') !== '') {
                return html_entity_decode($tag['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return Descriptions::pick();
    }

    /** @return array{url: string, source: string, size: array{0: int, 1: int}|null}|null */
    private function image(): ?array
    {
        $source = Urls::firstSocialImage($this->seo->image, SeoSettings::defaultImage());

        if ($source === null) {
            return null;
        }

        return ['url' => Urls::absolute($source), 'source' => $source, 'size' => Urls::imageSize($source)];
    }

    /** apple-touch-icon and web app manifest — only if the files exist */
    private function icons(): array
    {
        $icons = [];

        if (is_file(public_path('apple-touch-icon.png'))) {
            $icons['apple-touch-icon'] = '<link rel="apple-touch-icon" href="'.e(asset('apple-touch-icon.png')).'">';
        }

        if (is_file(public_path('site.webmanifest'))) {
            $icons['manifest'] = '<link rel="manifest" href="'.e(asset('site.webmanifest')).'">';
        }

        return $icons;
    }

    private static function meta(string $attribute, string $name, string $content): string
    {
        return '<meta '.$attribute.'="'.e($name).'" content="'.e($content).'">';
    }

    /**
     * Tags the theme already prints: "name:description", "property:og:title",
     * "rel:canonical" … (lowercase).
     *
     * @return array<string, true>
     */
    public static function existingTags(string $head): array
    {
        $keys = [];

        foreach (self::metaTags($head) as $tag) {
            $keys[$tag['key']] = true;
        }

        if (preg_match_all('/<link\b[^>]*\brel\s*=\s*["\']([^"\']+)["\']/i', $head, $matches)) {
            foreach ($matches[1] as $rel) {
                foreach (preg_split('/\s+/', strtolower(trim($rel))) ?: [] as $part) {
                    $keys['rel:'.$part] = true;
                }
            }
        }

        return $keys;
    }

    /** @return list<array{key: string, content: string}> */
    private static function metaTags(string $head): array
    {
        $tags = [];

        if (preg_match_all('/<meta\b[^>]*>/i', $head, $matches)) {
            foreach ($matches[0] as $tag) {
                if (! preg_match('/\b(name|property)\s*=\s*["\']([^"\']+)["\']/i', $tag, $name)) {
                    continue;
                }
                preg_match('/\bcontent\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $content);
                $tags[] = [
                    'key' => strtolower($name[1]).':'.strtolower($name[2]),
                    'content' => ($content[1] ?? '') !== '' ? $content[1] : ($content[2] ?? ''),
                ];
            }
        }

        return $tags;
    }
}
