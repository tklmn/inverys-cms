<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['published_at']);
            $table->dropColumn(['type', 'excerpt', 'published_at', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('type', 20)->default('page')->after('id');
            $table->text('excerpt')->nullable()->after('content');
            $table->timestamp('published_at')->nullable()->after('excerpt');
            $table->foreignId('author_id')->nullable()->after('published_at')
                ->constrained('users')->nullOnDelete();

            $table->index('type');
            $table->index('published_at');
        });
    }
};
