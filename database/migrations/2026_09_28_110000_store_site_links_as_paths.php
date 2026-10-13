<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu items pointing to the site itself (e.g. "Blog") were stored with
 * the full address (APP_URL/blog). After a domain or directory change
 * they led nowhere, and the language prefix was missing.
 * Here they are converted to the path — the Page model stores new
 * entries that way anyway and resolves the path when reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        $root = rtrim((string) config('app.url'), '/');
        if ($root === '') {
            return;
        }

        DB::table('pages')
            ->where(fn ($query) => $query->where('external_url', $root)->orWhere('external_url', 'like', $root.'/%'))
            ->orderBy('id')
            ->each(function ($page) use ($root) {
                DB::table('pages')->where('id', $page->id)->update([
                    'external_url' => substr($page->external_url, strlen($root)) ?: '/',
                ]);
            });
    }

    public function down(): void
    {
        // Paths stay valid — nothing to roll back
    }
};
