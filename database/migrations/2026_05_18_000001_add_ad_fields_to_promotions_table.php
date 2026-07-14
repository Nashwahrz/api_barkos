<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 6.2 — Add advertisement (iklan) fields to promotions.
     * Sellers can optionally attach an image or video ad with a title.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->enum('ad_type', ['none', 'image', 'video'])->default('none')->after('status');
            $table->text('ad_media_url')->nullable()->after('ad_type');   // URL to image/video
            $table->string('ad_title', 200)->nullable()->after('ad_media_url'); // Optional ad headline
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['ad_type', 'ad_media_url', 'ad_title']);
        });
    }
};
