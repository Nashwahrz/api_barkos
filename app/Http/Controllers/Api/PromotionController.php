<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    /**
     * Get all promotion packages available for purchase.
     */
    public function packages(): JsonResponse
    {
        $packages = PromotionPackage::where('is_active', true)->get();
        return response()->json(['data' => $packages]);
    }

    /**
     * [PUBLIC] Get all active promotions that have an ad (image/video) attached.
     * Used by the homepage to render the iklan/banner carousel.
     */
    public function banners(): JsonResponse
    {
        $banners = Promotion::with(['product'])
            ->active()
            ->whereIn('ad_type', ['image', 'video'])
            ->whereNotNull('ad_media_url')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($promo) {
                return [
                    'id'           => $promo->id,
                    'ad_type'      => $promo->ad_type,
                    'ad_media_url' => $promo->ad_media_url,
                    'ad_title'     => $promo->ad_title,
                    'product_id'   => $promo->product_id,
                    'product_name' => $promo->product?->nama_barang,
                    'product_price'=> $promo->product?->harga,
                ];
            });

        return response()->json(['data' => $banners]);
    }

    /**
     * Purchase a promotion for a product (Seller).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'penjual') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'product_id'    => 'required|exists:products,id',
            'package_id'    => 'required|exists:promotion_packages,id',
            // Ad fields — optional
            'ad_type'       => 'nullable|in:none,image,video',
            'ad_media_url'  => 'nullable|string|max:2000',
            'ad_media_file' => 'nullable|file|mimes:jpeg,png,jpg,webp,gif,mp4,mov,avi,webm,mkv|max:2097152', // max 2GB
            'ad_title'      => 'nullable|string|max:200',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Ensure the seller owns the product
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package = PromotionPackage::findOrFail($request->package_id);

        // Calculate end date based on package duration is deferred until payment succeeds.
        // For now, prepare media
        $adMediaUrl = $request->ad_media_url;
        if ($request->hasFile('ad_media_file')) {
            $path = $request->file('ad_media_file')->store('promotions', 'public');
            $adMediaUrl = Storage::url($path);
        }

        // Setup Midtrans Config
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized = config('midtrans.is_sanitized');
        \Midtrans\Config::$is3ds = config('midtrans.is_3ds');

        $orderId = 'PROMO-' . time() . '-' . $request->user()->id;

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $package->price,
            ],
            'customer_details' => [
                'first_name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mendapatkan token pembayaran dari Midtrans', 'error' => $e->getMessage()], 500);
        }

        // Record the promotion as pending
        $promotion = Promotion::create([
            'order_id'     => $orderId,
            'snap_token'   => $snapToken,
            'payment_status' => 'pending',
            'product_id'   => $product->id,
            'seller_id'    => $request->user()->id,
            'package_id'   => $package->id,
            'status'       => 'active', // overall promotion status, but payment is pending
            'start_at'     => Carbon::now(), // Will be properly adjusted on success webhook
            'end_at'       => Carbon::now(), // Will be properly adjusted on success webhook
            'amount_paid'  => $package->price,
            'ad_type'      => $request->ad_type ?? 'none',
            'ad_media_url' => $adMediaUrl,
            'ad_title'     => $request->ad_title,
        ]);

        return response()->json([
            'message' => 'Silakan selesaikan pembayaran.',
            'data'    => $promotion,
        ], 201);
    }

    /**
     * Get active promotions for the authenticated seller.
     */
    public function myPromotions(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'penjual') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $promotions = Promotion::with(['product', 'package'])
            ->whereHas('product', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->latest()
            ->get();

        return response()->json(['data' => $promotions]);
    }

    /**
     * Get all promotions (Admin Only).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $promotions = Promotion::with(['product', 'package'])->latest()->get();
        return response()->json(['data' => $promotions]);
    }

    /**
     * Development only: Force a promotion to be paid bypassing Midtrans
     */
    public function forcePaid(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string'
        ]);

        $promotion = Promotion::where('order_id', $request->order_id)
            ->where('seller_id', $request->user()->id)
            ->firstOrFail();

        if ($promotion->payment_status !== 'paid') {
            $promotion->payment_status = 'paid';
            
            $package = $promotion->package;
            $product = $promotion->product;
            
            $startDate = Carbon::now();
            if ($product->is_promoted && $product->promoted_until && Carbon::parse($product->promoted_until)->isFuture()) {
                $startDate = Carbon::parse($product->promoted_until);
            }
            $endDate = $startDate->copy()->addDays($package->duration_days);

            $promotion->start_at = $startDate;
            $promotion->end_at = $endDate;
            $promotion->save();

            $product->update([
                'is_promoted' => true,
                'promoted_until' => $endDate,
            ]);
        }

        return response()->json(['message' => 'Status forced to paid successfully.', 'data' => $promotion]);
    }

    /**
     * Create a new promotion package (Admin).
     */
    public function storePackage(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name'          => 'required|string|max:255',
            'duration_days' => 'required|integer|min:1',
            'price'         => 'required|numeric|min:0',
        ]);

        $package = PromotionPackage::create([
            'name'          => $request->name,
            'duration_days' => $request->duration_days,
            'price'         => $request->price,
            'is_active'     => true,
        ]);

        return response()->json([
            'message' => 'Package created successfully.',
            'data'    => $package,
        ], 201);
    }

    /**
     * Update a promotion package (Admin).
     */
    public function updatePackage(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package = PromotionPackage::findOrFail($id);

        $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'duration_days' => 'sometimes|required|integer|min:1',
            'price'         => 'sometimes|required|numeric|min:0',
            'is_active'     => 'sometimes|boolean',
        ]);

        $package->update($request->only(['name', 'duration_days', 'price', 'is_active']));

        return response()->json([
            'message' => 'Package updated successfully.',
            'data'    => $package,
        ]);
    }

    /**
     * Delete a promotion package (Admin).
     */
    public function destroyPackage(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package = PromotionPackage::findOrFail($id);

        if ($package->promotions()->exists()) {
             return response()->json(['message' => 'Package cannot be deleted because it is already used in promotions.'], 400);
        }

        $package->delete();

        return response()->json(['message' => 'Package deleted successfully.']);
    }
}
