<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
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
     */
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        // Subquery to find the latest message ID for each conversation (product_id + pair of users)
        // We use a trick: LEAST(sender_id, receiver_id) and GREATEST(sender_id, receiver_id) 
        // to treat (A, B) and (B, A) as the same pair.
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

        // Add unread count for each conversation
        $result = $chats->map(function ($chat) use ($userId) {
            $otherUserId = ($chat->sender_id === $userId) ? $chat->receiver_id : $chat->sender_id;
            
            $unreadCount = Chat::where('product_id', $chat->product_id)
                ->where('sender_id', $otherUserId)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            return [
                'last_message' => new ChatResource($chat),
                'other_user' => ($chat->sender_id === $userId) ? $chat->receiver : $chat->sender,
                'unread_count' => $unreadCount
            ];
        });

        return response()->json([
            'data' => $result
        ]);
    }

    /**
     * Get chat history between the auth user and another user for a specific product.
     */
    public function messages(Product $product, User $user): AnonymousResourceCollection
    {
        $authId = Auth::id();
        $otherId = $user->id;

        $chats = Chat::with(['sender', 'receiver', 'product'])
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
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'receiver_id' => 'sometimes|exists:users,id'
        ]);

        // If receiver_id is not provided, default to product owner
        $receiverId = $request->receiver_id ?? $product->user_id;

        if ($receiverId == Auth::id()) {
            // If user is owner, they are replying to someone. 
            // In a real app, we'd need to know who they are replying to.
            // For now, assume receiver_id must be provided if the user is the owner.
            return response()->json(['message' => 'Receiver ID is required for product owners'], 422);
        }

        $chat = Chat::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $receiverId,
            'product_id' => $product->id,
            'message' => $request->message,
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new ChatResource($chat->load(['sender', 'receiver', 'product'])),
        ], 201);
    }
}
