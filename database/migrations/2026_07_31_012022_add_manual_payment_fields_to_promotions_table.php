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
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('payment_method', 50)->default('midtrans')->after('payment_status');
            $table->string('manual_proof_path', 100)->nullable()->after('payment_method');
            $table->string('manual_review_status', 100)->default('none')->after('manual_proof_path');
            $table->text('ocr_note')->nullable()->after('manual_review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'manual_proof_path', 'manual_review_status', 'ocr_note']);
        });
    }
};
