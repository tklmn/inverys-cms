<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('cms_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('cms_notifications')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at');

            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_notification_reads');
        Schema::dropIfExists('cms_notifications');
    }
};
