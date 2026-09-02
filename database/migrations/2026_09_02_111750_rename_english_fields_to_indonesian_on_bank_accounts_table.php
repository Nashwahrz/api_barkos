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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->renameColumn('bank_name', 'nama_bank');
            $table->renameColumn('account_number', 'nomor_rekening');
            $table->renameColumn('account_name', 'nama_pemilik_rekening');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->renameColumn('nama_bank', 'bank_name');
            $table->renameColumn('nomor_rekening', 'account_number');
            $table->renameColumn('nama_pemilik_rekening', 'account_name');
        });
    }
};
