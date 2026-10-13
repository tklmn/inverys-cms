<?php

/**
 * Checks whether every translation key used in the code exists in English
 * (core) and in every other language.
 *
 *   php bin/check-translations.php            missing keys per language
 *   php bin/check-translations.php --unused   additionally: keys never used
 *   php bin/check-translations.php --locale=fr
 *
 * Resolved the same way as in Laravel:
 *   blog::blog.title        plugin namespace (plugins/blog/lang/…)
 *   translation.save        group (lang/en/translation.php)
 *   Save changes            JSON key (English = key, de.json translates)
 *
 * Only string-literal keys are detected; composed ones
 * (__($var), __('a.'.$b)) are listed by --dynamic for review.
 */
$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$showUnused = in_array('--unused', $args, true);
$showDynamic = in_array('--dynamic', $args, true);
$locales = ['de'];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--locale=')) {
        $locales = explode(',', substr($arg, 9));
    }
}

// --- Where are the language files? ------------------------------------------

/** Namespace => directory with en/, de/ … */
$namespaces = [];
foreach (glob($root.'/plugins/*/src/*ServiceProvider.php') as $file) {
    $src = file_get_contents($file);
    if (preg_match("/addNamespace\\(\\s*'([^']+)'\\s*,\\s*\\\$\\w+\\s*\\.\\s*'\\/lang'/", $src, $m)) {
        $namespaces[$m[1]] = dirname($file, 2).'/lang';
    }
}
foreach (glob($root.'/vendor/inverys/*/src/*ServiceProvider.php') as $file) {
    $src = file_get_contents($file);
    if (preg_match("/addNamespace\\(\\s*'([^']+)'/", $src, $m)) {
        $namespaces[$m[1]] = dirname($file, 2).'/lang';
    }
}

/** Directories that provide core groups and JSON per language (core + language packs) */
$corePaths = ['en' => [$root.'/lang']];
foreach (glob($root.'/plugins/*/plugin.json') as $manifest) {
    $meta = json_decode(file_get_contents($manifest), true);
    if (($meta['type'] ?? null) === 'language' && isset($meta['locale'])) {
        $corePaths[$meta['locale']][] = dirname($manifest).'/lang';
    }
}
foreach ($locales as $locale) {
    $corePaths[$locale][] = $root.'/lang'; // in case the core itself has files for the language
}

function loadGroup(array $dirs, string $locale, string $group): ?array
{
    $found = null;
    foreach ($dirs as $dir) {
        $file = "$dir/$locale/$group.php";
        if (is_file($file)) {
            $found = array_replace_recursive($found ?? [], (array) require $file);
        }
    }

    return $found;
}

function loadJson(array $dirs, string $locale): array
{
    $out = [];
    foreach ($dirs as $dir) {
        $file = "$dir/$locale.json";
        if (is_file($file)) {
            $out += (array) json_decode(file_get_contents($file), true);
        }
    }

    return $out;
}

function dig(?array $arr, string $path): mixed
{
    if ($arr === null) {
        return null;
    }
    if (array_key_exists($path, $arr)) {
        return $arr[$path];
    }
    foreach (explode('.', $path) as $part) {
        if (! is_array($arr) || ! array_key_exists($part, $arr)) {
            return null;
        }
        $arr = $arr[$part];
    }

    return $arr;
}

$coreGroups = array_map(fn ($f) => basename($f, '.php'), glob($root.'/lang/en/*.php'));

// --- Collect keys from the code ----------------------------------------------

$sourceDirs = ['app', 'resources/views', 'resources/js', 'routes', 'config', 'plugins', 'themes', 'database/seeders', 'vendor/inverys'];
$files = [];
foreach ($sourceDirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $path = $f->getPathname();
        if (preg_match('~/(lang|node_modules|sbom|assets/vendor)/~', $path)) {
            continue;
        }
        if (preg_match('/\.(php|js)$/', $path)) {
            $files[] = $path;
        }
    }
}

$call = '(?<![\w>$])(?:__|trans|trans_choice|@lang|Lang::get|Lang::choice|\$t)\(\s*';
$used = [];     // key => [file:line, …]
$dynamic = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    $rel = substr($file, strlen($root) + 1);
    if (preg_match_all('/'.$call.'(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")\s*([,)\.])/', $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $m) {
            $line = substr_count($src, "\n", 0, $m[0][1]) + 1;
            // Concatenated ('a.'.$b) or interpolated ("a.{$b}"): composed at runtime
            if ($m[2][0] === '.' || ($m[1][0][0] === '"' && str_contains($m[1][0], '$'))) {
                $dynamic[] = "$rel:$line  ".trim($m[0][0]);

                continue;
            }
            $key = stripcslashes(substr($m[1][0], 1, -1));
            $used[$key][] = "$rel:$line";
        }
    }
    if ($showDynamic && preg_match_all('/'.$call.'(\$[\w\->\[\]\'"]+)/', $src, $mm, PREG_OFFSET_CAPTURE)) {
        foreach ($mm[0] as $m) {
            $dynamic[] = $rel.':'.(substr_count($src, "\n", 0, $m[1]) + 1).'  '.trim($m[0]);
        }
    }
}

// --- Check -------------------------------------------------------------------

$missing = [];  // locale => [key => where]
$json = [];
foreach (array_merge(['en'], $locales) as $locale) {
    $json[$locale] = loadJson($corePaths[$locale] ?? [], $locale);
}

$lookup = function (string $key, string $locale) use ($namespaces, $corePaths, $coreGroups, $json) {
    if (str_contains($key, '::')) {
        [$ns, $rest] = explode('::', $key, 2);
        if (! isset($namespaces[$ns])) {
            return ['kind' => 'ns', 'found' => false, 'why' => "unknown namespace $ns"];
        }
        [$group, $item] = array_pad(explode('.', $rest, 2), 2, null);
        $value = $item === null ? loadGroup([$namespaces[$ns]], $locale, $group) : dig(loadGroup([$namespaces[$ns]], $locale, $group), $item);

        return ['kind' => 'ns', 'found' => $value !== null];
    }

    $group = explode('.', $key, 2)[0];
    if (in_array($group, $coreGroups, true) && preg_match('/^[\w-]+(\.[\w-]+)+$/', $key)) {
        $value = dig(loadGroup($corePaths[$locale] ?? [], $locale, $group), substr($key, strlen($group) + 1));

        return ['kind' => 'group', 'found' => $value !== null];
    }

    // JSON: English is the key itself
    return ['kind' => 'json', 'found' => $locale === 'en' || isset($json[$locale][$key])];
};

foreach ($used as $key => $where) {
    if ($key === '') {
        continue;
    }
    foreach (array_merge(['en'], $locales) as $locale) {
        $r = $lookup($key, $locale);
        if (! $r['found']) {
            $missing[$locale][$key] = $where[0].(count($where) > 1 ? ' (+'.(count($where) - 1).')' : '');
        }
    }
}

$total = 0;
foreach ($missing as $locale => $keys) {
    ksort($keys);
    echo "■ missing in '$locale': ".count($keys)." keys\n";
    foreach ($keys as $key => $where) {
        echo "    $key    ← $where\n";
    }
    $total += count($keys);
}

// Groups that English has but the other language does not fully cover
foreach ($locales as $locale) {
    $gaps = [];
    $check = function (array $en, ?array $other, string $prefix) use (&$check, &$gaps) {
        foreach ($en as $k => $v) {
            $path = $prefix === '' ? (string) $k : "$prefix.$k";
            if (is_array($v)) {
                $check($v, is_array($other[$k] ?? null) ? $other[$k] : null, $path);
            } elseif (! isset($other[$k])) {
                $gaps[] = $path;
            }
        }
    };
    foreach ($coreGroups as $group) {
        $check(loadGroup($corePaths['en'], 'en', $group) ?? [], loadGroup($corePaths[$locale] ?? [], $locale, $group), $group);
    }
    foreach ($namespaces as $ns => $dir) {
        foreach (glob("$dir/en/*.php") as $file) {
            $group = basename($file, '.php');
            $check(loadGroup([$dir], 'en', $group) ?? [], loadGroup([$dir], $locale, $group), "$ns::$group");
        }
    }
    if ($gaps) {
        echo "■ present in English, not in '$locale': ".count($gaps)."\n";
        foreach ($gaps as $g) {
            echo "    $g\n";
        }
        $total += count($gaps);
    }
}

if ($showUnused) {
    $usedSet = array_fill_keys(array_keys($used), true);
    $unused = [];
    foreach ($json['de'] ?? [] as $k => $_) {
        if (! isset($usedSet[$k])) {
            $unused[] = "json: $k";
        }
    }
    echo '■ never used (JSON de): '.count($unused)."\n";
    foreach ($unused as $u) {
        echo "    $u\n";
    }
}

if ($showDynamic) {
    echo '■ dynamic keys (check by hand): '.count($dynamic)."\n";
    foreach ($dynamic as $d) {
        echo "    $d\n";
    }
}

echo '----'.PHP_EOL.count($used).' keys in code, '.$total." gaps\n";
exit($total > 0 ? 1 : 0);
