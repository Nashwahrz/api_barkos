<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The impression-quota system (cap the number of times a promoted product
     * could appear before auto-expiring) turned out to be dead weight — removed
     * in favor of just the day-based duration + random-recipient targeting.
     */
    public function up(): void
    {
        Schema::table('promotion_packages', function (Blueprint $table) {
            $table->dropColumn('quota_impressions');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['max_impressions', 'current_impressions']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_packages', function (Blueprint $table) {
            $table->unsignedInteger('quota_impressions')->nullable()->after('duration_days');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('max_impressions')->nullable();
            $table->unsignedInteger('current_impressions')->default(0);
        });
    }
};
