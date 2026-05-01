<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1.1 — TRD §8.1
     * Add missing profile and location fields to users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // phone and avatar already exist — only add truly missing columns
            $table->boolean('is_active')->default(true)->after('asal_kampus');
            $table->decimal('latitude', 10, 7)->nullable()->after('is_active');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'latitude', 'longitude']);
        });
    }
};
