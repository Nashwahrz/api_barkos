<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Previously product_id was NOT NULL with ON DELETE CASCADE, so deleting a
     * product (e.g. an admin removing a reported listing) silently wiped every
     * chat thread about it — including any moderation message sent moments
     * before. Switching to nullable + SET NULL keeps chat history (and the
     * product relation just resolves to null, same as an already-deleted
     * report's product).
     */
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        DB::statement('ALTER TABLE chats MODIFY product_id INT UNSIGNED NULL');

        Schema::table('chats', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    /**
     * 
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        DB::statement('ALTER TABLE chats MODIFY product_id INT UNSIGNED NOT NULL');

        Schema::table('chats', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
