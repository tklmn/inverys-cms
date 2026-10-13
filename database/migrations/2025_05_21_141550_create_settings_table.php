<?php

// Migration to create the settings table for global application settings

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This migration creates the 'settings' table with key, value, type, and description columns
return new class extends Migration
{
    /**
     * Run the migrations.
     * This method creates the settings table
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // Primary key
            $table->id();
            // Unique key for the setting (e.g. 'language')
            $table->string('key')->unique();
            // Value for the setting (e.g. 'en', 'de')
            $table->string('value');
            // Optional type and description for the setting
            $table->string('type')->nullable();
            $table->string('description')->nullable();
            // Timestamps for created_at and updated_at
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     * This method drops the settings table
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
