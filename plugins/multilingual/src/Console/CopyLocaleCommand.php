<?php

namespace Plugins\Multilingual\Console;

use App\Contracts\ContentTranslator;
use App\Support\Locales;
use Illuminate\Console\Command;
use Plugins\Multilingual\DatabaseContentTranslator;

/**
 * Prefill a language from the command line — the same as the action under
 * System → Languages. Safe to run repeatedly: existing values are not overwritten.
 */
class CopyLocaleCommand extends Command
{
    protected $signature = 'multilingual:copy {from : Language to copy from, e.g. de} {to : Language to fill, e.g. fr}';

    protected $description = 'Prefill a language with the translations of another one as a starting point';

    public function handle(Locales $locales): int
    {
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');
        $base = $locales->base();

        foreach ([$from, $to] as $code) {
            if (! $locales->has($code)) {
                $this->error("Unknown language: {$code}. Installed: ".implode(', ', $locales->codes()));

                return self::FAILURE;
            }
        }

        if ($from === $to || $from === $base || $to === $base) {
            $this->error("Both languages must be translations (not the original {$base}) and different from each other.");

            return self::FAILURE;
        }

        $translator = app(ContentTranslator::class);
        if (! $translator instanceof DatabaseContentTranslator || ! $translator->enabled()) {
            $this->error('The Multilingual plugin is not active.');

            return self::FAILURE;
        }

        $result = $translator->copyLocale($from, $to);

        if ($result['items'] === 0) {
            $this->warn("Nothing to copy: everything {$from} has is already filled in {$to}.");

            return self::SUCCESS;
        }

        $this->info("Copied {$result['fields']} fields of {$result['items']} items from {$from} into {$to}. Existing translations were kept.");

        return self::SUCCESS;
    }
}
