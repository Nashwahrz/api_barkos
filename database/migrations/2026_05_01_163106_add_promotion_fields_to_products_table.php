<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1.2 — TRD §8.1
     * Add promotion fields to products table.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_promoted')->default(false)->after('status_terjual');
            $table->timestamp('promoted_until')->nullable()->after('is_promoted');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_promoted', 'promoted_until']);
        });
    }
};
