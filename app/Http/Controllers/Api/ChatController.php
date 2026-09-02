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
            DB::raw('MAX(id_obrolan) as max_id'),
            'id_produk',
            DB::raw('LEAST(id_pengirim, id_penerima) as user_a'),
            DB::raw('GREATEST(id_pengirim, id_penerima) as user_b')
        )
        ->where('id_pengirim', $userId)
        ->orWhere('id_penerima', $userId)
        ->groupBy('id_produk', 'user_a', 'user_b');

        $latestMessageIds = DB::table($subQuery, 'sub')->pluck('max_id');

        $chats = Chat::with(['sender', 'receiver', 'product.user', 'product.category'])
            ->whereIn('id_obrolan', $latestMessageIds)
            ->latest()
            ->get();

        // Fix N+1: fetch ALL unread counts in a single aggregated query, keyed by id_produk + id_pengirim
        $unreadCounts = Chat::select(
                'id_produk',
                'id_pengirim',
                DB::raw('COUNT(*) as unread_count')
            )
            ->where('id_penerima', $userId)
            ->where('sudah_dibaca', false)
            ->groupBy('id_produk', 'id_pengirim')
            ->get()
            ->keyBy(fn($row) => $row->id_produk . '_' . $row->id_pengirim);

        $result = $chats->map(function ($chat) use ($userId, $unreadCounts) {
            $otherUserId = ($chat->id_pengirim === $userId) ? $chat->id_penerima : $chat->id_pengirim;

            $key = $chat->id_produk . '_' . $otherUserId;
            $unreadCount = $unreadCounts->get($key)?->unread_count ?? 0;

            return [
                'last_message' => new ChatResource($chat),
                'other_user'   => new UserResource(($chat->id_pengirim === $userId) ? $chat->receiver : $chat->sender),
                'unread_count' => (int) $unreadCount,
            ];
        });

        return response()->json(['data' => $result]);
    }

    /**
     * Get chat history between the auth user and another user for a specific product.
     * Takes the raw product ID (not route-model-bound) since a thread must remain
     * readable even after the product itself has been deleted (e.g. by moderation).
     */
    public function messages($productId, User $user): AnonymousResourceCollection
    {
        $authId  = Auth::id();
        $otherId = $user->id;

        $chats = Chat::with(['sender', 'receiver', 'product', 'replyTo.sender'])
            ->when($productId == 0 || $productId === '0', function ($q) {
                $q->whereNull('id_produk');
            }, function ($q) use ($productId) {
                $q->where('id_produk', $productId);
            })
            ->where(function ($query) use ($authId, $otherId) {
                $query->where(function ($q) use ($authId, $otherId) {
                    $q->where('id_pengirim', $authId)->where('id_penerima', $otherId);
                })->orWhere(function ($q) use ($authId, $otherId) {
                    $q->where('id_pengirim', $otherId)->where('id_penerima', $authId);
                });
            })
            ->oldest()
            ->get();

        return ChatResource::collection($chats);
    }

    /**
     * Mark messages from another user as read for a specific product.
     */
    public function markAsRead($productId, User $user): JsonResponse
    {
        Chat::where('id_produk', $productId)
            ->where('id_pengirim', $user->id)
            ->where('id_penerima', Auth::id())
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    /**
     * Get total unread messages count.
     */
    public function unreadCount(): JsonResponse
    {
        $count = Chat::where('id_penerima', Auth::id())
            ->where('sudah_dibaca', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Send a new message.
     * Fixed: sellers can now reply to buyers; self-chat is prevented.
     */
    public function store(Request $request, $productId): JsonResponse
    {
        $request->validate([
            'message'     => 'required|string|max:2000',
            'receiver_id' => 'required|exists:users,id',
            'reply_to_id' => 'nullable|integer|exists:obrolan,id_obrolan',
        ]);

        $senderId   = Auth::id();
        $receiverId = (int) $request->receiver_id;

        // Prevent sending message to yourself
        if ($receiverId === $senderId) {
            return response()->json([
                'message' => 'Tidak bisa mengirim pesan ke diri sendiri.',
            ], 422);
        }

        // Admins may message any user regardless of product ownership (moderation
        // follow-ups) — everyone else must be a participant of this product's
        // buyer/seller conversation, and the product must still exist.
        if ($request->user()->role !== 'super_admin') {
            $product = Product::find($productId);
            if (!$product) {
                return response()->json(['message' => 'Produk ini sudah tidak tersedia.'], 404);
            }

            $isOwner         = $product->id_pengguna === $senderId;
            $receiverIsOwner = $product->id_pengguna === $receiverId;

            if (!$isOwner && !$receiverIsOwner) {
                return response()->json([
                    'message' => 'Tidak dapat mengirim pesan pada konteks produk ini.',
                ], 403);
            }

            // Determine who the buyer is in this context
            $buyerId = $isOwner ? $receiverId : $senderId;

            // Check if chat is closed manually by the seller
            $isClosed = DB::table('obrolan_selesai')
                ->where('id_produk', $productId)
                ->where('id_pembeli', $buyerId)
                ->exists();

            if ($isClosed) {
                return response()->json(['message' => 'Sesi percakapan ini telah ditutup oleh penjual.'], 403);
            }

            // Check if chat is closed automatically (3 days after product sold)
            if ($product->status_terjual && $product->terjual_pada && \Carbon\Carbon::parse($product->terjual_pada)->addDays(3)->isPast()) {
                return response()->json(['message' => 'Sesi percakapan ini telah otomatis ditutup (3 hari sejak produk terjual).'], 403);
            }
        }

        // Reply must belong to the same product conversation.
        $replyToId = $request->reply_to_id;
        if ($replyToId) {
            $replyTarget = Chat::where('id_obrolan', $replyToId)->where('id_produk', $productId)->first();
            if (!$replyTarget) {
                $replyToId = null;
            }
        }

        $chat = Chat::create([
            'id_pengirim' => $senderId,
            'id_penerima' => $receiverId,
            'id_produk'   => $productId,
            'pesan'       => $request->message,
            'id_balasan'  => $replyToId,
            'sudah_dibaca' => false,
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

        if ($chat->id_pengirim !== $authId && $chat->id_penerima !== $authId) {
            return response()->json(['message' => 'Tidak diizinkan menghapus pesan ini.'], 403);
        }

        $chat->delete();

        return response()->json(['message' => 'Pesan berhasil dihapus']);
    }

    /**
     * Delete the entire chat history between the auth user and another user for a
     * specific product. Permanent for both sides.
     */
    public function destroyConversation($productId, User $user): JsonResponse
    {
        $authId  = Auth::id();
        $otherId = $user->id;

        Chat::where('id_produk', $productId)
            ->where(function ($query) use ($authId, $otherId) {
                $query->where(function ($q) use ($authId, $otherId) {
                    $q->where('id_pengirim', $authId)->where('id_penerima', $otherId);
                })->orWhere(function ($q) use ($authId, $otherId) {
                    $q->where('id_pengirim', $otherId)->where('id_penerima', $authId);
                });
            })
            ->delete();

        return response()->json(['message' => 'Percakapan berhasil dihapus']);
    }

    /**
     * Get chat status (whether it is closed manually or automatically).
     */
    public function chatStatus($productId, User $user): JsonResponse
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['is_closed' => false, 'message' => 'Product not found']);
        }

        $authId = Auth::id();
        $isOwner = $product->id_pengguna === $authId;

        // If auth user is not the owner and not the other user (e.g. admin), they can just view it.
        $buyerId = $isOwner ? $user->id : $authId;

        $isManuallyClosed = DB::table('obrolan_selesai')
            ->where('id_produk', $productId)
            ->where('id_pembeli', $buyerId)
            ->exists();

        $isAutoClosed = $product->status_terjual && $product->terjual_pada && \Carbon\Carbon::parse($product->terjual_pada)->addDays(3)->isPast();

        return response()->json([
            'is_closed' => $isManuallyClosed || $isAutoClosed,
            'is_manually_closed' => $isManuallyClosed,
            'is_auto_closed' => $isAutoClosed,
        ]);
    }

    /**
     * Close a chat (Only seller can close it for a specific buyer).
     */
    public function closeChat($productId, User $user): JsonResponse
    {
        $product = Product::find($productId);
        if (!$product || $product->id_pengguna !== Auth::id()) {
            return response()->json(['message' => 'Tidak diizinkan menutup percakapan ini.'], 403);
        }

        DB::table('obrolan_selesai')->updateOrInsert(
            ['id_produk' => $productId, 'id_pembeli' => $user->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['message' => 'Percakapan berhasil ditutup']);
    }
}
