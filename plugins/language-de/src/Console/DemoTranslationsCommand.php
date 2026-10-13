<?php

namespace Plugins\LanguageDe\Console;

use Illuminate\Console\Command;
use Plugins\LanguageDe\DemoTranslations;

/**
 * Add the German version of the demo content — for installations that already
 * have the demo content. The installer does this itself for new installations.
 * Safe to run repeatedly: existing content is not overwritten.
 */
class DemoTranslationsCommand extends Command
{
    protected $signature = 'language-de:demo';

    protected $description = 'Add the German version of the bundled demo content';

    public function handle(): int
    {
        $counts = (new DemoTranslations)->apply();

        if ($counts === []) {
            $this->warn('Nothing to translate: no untranslated demo content found, or the Multilingual plugin is not active.');

            return self::SUCCESS;
        }

        foreach ($counts as $what => $count) {
            $this->line(sprintf('  %-16s %d', $what, $count));
        }
        $this->info('German demo content added.');

        return self::SUCCESS;
    }
}
