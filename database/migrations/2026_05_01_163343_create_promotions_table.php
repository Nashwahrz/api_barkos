<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1.5b — TRD §8.1
     * Promotions — records of sellers purchasing promotion packages for their products.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('package_id');
            $table->foreign('package_id')->references('id')->on('promotion_packages')->onDelete('cascade');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->decimal('amount_paid', 12, 2);
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
