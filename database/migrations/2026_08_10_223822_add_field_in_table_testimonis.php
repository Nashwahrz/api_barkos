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
        Schema::table('testimonis', function (Blueprint $table) {
            $table->string('nama');
            $table->string('pekerjaan');
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan'])->nullable();
            $table->date('tanggal_lahir');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonis', function (Blueprint $table) {
            table->dropColumn(['nama', 'pekerjaan', 'jenis_kelamin', 'tanggal_lahir']);
        });
    }
};
