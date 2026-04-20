<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Get chat history for a specific product.
     */
    public function index(Product $product): AnonymousResourceCollection
    {
        $chats = Chat::with(['sender', 'receiver', 'product'])
            ->where('product_id', $product->id)
            ->where(function ($query) {
                $query->where('sender_id', Auth::id())
                    ->orWhere('receiver_id', Auth::id());
            })
            ->oldest()
            ->get();

        return ChatResource::collection($chats);
    }

    /**
     * Send a new message.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $chat = Chat::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $product->user_id,
            'product_id' => $product->id,
            'message' => $request->message,
        ]);

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new ChatResource($chat->load(['sender', 'receiver', 'product'])),
        ], 201);
    }
}
