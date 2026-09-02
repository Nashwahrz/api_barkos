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
        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('message', 'pesan');
            $table->renameColumn('is_read', 'sudah_dibaca');
            $table->renameColumn('reply_to_id', 'id_balasan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('pesan', 'message');
            $table->renameColumn('sudah_dibaca', 'is_read');
            $table->renameColumn('id_balasan', 'reply_to_id');
        });
    }
};
