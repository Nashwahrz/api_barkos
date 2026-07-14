<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    /**
     * Buyer makes an offer for a product.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'offered_price' => 'required|integer|min:1',
        ]);

        $buyer = Auth::user();

        // Cannot offer on own product
        if ($product->user_id === $buyer->id) {
            return response()->json(['message' => 'Anda tidak dapat menawar produk milik sendiri.'], 422);
        }

        // Product must not be sold
        if ($product->status_terjual) {
            return response()->json(['message' => 'Produk ini sudah terjual.'], 422);
        }

        // Prevent duplicate pending offers
        $existing = Offer::where('product_id', $product->id)
            ->where('buyer_id', $buyer->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Anda sudah memiliki penawaran yang sedang menunggu respon untuk produk ini.'], 422);
        }

        $offer = Offer::create([
            'product_id'    => $product->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $product->user_id,
            'offered_price' => $request->offered_price,
            'status'        => 'pending',
        ]);

        $seller = \App\Models\User::find($product->user_id);
        $seller->notify(new \App\Notifications\OfferNotification(
            $offer,
            "{$buyer->name} menawar produk {$product->nama_barang} Anda seharga Rp " . number_format($request->offered_price, 0, ',', '.'),
            'offer_received'
        ));

        return response()->json([
            'message' => 'Penawaran berhasil dikirim. Menunggu respon penjual.',
            'data'    => new OfferResource($offer->load(['product', 'buyer', 'seller'])),
        ], 201);
    }

    /**
     * Buyer views their own offers.
     */
    public function indexBuyer(Request $request): AnonymousResourceCollection
    {
        $offers = Offer::with(['product.images', 'seller'])
            ->where('buyer_id', Auth::id())
            ->latest()
            ->get();

        return OfferResource::collection($offers);
    }

    /**
     * Seller views offers received for their products.
     */
    public function indexSeller(Request $request): AnonymousResourceCollection
    {
        $offers = Offer::with(['product.images', 'buyer'])
            ->where('seller_id', Auth::id())
            ->latest()
            ->get();

        return OfferResource::collection($offers);
    }

    /**
     * Update status: Seller accepts/rejects, or Buyer cancels.
     */
    public function updateStatus(Request $request, Offer $offer): JsonResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject', 'cancel'])],
        ]);

        $user = Auth::user();

        if ($request->action === 'cancel') {
            // Only buyer can cancel, and only if pending
            if ($offer->buyer_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($offer->status !== 'pending') {
                return response()->json(['message' => 'Hanya penawaran berstatus pending yang bisa dibatalkan.'], 422);
            }
            $offer->update(['status' => 'cancelled']);
            $message = 'Penawaran dibatalkan.';
        } else {
            // Only seller can accept/reject, and only if pending
            if ($offer->seller_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($offer->status !== 'pending') {
                return response()->json(['message' => 'Penawaran sudah tidak valid (sudah diproses/dibatalkan).'], 422);
            }

            if ($request->action === 'accept') {
                $offer->update(['status' => 'accepted']);
                $message = 'Penawaran diterima.';

                $buyer = \App\Models\User::find($offer->buyer_id);
                $buyer->notify(new \App\Notifications\OfferNotification(
                    $offer,
                    "Penawaran Anda untuk produk {$offer->product->nama_barang} seharga Rp " . number_format($offer->offered_price, 0, ',', '.') . " telah DITERIMA.",
                    'offer_accepted'
                ));
            } else {
                $offer->update(['status' => 'rejected']);
                $message = 'Penawaran ditolak.';

                $buyer = \App\Models\User::find($offer->buyer_id);
                $buyer->notify(new \App\Notifications\OfferNotification(
                    $offer,
                    "Penawaran Anda untuk produk {$offer->product->nama_barang} seharga Rp " . number_format($offer->offered_price, 0, ',', '.') . " telah DITOLAK.",
                    'offer_rejected'
                ));
            }
        }

        return response()->json([
            'message' => $message,
            'data'    => new OfferResource($offer->fresh()->load(['product', 'buyer', 'seller'])),
        ]);
    }
}
