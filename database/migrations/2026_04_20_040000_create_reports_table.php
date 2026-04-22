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
            $blueprint->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
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
