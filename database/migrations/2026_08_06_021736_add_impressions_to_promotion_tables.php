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
        Schema::table('promotion_packages', function (Blueprint $table) {
            $table->integer('quota_impressions')->nullable()->after('duration_days');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->integer('max_impressions')->nullable()->after('end_at');
            $table->integer('current_impressions')->default(0)->after('max_impressions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['max_impressions', 'current_impressions']);
        });
        
        Schema::table('promotion_packages', function (Blueprint $table) {
            $table->dropColumn(['quota_impressions']);
        });
    }
};
