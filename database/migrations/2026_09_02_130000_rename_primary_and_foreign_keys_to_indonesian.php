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
        Schema::table('kategori', function (Blueprint $table) {
            $table->renameColumn('id', 'id_kategori');
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->renameColumn('id', 'id_produk');
            $table->renameColumn('user_id', 'id_pengguna');
            $table->renameColumn('category_id', 'id_kategori');
        });

        Schema::table('obrolan', function (Blueprint $table) {
            $table->renameColumn('id', 'id_obrolan');
            $table->renameColumn('sender_id', 'id_pengirim');
            $table->renameColumn('receiver_id', 'id_penerima');
            $table->renameColumn('product_id', 'id_produk');
        });

        Schema::table('laporan', function (Blueprint $table) {
            $table->renameColumn('id', 'id_laporan');
            $table->renameColumn('reporter_id', 'id_pelapor');
            $table->renameColumn('product_id', 'id_produk');
        });

        Schema::table('gambar_produk', function (Blueprint $table) {
            $table->renameColumn('id', 'id_gambar_produk');
            $table->renameColumn('product_id', 'id_produk');
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->renameColumn('id', 'id_transaksi');
            $table->renameColumn('product_id', 'id_produk');
            $table->renameColumn('buyer_id', 'id_pembeli');
            $table->renameColumn('seller_id', 'id_penjual');
        });

        Schema::table('paket_promosi', function (Blueprint $table) {
            $table->renameColumn('id', 'id_paket_promosi');
        });

        Schema::table('promosi', function (Blueprint $table) {
            $table->renameColumn('id', 'id_promosi');
            $table->renameColumn('product_id', 'id_produk');
            $table->renameColumn('seller_id', 'id_penjual');
            $table->renameColumn('package_id', 'id_paket_promosi');
        });

        Schema::table('rekening_bank', function (Blueprint $table) {
            $table->renameColumn('id', 'id_rekening_bank');
            $table->renameColumn('user_id', 'id_pengguna');
        });

        Schema::table('tawaran', function (Blueprint $table) {
            $table->renameColumn('id', 'id_tawaran');
            $table->renameColumn('product_id', 'id_produk');
            $table->renameColumn('buyer_id', 'id_pembeli');
            $table->renameColumn('seller_id', 'id_penjual');
        });

        Schema::table('pengaturan_pembayaran', function (Blueprint $table) {
            $table->renameColumn('id', 'id_pengaturan_pembayaran');
        });

        Schema::table('rekening_bank_pembayaran', function (Blueprint $table) {
            $table->renameColumn('id', 'id_rekening_bank_pembayaran');
        });

        Schema::table('favorit', function (Blueprint $table) {
            $table->renameColumn('id', 'id_favorit');
            $table->renameColumn('user_id', 'id_pengguna');
            $table->renameColumn('product_id', 'id_produk');
        });

        Schema::table('obrolan_selesai', function (Blueprint $table) {
            $table->renameColumn('id', 'id_obrolan_selesai');
            $table->renameColumn('product_id', 'id_produk');
            $table->renameColumn('buyer_id', 'id_pembeli');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kategori', function (Blueprint $table) {
            $table->renameColumn('id_kategori', 'id');
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->renameColumn('id_produk', 'id');
            $table->renameColumn('id_pengguna', 'user_id');
            $table->renameColumn('id_kategori', 'category_id');
        });

        Schema::table('obrolan', function (Blueprint $table) {
            $table->renameColumn('id_obrolan', 'id');
            $table->renameColumn('id_pengirim', 'sender_id');
            $table->renameColumn('id_penerima', 'receiver_id');
            $table->renameColumn('id_produk', 'product_id');
        });

        Schema::table('laporan', function (Blueprint $table) {
            $table->renameColumn('id_laporan', 'id');
            $table->renameColumn('id_pelapor', 'reporter_id');
            $table->renameColumn('id_produk', 'product_id');
        });

        Schema::table('gambar_produk', function (Blueprint $table) {
            $table->renameColumn('id_gambar_produk', 'id');
            $table->renameColumn('id_produk', 'product_id');
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->renameColumn('id_transaksi', 'id');
            $table->renameColumn('id_produk', 'product_id');
            $table->renameColumn('id_pembeli', 'buyer_id');
            $table->renameColumn('id_penjual', 'seller_id');
        });

        Schema::table('paket_promosi', function (Blueprint $table) {
            $table->renameColumn('id_paket_promosi', 'id');
        });

        Schema::table('promosi', function (Blueprint $table) {
            $table->renameColumn('id_promosi', 'id');
            $table->renameColumn('id_produk', 'product_id');
            $table->renameColumn('id_penjual', 'seller_id');
            $table->renameColumn('id_paket_promosi', 'package_id');
        });

        Schema::table('rekening_bank', function (Blueprint $table) {
            $table->renameColumn('id_rekening_bank', 'id');
            $table->renameColumn('id_pengguna', 'user_id');
        });

        Schema::table('tawaran', function (Blueprint $table) {
            $table->renameColumn('id_tawaran', 'id');
            $table->renameColumn('id_produk', 'product_id');
            $table->renameColumn('id_pembeli', 'buyer_id');
            $table->renameColumn('id_penjual', 'seller_id');
        });

        Schema::table('pengaturan_pembayaran', function (Blueprint $table) {
            $table->renameColumn('id_pengaturan_pembayaran', 'id');
        });

        Schema::table('rekening_bank_pembayaran', function (Blueprint $table) {
            $table->renameColumn('id_rekening_bank_pembayaran', 'id');
        });

        Schema::table('favorit', function (Blueprint $table) {
            $table->renameColumn('id_favorit', 'id');
            $table->renameColumn('id_pengguna', 'user_id');
            $table->renameColumn('id_produk', 'product_id');
        });

        Schema::table('obrolan_selesai', function (Blueprint $table) {
            $table->renameColumn('id_obrolan_selesai', 'id');
            $table->renameColumn('id_produk', 'product_id');
            $table->renameColumn('id_pembeli', 'buyer_id');
        });
    }
};
