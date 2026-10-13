<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['revisionable_type', 'revisionable_id']);
            $table->index('user_id');
            $table->index('created_at');
        });

        // Seed default revision limit setting
        Setting::firstOrCreate(
            ['key' => 'revisions.max_per_model'],
            ['value' => '50', 'type' => 'integer', 'description' => 'Maximum number of revisions to keep per model instance']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
        Setting::where('key', 'revisions.max_per_model')->delete();
    }
};
