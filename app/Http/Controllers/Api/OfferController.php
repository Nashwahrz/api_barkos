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
            'harga_tawaran' => 'required|integer|min:1',
        ]);

        $buyer = Auth::user();

        // Cannot offer on own product
        if ($product->id_pengguna === $buyer->id) {
            return response()->json(['message' => 'Anda tidak dapat menawar produk milik sendiri.'], 422);
        }

        // Product must not be sold
        if ($product->status_terjual) {
            return response()->json(['message' => 'Produk ini sudah terjual.'], 422);
        }

        // Seller may disable negotiation for this product
        if (!$product->tawaran_diaktifkan) {
            return response()->json(['message' => 'Penjual tidak menerima tawaran untuk produk ini.'], 422);
        }

        // Validate minimum offer price
        if ($product->harga_minimum_tawaran !== null && $request->harga_tawaran < $product->harga_minimum_tawaran) {
            return response()->json([
                'message' => 'Penawaran Anda di bawah harga minimum yang ditetapkan penjual (Rp ' . number_format($product->harga_minimum_tawaran, 0, ',', '.') . ').'
            ], 422);
        }

        // Prevent duplicate pending offers
        $existing = Offer::where('id_produk', $product->id_produk)
            ->where('id_pembeli', $buyer->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Anda sudah memiliki penawaran yang sedang menunggu respon untuk produk ini.'], 422);
        }

        $offer = Offer::create([
            'id_produk'     => $product->id_produk,
            'id_pembeli'    => $buyer->id,
            'id_penjual'    => $product->id_pengguna,
            'harga_tawaran' => $request->harga_tawaran,
            'status'        => 'pending',
        ]);

        $seller = \App\Models\User::find($product->id_pengguna);
        $seller->notify(new \App\Notifications\OfferNotification(
            $offer,
            "{$buyer->nama} menawar produk {$product->nama_barang} Anda seharga Rp " . number_format($request->harga_tawaran, 0, ',', '.'),
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
            ->where('id_pembeli', Auth::id())
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
            ->where('id_penjual', Auth::id())
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
            if ($offer->id_pembeli !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($offer->status !== 'pending') {
                return response()->json(['message' => 'Hanya penawaran berstatus pending yang bisa dibatalkan.'], 422);
            }
            $offer->update(['status' => 'cancelled']);
            $message = 'Penawaran dibatalkan.';
        } else {
            // Only seller can accept/reject, and only if pending
            if ($offer->id_penjual !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($offer->status !== 'pending') {
                return response()->json(['message' => 'Penawaran sudah tidak valid (sudah diproses/dibatalkan).'], 422);
            }

            if ($request->action === 'accept') {
                $offer->update(['status' => 'accepted']);
                $message = 'Penawaran diterima.';

                $buyer = \App\Models\User::find($offer->id_pembeli);
                $buyer->notify(new \App\Notifications\OfferNotification(
                    $offer,
                    "Penawaran Anda untuk produk {$offer->product->nama_barang} seharga Rp " . number_format($offer->harga_tawaran, 0, ',', '.') . " telah DITERIMA.",
                    'offer_accepted'
                ));
            } else {
                $offer->update(['status' => 'rejected']);
                $message = 'Penawaran ditolak.';

                $buyer = \App\Models\User::find($offer->id_pembeli);
                $buyer->notify(new \App\Notifications\OfferNotification(
                    $offer,
                    "Penawaran Anda untuk produk {$offer->product->nama_barang} seharga Rp " . number_format($offer->harga_tawaran, 0, ',', '.') . " telah DITOLAK.",
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
