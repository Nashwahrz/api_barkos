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
            $table->renameColumn('name', 'nama');
            $table->renameColumn('duration_days', 'durasi_hari');
            $table->renameColumn('price', 'harga');
            $table->renameColumn('is_active', 'aktif');
            $table->renameColumn('random_recipient_count', 'jumlah_penerima_acak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_packages', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('durasi_hari', 'duration_days');
            $table->renameColumn('harga', 'price');
            $table->renameColumn('aktif', 'is_active');
            $table->renameColumn('jumlah_penerima_acak', 'random_recipient_count');
        });
    }
};
