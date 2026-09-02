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
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->renameColumn('midtrans_enabled', 'midtrans_diaktifkan');
            $table->renameColumn('manual_transfer_enabled', 'transfer_manual_diaktifkan');
            $table->renameColumn('qris_image_path', 'jalur_gambar_qris');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->renameColumn('midtrans_diaktifkan', 'midtrans_enabled');
            $table->renameColumn('transfer_manual_diaktifkan', 'manual_transfer_enabled');
            $table->renameColumn('jalur_gambar_qris', 'qris_image_path');
        });
    }
};
