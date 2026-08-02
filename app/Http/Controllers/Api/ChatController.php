<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Http\Resources\UserResource;
use App\Models\Chat;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Get all unique conversations for the authenticated user.
     * Fixed: N+1 query resolved by fetching unread counts in a single query.
     */
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        // Subquery: get the latest message ID per conversation (product + user pair)
        $subQuery = Chat::select(
            DB::raw('MAX(id) as max_id'),
            'product_id',
            DB::raw('LEAST(sender_id, receiver_id) as user_a'),
            DB::raw('GREATEST(sender_id, receiver_id) as user_b')
        )
        ->where('sender_id', $userId)
        ->orWhere('receiver_id', $userId)
        ->groupBy('product_id', 'user_a', 'user_b');

        $latestMessageIds = DB::table($subQuery, 'sub')->pluck('max_id');

        $chats = Chat::with(['sender', 'receiver', 'product.user', 'product.category'])
            ->whereIn('id', $latestMessageIds)
            ->latest()
            ->get();

        // Fix N+1: fetch ALL unread counts in a single aggregated query, keyed by product_id + sender_id
        $unreadCounts = Chat::select(
                'product_id',
                'sender_id',
                DB::raw('COUNT(*) as unread_count')
            )
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->groupBy('product_id', 'sender_id')
            ->get()
            ->keyBy(fn($row) => $row->product_id . '_' . $row->sender_id);

        $result = $chats->map(function ($chat) use ($userId, $unreadCounts) {
            $otherUserId = ($chat->sender_id === $userId) ? $chat->receiver_id : $chat->sender_id;

            $key = $chat->product_id . '_' . $otherUserId;
            $unreadCount = $unreadCounts->get($key)?->unread_count ?? 0;

            return [
                'last_message' => new ChatResource($chat),
                'other_user'   => new UserResource(($chat->sender_id === $userId) ? $chat->receiver : $chat->sender),
                'unread_count' => (int) $unreadCount,
            ];
        });

        return response()->json(['data' => $result]);
    }

    /**
     * Get chat history between the auth user and another user for a specific product.
     */
    public function messages(Product $product, User $user): AnonymousResourceCollection
    {
        $authId  = Auth::id();
        $otherId = $user->id;

        $chats = Chat::with(['sender', 'receiver', 'product', 'replyTo.sender'])
            ->where('product_id', $product->id)
            ->where(function ($query) use ($authId, $otherId) {
                $query->where(function ($q) use ($authId, $otherId) {
                    $q->where('sender_id', $authId)->where('receiver_id', $otherId);
                })->orWhere(function ($q) use ($authId, $otherId) {
                    $q->where('sender_id', $otherId)->where('receiver_id', $authId);
                });
            })
            ->oldest()
            ->get();

        return ChatResource::collection($chats);
    }

    /**
     * Mark messages from another user as read for a specific product.
     */
    public function markAsRead(Product $product, User $user): JsonResponse
    {
        Chat::where('product_id', $product->id)
            ->where('sender_id', $user->id)
            ->where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    /**
     * Get total unread messages count.
     */
    public function unreadCount(): JsonResponse
    {
        $count = Chat::where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Send a new message.
     * Fixed: sellers can now reply to buyers; self-chat is prevented.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'message'     => 'required|string|max:2000',
            'receiver_id' => 'required|exists:users,id',
            'reply_to_id' => 'nullable|integer|exists:chats,id',
        ]);

        $senderId   = Auth::id();
        $receiverId = (int) $request->receiver_id;

        // Prevent sending message to yourself
        if ($receiverId === $senderId) {
            return response()->json([
                'message' => 'Tidak bisa mengirim pesan ke diri sendiri.',
            ], 422);
        }

        // Validate that the conversation is valid for this product:
        // Either the sender is the owner (replying to buyer), or the receiver is the owner (buyer messaging seller).
        $isOwner        = $product->user_id === $senderId;
        $receiverIsOwner = $product->user_id === $receiverId;

        if (!$isOwner && !$receiverIsOwner) {
            return response()->json([
                'message' => 'Tidak dapat mengirim pesan pada konteks produk ini.',
            ], 403);
        }

        // Reply must belong to the same product conversation.
        $replyToId = $request->reply_to_id;
        if ($replyToId) {
            $replyTarget = Chat::where('id', $replyToId)->where('product_id', $product->id)->first();
            if (!$replyTarget) {
                $replyToId = null;
            }
        }

        $chat = Chat::create([
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'product_id'  => $product->id,
            'message'     => $request->message,
            'reply_to_id' => $replyToId,
            'is_read'     => false,
        ]);

        $receiver = User::find($receiverId);
        if ($receiver) {
            $receiver->notify(new \App\Notifications\ChatNotification($chat));
        }

        return response()->json([
            'message' => 'Message sent successfully',
            'data'    => new ChatResource($chat->load(['sender', 'receiver', 'product', 'replyTo.sender'])),
        ], 201);
    }

    /**
     * Delete a single message. Only a participant of the conversation may delete it,
     * and deletion is permanent for both sides.
     */
    public function destroy(Chat $chat): JsonResponse
    {
        $authId = Auth::id();

        if ($chat->sender_id !== $authId && $chat->receiver_id !== $authId) {
            return response()->json(['message' => 'Tidak diizinkan menghapus pesan ini.'], 403);
        }

        $chat->delete();

        return response()->json(['message' => 'Pesan berhasil dihapus']);
    }

    /**
     * Delete the entire chat history between the auth user and another user for a
     * specific product. Permanent for both sides.
     */
    public function destroyConversation(Product $product, User $user): JsonResponse
    {
        $authId  = Auth::id();
        $otherId = $user->id;

        Chat::where('product_id', $product->id)
            ->where(function ($query) use ($authId, $otherId) {
                $query->where(function ($q) use ($authId, $otherId) {
                    $q->where('sender_id', $authId)->where('receiver_id', $otherId);
                })->orWhere(function ($q) use ($authId, $otherId) {
                    $q->where('sender_id', $otherId)->where('receiver_id', $authId);
                });
            })
            ->delete();

        return response()->json(['message' => 'Percakapan berhasil dihapus']);
    }
}
