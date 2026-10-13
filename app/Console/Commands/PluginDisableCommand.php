<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use Illuminate\Console\Command;

class PluginDisableCommand extends Command
{
    protected $signature = 'plugin:disable {slug : The plugin slug to disable}';

    protected $description = 'Disable a plugin (useful for recovery when a broken plugin crashes the admin panel)';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            $this->error("Plugin '{$slug}' not found.");
            $this->newLine();
            $this->info('Installed plugins:');
            Plugin::all()->each(fn ($p) => $this->line("  {$p->slug} — ".($p->is_enabled ? 'enabled' : 'disabled')));

            return self::FAILURE;
        }

        if (! $plugin->is_enabled) {
            $this->warn("Plugin '{$slug}' is already disabled.");

            return self::SUCCESS;
        }

        $plugin->update([
            'is_enabled' => false,
            'enabled_at' => null,
        ]);

        $this->info("Plugin '{$slug}' has been disabled.");

        return self::SUCCESS;
    }
}
