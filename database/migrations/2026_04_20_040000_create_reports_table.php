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
        Schema::create('reports', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('reporter_id');
            $blueprint->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
            $blueprint->unsignedBigInteger('product_id')->nullable();
            $blueprint->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $blueprint->string('reason');
            $blueprint->text('description')->nullable();
            $blueprint->enum('status', ['pending', 'investigated', 'resolved', 'dismissed'])->default('pending');
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
