<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Illustrations for the demo content — pure SVG graphics without text.
 *
 * No text labels, which would look out of place in other languages: a
 * strong gradient with light, abstract motifs on top (browser window,
 * charts, map …). This works in light and dark mode alike and needs no
 * translation.
 */
final class DemoIllustrations
{
    /** File → [motif, gradient from, gradient to] */
    private const IMAGES = [
        'hero-abstract.svg' => ['builder', '#4f46e5', '#9333ea'],
        'about-team.svg' => ['team', '#7c3aed', '#db2777'],
        'team-lead.svg' => ['person', '#2563eb', '#4f46e5'],
        'team-design.svg' => ['person', '#db2777', '#9333ea'],
        'team-dev.svg' => ['person', '#059669', '#0891b2'],
        'services-web.svg' => ['code', '#2563eb', '#0891b2'],
        'services-consulting.svg' => ['charts', '#d97706', '#dc2626'],
        'features-overview.svg' => ['blocks', '#4f46e5', '#0891b2'],
        'contact-hero.svg' => ['contact', '#0891b2', '#4f46e5'],
        'map-placeholder.svg' => ['map', '#334155', '#1e293b'],
        'docs-hero.svg' => ['docs', '#7c3aed', '#4f46e5'],
        'blog-getting-started.svg' => ['launch', '#2563eb', '#7c3aed'],
        'blog-page-builder.svg' => ['blocks', '#7c3aed', '#db2777'],
        'blog-themes.svg' => ['themes', '#db2777', '#f59e0b'],
        'blog-tips.svg' => ['checklist', '#059669', '#65a30d'],
        'blog-plugins.svg' => ['plugins', '#d97706', '#db2777'],
        'blog-api.svg' => ['network', '#0891b2', '#2563eb'],
        'feature-languages.svg' => ['languages', '#0d9488', '#2563eb'],
        'feature-media.svg' => ['gallery', '#db2777', '#7c3aed'],
        'feature-security.svg' => ['shield', '#334155', '#4f46e5'],
        'avatar-1.svg' => ['person', '#f59e0b', '#db2777'],
        'avatar-2.svg' => ['person', '#0891b2', '#4f46e5'],
        'avatar-3.svg' => ['person', '#16a34a', '#0d9488'],
        // Logos for the logo strip: their own wide format (logo())
        'logo-1.svg' => ['logo', '1', ''],
        'logo-2.svg' => ['logo', '2', ''],
        'logo-3.svg' => ['logo', '3', ''],
        'logo-4.svg' => ['logo', '4', ''],
        'logo-5.svg' => ['logo', '5', ''],
    ];

    public static function has(string $file): bool
    {
        return isset(self::IMAGES[$file]);
    }

    /** @return list<string> */
    public static function files(): array
    {
        return array_keys(self::IMAGES);
    }

    public static function svg(string $file): string
    {
        [$motif, $from, $to] = self::IMAGES[$file] ?? ['blocks', '#4f46e5', '#9333ea'];

        if ($motif === 'logo') {
            return self::logo((int) $from);
        }
        $id = preg_replace('/[^a-z]/', '', pathinfo($file, PATHINFO_FILENAME));

        $motifs = [
            'builder' => fn () => self::builder(),
            'team' => fn () => self::team(),
            'person' => fn () => self::person(),
            'code' => fn () => self::code(),
            'charts' => fn () => self::charts(),
            'blocks' => fn () => self::blocks(),
            'contact' => fn () => self::contact(),
            'map' => fn () => self::map(),
            'docs' => fn () => self::docs(),
            'launch' => fn () => self::launch(),
            'themes' => fn () => self::themes(),
            'checklist' => fn () => self::checklist(),
            'plugins' => fn () => self::plugins(),
            'network' => fn () => self::network(),
            'languages' => fn () => self::languages(),
            'gallery' => fn () => self::gallery(),
            'shield' => fn () => self::shield(),
        ];

        $body = ($motifs[$motif] ?? $motifs['blocks'])();

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" role="img">
          <defs>
            <linearGradient id="{$id}-bg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="{$from}"/>
              <stop offset="1" stop-color="{$to}"/>
            </linearGradient>
            <radialGradient id="{$id}-glow" cx=".8" cy=".1" r=".7">
              <stop offset="0" stop-color="#fff" stop-opacity=".28"/>
              <stop offset="1" stop-color="#fff" stop-opacity="0"/>
            </radialGradient>
            <pattern id="{$id}-grid" width="48" height="48" patternUnits="userSpaceOnUse">
              <path d="M48 0H0V48" fill="none" stroke="#fff" stroke-opacity=".07" stroke-width="1"/>
            </pattern>
            <filter id="{$id}-shadow" x="-20%" y="-20%" width="140%" height="160%">
              <feDropShadow dx="0" dy="24" stdDeviation="28" flood-color="#0b1020" flood-opacity=".35"/>
            </filter>
          </defs>
          <rect width="1200" height="800" fill="url(#{$id}-bg)"/>
          <rect width="1200" height="800" fill="url(#{$id}-grid)"/>
          <rect width="1200" height="800" fill="url(#{$id}-glow)"/>
          <circle cx="1060" cy="700" r="240" fill="#fff" fill-opacity=".06"/>
          <circle cx="120" cy="90" r="160" fill="#fff" fill-opacity=".05"/>
          <g filter="url(#{$id}-shadow)">
        {$body}
          </g>
        </svg>
        SVG;
    }

    /**
     * Made-up company logos without lettering: a mark plus a "wordmark" of
     * bars, single colour — the logo strip greys them out or tints them.
     */
    private static function logo(int $variant): string
    {
        $c = '#334155';
        $marks = [
            1 => '<circle cx="40" cy="50" r="26" fill="'.$c.'"/><circle cx="40" cy="50" r="11" fill="#fff"/>',
            2 => '<path d="M14 76 L40 24 L66 76 Z" fill="'.$c.'"/>',
            3 => '<rect x="14" y="24" width="24" height="24" rx="6" fill="'.$c.'"/><rect x="42" y="24" width="24" height="24" rx="6" fill="'.$c.'" fill-opacity=".55"/><rect x="14" y="52" width="24" height="24" rx="6" fill="'.$c.'" fill-opacity=".55"/><rect x="42" y="52" width="24" height="24" rx="6" fill="'.$c.'"/>',
            4 => '<path d="M14 50 a26 26 0 0 1 52 0 a26 26 0 0 1 -52 0 M28 50 h24" fill="none" stroke="'.$c.'" stroke-width="10" stroke-linecap="round"/>',
            5 => '<path d="M14 70 C 30 20, 50 20, 66 70" fill="none" stroke="'.$c.'" stroke-width="12" stroke-linecap="round"/><circle cx="40" cy="74" r="8" fill="'.$c.'"/>',
        ];
        $words = [1 => [150, 70], 2 => [120, 90], 3 => [170, 50], 4 => [110, 110], 5 => [140, 80]][$variant] ?? [140, 80];

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 100" role="img">'
            .($marks[$variant] ?? $marks[1])
            .self::rect(84, 36, $words[0], 16, 8, $c)
            .self::rect(84, 58, $words[1], 10, 5, $c, .5)
            .'</svg>';
    }

    // ── Building blocks ──────────────────────────────────────────

    private static function rect(float $x, float $y, float $w, float $h, float $r, string $fill = '#fff', float $opacity = 1): string
    {
        return sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"%s/>', $x, $y, $w, $h, $r, $fill, $opacity < 1 ? sprintf(' fill-opacity="%s"', $opacity) : '');
    }

    /** Browser window with title bar; content relative to the inner area */
    private static function window(float $x, float $y, float $w, float $h, string $inner = ''): string
    {
        return '<g>'
            .self::rect($x, $y, $w, $h, 22)
            .self::rect($x, $y, $w, 52, 22, '#eef2ff')
            .self::rect($x, $y + 30, $w, 22, 0, '#eef2ff')
            .sprintf('<circle cx="%s" cy="%s" r="7" fill="#f87171"/><circle cx="%s" cy="%s" r="7" fill="#fbbf24"/><circle cx="%s" cy="%s" r="7" fill="#34d399"/>', $x + 30, $y + 26, $x + 54, $y + 26, $x + 78, $y + 26)
            .self::rect($x + 110, $y + 16, min(320, $w - 150), 20, 10, '#c7d2fe', .7)
            .sprintf('<g transform="translate(%s %s)">%s</g>', $x, $y + 52, $inner)
            .'</g>';
    }

    /** Text lines as rounded bars */
    private static function lines(float $x, float $y, array $widths, string $fill = '#cbd5e1', float $gap = 26, float $height = 12): string
    {
        $out = '';
        foreach ($widths as $i => $w) {
            $out .= self::rect($x, $y + $i * $gap, $w, $height, $height / 2, $fill);
        }

        return $out;
    }

    // ── Motifs ───────────────────────────────────────────────────

    private static function builder(): string
    {
        $page = self::rect(40, 36, 640, 150, 16, '#6366f1')
            .self::rect(70, 70, 280, 22, 11, '#fff', .95)
            .self::rect(70, 106, 200, 14, 7, '#fff', .6)
            .self::rect(70, 138, 110, 30, 15, '#fff')
            .self::rect(192, 138, 96, 30, 15, '#fff', .35)
            .self::rect(430, 60, 220, 104, 14, '#fff', .18);
        foreach ([0, 1, 2] as $i) {
            $cx = 40 + $i * 218;
            $page .= self::rect($cx, 210, 204, 170, 16, '#f1f5f9')
                .sprintf('<circle cx="%s" cy="252" r="18" fill="%s"/>', $cx + 38, ['#a5b4fc', '#f9a8d4', '#6ee7b7'][$i])
                .self::lines($cx + 20, 290, [150, 120, 90], '#cbd5e1', 24, 10);
        }

        return self::window(220, 150, 720, 480, $page)
            // Floating palette at the bottom left
            .self::rect(90, 520, 250, 180, 20)
            .self::lines(116, 548, [120], '#c7d2fe', 0, 12)
            .'<g>'.implode('', array_map(fn ($i) => self::rect(116 + ($i % 3) * 68, 578 + intdiv($i, 3) * 54, 58, 42, 10, ['#e0e7ff', '#fce7f3', '#dcfce7', '#fef3c7', '#e0f2fe', '#ede9fe'][$i]), range(0, 5))).'</g>'
            // Colour picker at the top right
            .self::rect(880, 90, 230, 96, 20)
            .'<circle cx="930" cy="138" r="22" fill="#6366f1"/><circle cx="984" cy="138" r="22" fill="#a855f7"/><circle cx="1038" cy="138" r="22" fill="#ec4899"/>'
            // Mouse pointer
            .'<path d="M760 560 l0 64 l18 -18 l14 30 l14 -7 l-14 -29 l26 -2 z" fill="#0f172a" stroke="#fff" stroke-width="4" stroke-linejoin="round"/>';
    }

    private static function team(): string
    {
        $avatar = fn (float $cx, float $cy, float $r, string $c) => sprintf('<circle cx="%s" cy="%s" r="%s" fill="#fff"/><circle cx="%s" cy="%s" r="%s" fill="%s"/><circle cx="%s" cy="%s" r="%s" fill="#fff"/><path d="M%s %s a%s %s 0 0 1 %s 0" fill="#fff"/>',
            $cx, $cy, $r, $cx, $cy, $r - 8, $c, $cx, $cy - $r * .22, $r * .3, $cx - $r * .52, $cy + $r * .62, $r * .52, $r * .5, $r * 1.04);

        return '<path d="M330 420 C 480 300, 720 300, 870 420" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="6" stroke-dasharray="4 18" stroke-linecap="round"/>'
            .$avatar(330, 430, 120, '#a78bfa')
            .$avatar(600, 360, 150, '#f472b6')
            .$avatar(870, 430, 120, '#60a5fa')
            // Speech bubbles
            .self::rect(150, 170, 220, 90, 26).'<path d="M200 258 l-10 36 l40 -36 z" fill="#fff"/>'
            .self::lines(180, 198, [150, 110], '#ddd6fe', 26, 12)
            .self::rect(840, 150, 220, 90, 26).'<path d="M1010 238 l10 36 l-40 -36 z" fill="#fff"/>'
            .self::lines(870, 178, [150, 90], '#fbcfe8', 26, 12)
            .self::rect(420, 610, 360, 70, 35, '#fff', .9)
            .'<circle cx="470" cy="645" r="16" fill="#34d399"/>'.self::lines(500, 639, [240], '#e2e8f0', 0, 12);
    }

    private static function person(): string
    {
        return '<circle cx="600" cy="400" r="270" fill="#fff" fill-opacity=".12"/>'
            .'<circle cx="600" cy="400" r="210" fill="#fff" fill-opacity=".14"/>'
            .'<circle cx="600" cy="330" r="92" fill="#fff"/>'
            .'<path d="M420 600 a180 170 0 0 1 360 0 v30 h-360 z" fill="#fff"/>'
            .'<circle cx="850" cy="190" r="26" fill="#fff" fill-opacity=".7"/>'
            .'<circle cx="340" cy="600" r="16" fill="#fff" fill-opacity=".6"/>'
            .self::rect(760, 560, 170, 56, 28, '#fff', .9)
            .'<circle cx="792" cy="588" r="12" fill="#34d399"/>'.self::lines(814, 582, [90], '#cbd5e1', 0, 12);
    }

    private static function code(): string
    {
        $rows = [
            [[40, '#c084fc'], [120, '#60a5fa']],
            [[70, '#94a3b8'], [180, '#34d399'], [60, '#fbbf24']],
            [[70, '#94a3b8'], [90, '#f472b6'], [200, '#60a5fa']],
            [[110, '#94a3b8'], [140, '#34d399']],
            [[110, '#94a3b8'], [60, '#c084fc'], [220, '#fbbf24']],
            [[70, '#94a3b8'], [100, '#60a5fa']],
            [[40, '#c084fc']],
            [[40, '#c084fc'], [160, '#f472b6'], [80, '#60a5fa']],
            [[70, '#94a3b8'], [240, '#34d399']],
        ];
        $inner = self::rect(0, 0, 760, 448, 0, '#0f172a');
        foreach ($rows as $i => $segments) {
            $x = 36;
            $inner .= self::rect(20, 34 + $i * 42, 18, 12, 6, '#334155');
            foreach ($segments as [$w, $c]) {
                $inner .= self::rect($x + 30, 34 + $i * 42, $w, 12, 6, $c);
                $x += $w + 16;
            }
        }
        $inner .= self::rect(0, 420, 760, 28, 0, '#1e293b');

        return self::window(220, 130, 760, 500, $inner)
            .self::rect(840, 470, 260, 190, 22)
            .'<path d="M890 600 l40 -50 l40 30 l50 -70 l40 40" fill="none" stroke="#6366f1" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>'
            .self::lines(886, 500, [120], '#c7d2fe', 0, 12);
    }

    private static function charts(): string
    {
        $bars = '';
        foreach ([120, 190, 150, 250, 210, 300] as $i => $h) {
            $bars .= self::rect(60 + $i * 72, 360 - $h, 44, $h, 10, $i === 5 ? '#f97316' : '#fdba74');
        }
        $inner = self::rect(30, 24, 520, 380, 18, '#fff7ed')
            .self::lines(60, 50, [160], '#fed7aa', 0, 14)
            .$bars
            .self::rect(580, 24, 180, 180, 18, '#fff7ed')
            .'<circle cx="670" cy="114" r="56" fill="none" stroke="#fed7aa" stroke-width="22"/>'
            .'<path d="M670 58 a56 56 0 0 1 53 74" fill="none" stroke="#f97316" stroke-width="22"/>'
            .self::rect(580, 224, 180, 180, 18, '#fff7ed')
            .self::lines(604, 256, [110, 80, 130, 60], '#fdba74', 32, 12);

        return self::window(200, 140, 800, 490, $inner)
            .self::rect(880, 80, 220, 110, 22)
            .'<path d="M916 150 l34 -34 l28 22 l50 -52" fill="none" stroke="#16a34a" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    private static function blocks(): string
    {
        $tiles = [
            [160, 150, 420, 200, '#fff'], [600, 150, 440, 90, '#fff', .9], [600, 260, 210, 90, '#fff', .75],
            [830, 260, 210, 90, '#fff', .9], [160, 370, 250, 250, '#fff', .9], [430, 370, 360, 120, '#fff'],
            [430, 510, 360, 110, '#fff', .75], [810, 370, 230, 250, '#fff'],
        ];
        $out = '';
        foreach ($tiles as $t) {
            $out .= self::rect($t[0], $t[1], $t[2], $t[3], 20, $t[4], $t[5] ?? 1);
        }

        return $out
            .self::rect(190, 184, 220, 22, 11, '#818cf8').self::lines(190, 226, [320, 260, 180], '#e2e8f0', 28, 12)
            .'<circle cx="650" cy="195" r="22" fill="#a5b4fc"/>'.self::lines(690, 186, [300], '#e2e8f0', 0, 16)
            .'<circle cx="285" cy="470" r="60" fill="#c4b5fd"/>'.self::lines(210, 560, [150, 110], '#e2e8f0', 26, 12)
            .self::rect(460, 400, 110, 60, 12, '#e0f2fe').self::lines(590, 410, [170, 120], '#e2e8f0', 26, 12)
            .self::rect(460, 545, 130, 40, 20, '#6366f1').self::rect(605, 545, 110, 40, 20, '#c7d2fe')
            .self::lines(840, 404, [170, 130, 150, 100, 160], '#e2e8f0', 36, 12);
    }

    private static function contact(): string
    {
        return '<g transform="rotate(-6 520 430)">'
            .self::rect(300, 250, 480, 320, 26)
            .'<path d="M300 280 L540 440 L780 280" fill="none" stroke="#bae6fd" stroke-width="14" stroke-linejoin="round"/>'
            .'<path d="M300 560 L470 410 M780 560 L610 410" stroke="#e0f2fe" stroke-width="10"/>'
            .'</g>'
            .self::rect(760, 160, 300, 120, 30).'<path d="M820 278 l-6 44 l50 -44 z" fill="#fff"/>'
            .self::lines(800, 196, [210, 150], '#bae6fd', 30, 14)
            .self::rect(760, 480, 240, 100, 30, '#fff', .92)
            .self::lines(796, 512, [160, 110], '#c7d2fe', 28, 12)
            .'<circle cx="220" cy="190" r="62" fill="#fff"/>'
            .'<path d="M196 166 c0 36 22 58 58 58 l10 -18 l-22 -12 l-10 10 c-12 -6 -20 -14 -26 -26 l10 -10 l-12 -22 z" fill="#0891b2"/>';
    }

    private static function map(): string
    {
        $out = self::rect(0, 0, 1200, 800, 0, '#1e293b', .0)
            .'<path d="M-20 600 C 240 520, 420 700, 700 610 S 1060 480, 1240 540" fill="none" stroke="#38bdf8" stroke-opacity=".55" stroke-width="54"/>'
            .self::rect(760, 110, 260, 170, 30, '#4ade80', .28)
            .self::rect(120, 150, 200, 140, 30, '#4ade80', .22);
        foreach ([130, 330, 520, 720, 900] as $x) {
            $out .= sprintf('<path d="M%s -20 L%s 820" stroke="#fff" stroke-opacity=".22" stroke-width="%s"/>', $x, $x + 60, $x === 520 ? 22 : 12);
        }
        foreach ([90, 330, 470, 720] as $y) {
            $out .= sprintf('<path d="M-20 %s L1220 %s" stroke="#fff" stroke-opacity=".22" stroke-width="%s"/>', $y, $y - 40, $y === 330 ? 22 : 12);
        }

        return $out
            .'<circle cx="560" cy="330" r="110" fill="#f43f5e" fill-opacity=".18"/>'
            .'<circle cx="560" cy="330" r="60" fill="#f43f5e" fill-opacity=".28"/>'
            .'<path d="M560 350 c-44 -52 -66 -84 -66 -116 a66 66 0 0 1 132 0 c0 32 -22 64 -66 116 z" fill="#f43f5e" stroke="#fff" stroke-width="8"/>'
            .'<circle cx="560" cy="232" r="22" fill="#fff"/>';
    }

    private static function docs(): string
    {
        return self::rect(340, 200, 440, 480, 24, '#fff', .45)
            .self::rect(380, 170, 440, 480, 24, '#fff', .7)
            .self::rect(420, 140, 440, 480, 24)
            .self::rect(460, 184, 220, 24, 12, '#8b5cf6')
            .self::lines(460, 236, [340, 300, 330, 220], '#e2e8f0', 30, 12)
            .self::rect(460, 370, 360, 120, 16, '#0f172a')
            .self::lines(484, 398, [120, 210, 160], '#a78bfa', 26, 10)
            .self::lines(460, 520, [320, 260], '#e2e8f0', 30, 12)
            .self::rect(830, 480, 200, 150, 24, '#fff', .95)
            .'<path d="M880 560 l30 30 l60 -70" fill="none" stroke="#22c55e" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    private static function launch(): string
    {
        return '<path d="M140 660 C 360 640, 520 520, 640 420 S 900 200, 1020 150" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="8" stroke-dasharray="2 22" stroke-linecap="round"/>'
            .'<circle cx="140" cy="660" r="26" fill="#fff"/>'
            .'<circle cx="640" cy="420" r="26" fill="#fff"/>'
            .'<path d="M1010 90 v120" stroke="#fff" stroke-width="10" stroke-linecap="round"/>'
            .'<path d="M1016 96 h110 l-26 32 l26 32 h-110 z" fill="#fff"/>'
            .self::rect(220, 250, 330, 150, 24)
            .self::lines(256, 286, [160], '#c7d2fe', 0, 16)
            .self::lines(256, 326, [250, 190], '#e2e8f0', 28, 12)
            .self::rect(690, 520, 330, 130, 24, '#fff', .92)
            .'<circle cx="742" cy="585" r="26" fill="#22c55e"/>'
            .'<path d="M730 585 l9 9 l17 -19" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round"/>'
            .self::lines(790, 566, [190, 140], '#e2e8f0', 28, 12);
    }

    private static function themes(): string
    {
        $inner = self::rect(30, 30, 330, 360, 16, '#fdf2f8')
            .self::rect(390, 30, 330, 360, 16, '#0f172a');
        foreach ([[60, '#ec4899', '#fbcfe8'], [420, '#f59e0b', '#334155']] as [$x, $a, $line]) {
            $inner .= self::rect($x, 60, 270, 90, 12, $a)
                .self::lines($x, 176, [240, 190, 220], $line, 28, 12)
                .self::rect($x, 290, 110, 36, 18, $a);
        }

        return self::window(230, 170, 750, 470, $inner)
            .'<circle cx="190" cy="300" r="56" fill="#fff"/><circle cx="190" cy="300" r="34" fill="#ec4899"/>'
            .'<circle cx="160" cy="440" r="42" fill="#fff"/><circle cx="160" cy="440" r="24" fill="#f59e0b"/>'
            .'<circle cx="1040" cy="170" r="48" fill="#fff"/><path d="M1040 136 a34 34 0 1 0 0 68 z" fill="#0f172a"/>';
    }

    private static function checklist(): string
    {
        $out = self::rect(330, 130, 540, 540, 30);
        foreach ([0, 1, 2, 3] as $i) {
            $y = 200 + $i * 112;
            $done = $i < 3;
            $out .= self::rect(380, $y, 56, 56, 16, $done ? '#22c55e' : '#e2e8f0')
                .($done ? sprintf('<path d="M394 %s l12 12 l20 -22" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>', $y + 28) : '')
                .self::lines(466, $y + 8, [300 - $i * 30, 200], $done ? '#cbd5e1' : '#e2e8f0', 26, 12);
        }

        return $out
            .'<circle cx="960" cy="220" r="70" fill="#fff" fill-opacity=".9"/>'
            .'<path d="M960 180 v44 l28 18" fill="none" stroke="#059669" stroke-width="12" stroke-linecap="round"/>';
    }

    private static function plugins(): string
    {
        $piece = fn (float $x, float $y, string $c) => self::rect($x, $y, 180, 180, 30, '#fff')
            .self::rect($x + 30, $y + 30, 120, 120, 22, $c)
            .sprintf('<circle cx="%s" cy="%s" r="26" fill="#fff"/>', $x + 180, $y + 90)
            .sprintf('<circle cx="%s" cy="%s" r="26" fill="#fff"/>', $x + 90, $y + 180);

        return $piece(320, 170, '#fde68a')
            .$piece(560, 170, '#fbcfe8')
            .$piece(320, 410, '#bfdbfe')
            .self::rect(560, 410, 180, 180, 30, '#fff', .35)
            .'<path d="M650 470 v60 M620 500 h60" stroke="#fff" stroke-width="12" stroke-linecap="round"/>'
            .self::rect(820, 250, 250, 110, 24, '#fff', .92)
            .self::lines(850, 282, [170, 120], '#e2e8f0', 30, 12);
    }

    /** Two versions of the same page, the switch in between */
    private static function languages(): string
    {
        $page = fn (float $x, float $y, string $c) => self::rect($x, $y, 330, 380, 24)
            .self::rect($x + 30, $y + 34, 150, 22, 11, $c)
            .self::lines($x + 30, $y + 84, [270, 230, 250, 180], '#e2e8f0', 30, 12)
            .self::rect($x + 30, $y + 230, 270, 110, 16, '#f1f5f9')
            .sprintf('<circle cx="%s" cy="%s" r="26" fill="%s"/>', $x + 80, $y + 285, $c)
            .self::lines($x + 124, $y + 272, [140, 100], '#cbd5e1', 26, 10);

        return $page(180, 210, '#14b8a6')
            .$page(690, 210, '#3b82f6')
            .'<circle cx="600" cy="400" r="62" fill="#fff"/>'
            .'<circle cx="600" cy="400" r="40" fill="none" stroke="#0d9488" stroke-width="6"/>'
            .'<path d="M560 400 h80 M600 360 c-22 24 -22 56 0 80 M600 360 c22 24 22 56 0 80" fill="none" stroke="#0d9488" stroke-width="6" stroke-linecap="round"/>'
            .'<path d="M520 300 c40 -40 120 -40 160 0" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="6" stroke-dasharray="4 14" stroke-linecap="round"/>';
    }

    /** Image grid as in the media library */
    private static function gallery(): string
    {
        $out = self::rect(220, 150, 760, 500, 28);
        $colors = ['#fbcfe8', '#ddd6fe', '#bfdbfe', '#fde68a', '#bbf7d0', '#fecaca'];
        foreach (range(0, 5) as $i) {
            $x = 260 + ($i % 3) * 236;
            $y = 190 + intdiv($i, 3) * 216;
            $out .= self::rect($x, $y, 208, 188, 18, $colors[$i])
                .sprintf('<path d="M%s %s l50 -60 l40 40 l30 -30 l60 70 z" fill="#fff" fill-opacity=".85"/>', $x + 24, $y + 160)
                .sprintf('<circle cx="%s" cy="%s" r="16" fill="#fff" fill-opacity=".9"/>', $x + 160, $y + 48);
        }

        return $out
            .self::rect(840, 560, 260, 110, 24, '#fff', .96)
            .'<path d="M890 630 v-40 m-18 18 l18 -18 l18 18" fill="none" stroke="#db2777" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>'
            .self::lines(940, 596, [120, 80], '#e2e8f0', 28, 12);
    }

    /** Shield with a check mark, roles and code beside it */
    private static function shield(): string
    {
        return '<path d="M600 150 L790 220 V400 C790 530 700 610 600 650 C500 610 410 530 410 400 V220 Z" fill="#fff"/>'
            .'<path d="M600 200 L745 254 V400 C745 500 677 562 600 596 C523 562 455 500 455 400 V254 Z" fill="#6366f1"/>'
            .'<path d="M540 405 l42 42 l84 -92" fill="none" stroke="#fff" stroke-width="22" stroke-linecap="round" stroke-linejoin="round"/>'
            .self::rect(150, 250, 220, 90, 22, '#fff', .92)
            .'<circle cx="192" cy="295" r="20" fill="#a5b4fc"/>'.self::lines(226, 284, [110, 70], '#e2e8f0', 24, 10)
            .self::rect(150, 370, 220, 90, 22, '#fff', .8)
            .'<circle cx="192" cy="415" r="20" fill="#c4b5fd"/>'.self::lines(226, 404, [100, 80], '#e2e8f0', 24, 10)
            .self::rect(840, 300, 230, 150, 22, '#0f172a', .92)
            .'<circle cx="890" cy="350" r="12" fill="#34d399"/>'
            .self::lines(914, 344, [120], '#a5b4fc', 0, 12)
            .self::lines(870, 386, [170, 130], '#475569', 28, 10);
    }

    private static function network(): string
    {
        $nodes = [[600, 400, 70], [300, 230, 46], [900, 220, 46], [260, 590, 40], [930, 600, 40], [600, 140, 34]];
        $out = '';
        foreach (array_slice($nodes, 1) as [$x, $y]) {
            $out .= sprintf('<path d="M600 400 L%s %s" stroke="#fff" stroke-opacity=".55" stroke-width="6" stroke-dasharray="14 12"/>', $x, $y);
        }
        foreach ($nodes as $i => [$x, $y, $r]) {
            $out .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="#fff"/><circle cx="%s" cy="%s" r="%s" fill="%s"/>', $x, $y, $r, $x, $y, $r * .45, $i === 0 ? '#2563eb' : '#7dd3fc');
        }

        return $out
            .'<path d="M560 380 l-22 20 l22 20 M640 380 l22 20 l-22 20" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>'
            .self::rect(760, 360, 300, 110, 22, '#0f172a', .92)
            .self::lines(790, 390, [120, 220], '#7dd3fc', 30, 12);
    }
}
