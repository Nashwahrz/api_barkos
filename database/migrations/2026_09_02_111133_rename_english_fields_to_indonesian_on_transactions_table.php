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
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('payment_method', 'metode_pembayaran');
            $table->renameColumn('payment_proof_path', 'jalur_bukti_pembayaran');
            $table->renameColumn('agreed_price', 'harga_disepakati');
            $table->renameColumn('notes', 'catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('metode_pembayaran', 'payment_method');
            $table->renameColumn('jalur_bukti_pembayaran', 'payment_proof_path');
            $table->renameColumn('harga_disepakati', 'agreed_price');
            $table->renameColumn('catatan', 'notes');
        });
    }
};
