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
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('minimum_offer_price', 'harga_minimum_tawaran');
            $table->renameColumn('is_offer_enabled', 'tawaran_diaktifkan');
            $table->renameColumn('sold_at', 'terjual_pada');
            $table->renameColumn('is_promoted', 'dipromosikan');
            $table->renameColumn('promoted_until', 'dipromosikan_hingga');
            $table->renameColumn('payment_method', 'metode_pembayaran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('harga_minimum_tawaran', 'minimum_offer_price');
            $table->renameColumn('tawaran_diaktifkan', 'is_offer_enabled');
            $table->renameColumn('terjual_pada', 'sold_at');
            $table->renameColumn('dipromosikan', 'is_promoted');
            $table->renameColumn('dipromosikan_hingga', 'promoted_until');
            $table->renameColumn('metode_pembayaran', 'payment_method');
        });
    }
};
