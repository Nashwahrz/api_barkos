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
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nama');
            $table->renameColumn('avatar', 'foto_profil');
            $table->renameColumn('phone', 'no_telepon');
            $table->renameColumn('is_active', 'aktif');
            $table->renameColumn('identity_document_path', 'jalur_dokumen_identitas');
            $table->renameColumn('is_identity_verified', 'identitas_terverifikasi');
            $table->renameColumn('last_active_at', 'terakhir_aktif_pada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('foto_profil', 'avatar');
            $table->renameColumn('no_telepon', 'phone');
            $table->renameColumn('aktif', 'is_active');
            $table->renameColumn('jalur_dokumen_identitas', 'identity_document_path');
            $table->renameColumn('identitas_terverifikasi', 'is_identity_verified');
            $table->renameColumn('terakhir_aktif_pada', 'last_active_at');
        });
    }
};
