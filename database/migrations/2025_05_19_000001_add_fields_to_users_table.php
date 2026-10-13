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
        Schema::table('users', function (Blueprint $table) {
            // Add first_name and last_name fields (splitting the name field)
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');

            // Add phone number field
            $table->string('phone')->nullable()->after('email');

            // Add profile information
            $table->string('job_title')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('job_title');

            // Add status field
            $table->boolean('is_active')->default(true)->after('bio');

            // Add avatar field
            $table->string('avatar')->nullable()->after('is_active');

            // Add last login timestamp
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'job_title',
                'bio',
                'is_active',
                'avatar',
                'last_login_at',
            ]);
        });
    }
};
