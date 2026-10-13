<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirects (Settings → SEO → Redirects): old addresses that lead to a
 * new one with 301 or 302 — entered by hand or created automatically
 * when the address of a page or post changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('redirects')) {
            return;
        }

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            // Path on this site without language prefix ("/old-page")
            $table->string('source_path', 255);
            // Only in this content language (translated addresses); null = every language
            $table->string('locale', 12)->nullable();
            // Path on this site ("/new-page") or absolute address
            $table->text('target');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            // Created by the system when an address changed
            $table->boolean('is_automatic')->default(false);
            $table->timestamps();

            $table->index(['source_path', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
