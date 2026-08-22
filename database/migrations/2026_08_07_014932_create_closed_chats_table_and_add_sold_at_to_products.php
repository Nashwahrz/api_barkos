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
            $table->timestamp('sold_at')->nullable()->after('status_terjual');
        });

        Schema::create('closed_chats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unsignedInteger('buyer_id');
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'buyer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closed_chats');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });
    }
};
