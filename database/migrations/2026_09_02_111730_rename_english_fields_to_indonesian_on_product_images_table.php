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
        Schema::table('product_images', function (Blueprint $table) {
            $table->renameColumn('image_path', 'jalur_gambar');
            $table->renameColumn('is_primary', 'utama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->renameColumn('jalur_gambar', 'image_path');
            $table->renameColumn('utama', 'is_primary');
        });
    }
};
