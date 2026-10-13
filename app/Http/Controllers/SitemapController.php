<?php

namespace App\Http\Controllers;

use App\Sitemap\SitemapGenerator;
use App\Support\Locales;
use App\Support\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /** /sitemap.xml — index (multiple languages) or plain sitemap */
    public function index(SitemapGenerator $sitemap): Response
    {
        abort_unless($sitemap->enabled(), 404);

        return $this->xml($sitemap->xml());
    }

    /**
     * /sitemap-{locale}.xml. Without language sitemaps, /sitemap.xml is the
     * sitemap of the default language — its address redirects there.
     */
    public function locale(SitemapGenerator $sitemap, Locales $locales, string $locale): Response|RedirectResponse
    {
        abort_unless($sitemap->enabled(), 404);

        if (! $sitemap->isIndex()) {
            abort_unless($locale === $locales->default(), 404);

            return redirect()->route('sitemap', status: 301);
        }

        $xml = $sitemap->xmlFor($locale);
        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    /**
     * robots.txt from the SEO settings, with a reference to the sitemap. The
     * address depends on the installation, hence dynamic instead of a file
     * in public/. If a robots.txt does exist there, the web server serves
     * it before Laravel is asked.
     *
     * Mode "Off": 404 — crawlers then assume everything is allowed.
     */
    public function robots(): Response
    {
        $content = SeoSettings::robotsTxt();
        abort_if($content === null, 404);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
