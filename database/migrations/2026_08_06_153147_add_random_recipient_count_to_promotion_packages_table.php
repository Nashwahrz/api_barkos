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
            $table->unsignedInteger('random_recipient_count')->nullable()->after('quota_impressions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_packages', function (Blueprint $table) {
            $table->dropColumn('random_recipient_count');
        });
    }
};
