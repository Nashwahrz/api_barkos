<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('categories', 'kategori');
        Schema::rename('products', 'produk');
        Schema::rename('chats', 'obrolan');
        Schema::rename('reports', 'laporan');
        Schema::rename('product_images', 'gambar_produk');
        Schema::rename('transactions', 'transaksi');
        Schema::rename('promotion_packages', 'paket_promosi');
        Schema::rename('promotions', 'promosi');
        Schema::rename('bank_accounts', 'rekening_bank');
        Schema::rename('offers', 'tawaran');
        Schema::rename('payment_settings', 'pengaturan_pembayaran');
        Schema::rename('payment_bank_accounts', 'rekening_bank_pembayaran');
        Schema::rename('favorites', 'favorit');
        Schema::rename('closed_chats', 'obrolan_selesai');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('kategori', 'categories');
        Schema::rename('produk', 'products');
        Schema::rename('obrolan', 'chats');
        Schema::rename('laporan', 'reports');
        Schema::rename('gambar_produk', 'product_images');
        Schema::rename('transaksi', 'transactions');
        Schema::rename('paket_promosi', 'promotion_packages');
        Schema::rename('promosi', 'promotions');
        Schema::rename('rekening_bank', 'bank_accounts');
        Schema::rename('tawaran', 'offers');
        Schema::rename('pengaturan_pembayaran', 'payment_settings');
        Schema::rename('rekening_bank_pembayaran', 'payment_bank_accounts');
        Schema::rename('favorit', 'favorites');
        Schema::rename('obrolan_selesai', 'closed_chats');
    }
};
