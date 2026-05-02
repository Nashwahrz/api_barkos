<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes to the chats table.
     * These indexes speed up the most frequent queries:
     * - conversations() filters by sender_id OR receiver_id
     * - unreadCount() filters by receiver_id + is_read
     * - messages() filters by product_id + sender_id + receiver_id
     */
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // For conversations() subquery: grouping by product + user pair
            $table->index(['product_id', 'sender_id', 'receiver_id'], 'chats_product_users_idx');

            // For unreadCount() and conversations() unread aggregation
            $table->index(['receiver_id', 'is_read'], 'chats_receiver_read_idx');

            // For latest() ordering in conversations
            $table->index(['sender_id', 'created_at'], 'chats_sender_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('chats_product_users_idx');
            $table->dropIndex('chats_receiver_read_idx');
            $table->dropIndex('chats_sender_time_idx');
        });
    }
};
