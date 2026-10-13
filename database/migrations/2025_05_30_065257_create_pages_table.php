<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->json('blocks')->nullable();
            $table->string('content_type')->default('blocks');
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->integer('level')->default(0);
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('hide_in_menu')->default(false);
            $table->boolean('is_root')->default(false);
            $table->string('template')->default('container'); // full_width or container
            $table->string('subtitle')->nullable();
            $table->string('nav_title')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('external_url_new_window')->default(false);
            $table->string('meta_author')->nullable();
            $table->boolean('include_in_search')->default(true);
            $table->string('header_image')->nullable();
            $table->text('extra_css')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_robots')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('aria_label')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // category tables are now in their own migrations
        Schema::dropIfExists('pages');
    }
};
