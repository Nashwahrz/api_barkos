<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPromotionPaymentProofJob;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionPackage;
use App\Models\User;
use App\Services\PromotionActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    public function __construct(private PromotionActivationService $promotionActivationService)
    {
    }

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
            ->whereHas('product', function ($query) {
                $query->where('status_terjual', false);
            })
            ->whereIn('ad_type', ['image', 'video'])
            ->whereNotNull('ad_media_url')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($promo) {
                $mediaUrl = $promo->ad_media_url;
                if ($mediaUrl && str_starts_with($mediaUrl, '/storage/')) {
                    $mediaUrl = '/api' . $mediaUrl;
                }

                return [
                    'id'           => $promo->id,
                    'ad_type'      => $promo->ad_type,
                    'ad_media_url' => $mediaUrl,
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
            'payment_method' => 'required|in:midtrans,manual_transfer',
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

        $paymentSetting = PaymentSetting::current();
        if ($request->payment_method === 'midtrans' && !$paymentSetting->midtrans_enabled) {
            return response()->json(['message' => 'Metode pembayaran Midtrans sedang tidak tersedia.'], 422);
        }
        if ($request->payment_method === 'manual_transfer' && !$paymentSetting->manual_transfer_enabled) {
            return response()->json(['message' => 'Metode transfer manual sedang tidak tersedia.'], 422);
        }

        // Prepare ad media
        $adMediaUrl = $request->ad_media_url;
        if ($request->hasFile('ad_media_file')) {
            $path = $request->file('ad_media_file')->store('promotions', 'public');
            $adMediaUrl = '/api/storage/' . $path;
        }

        if ($request->payment_method === 'manual_transfer') {
            $promotion = Promotion::create([
                'order_id'     => 'PROMO-' . time() . '-' . $request->user()->id,
                'payment_status' => 'pending',
                'payment_method' => 'manual_transfer',
                'manual_review_status' => 'pending',
                'product_id'   => $product->id,
                'seller_id'    => $request->user()->id,
                'package_id'   => $package->id,
                'status'       => 'active',
                'start_at'     => Carbon::now(),
                'end_at'       => Carbon::now(),
                'amount_paid'  => $package->price,
                'ad_type'      => $request->ad_type ?? 'none',
                'ad_media_url' => $adMediaUrl,
                'ad_title'     => $request->ad_title,
                'max_impressions' => $package->quota_impressions,
            ]);

            return response()->json([
                'message' => 'Silakan upload bukti transfer.',
                'data'    => $promotion,
            ], 201);
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
            'payment_method' => 'midtrans',
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
            'max_impressions' => $package->quota_impressions,
        ]);

        return response()->json([
            'message' => 'Silakan selesaikan pembayaran.',
            'data'    => $promotion,
        ], 201);
    }

    /**
     * Upload manual-transfer proof of payment for a pending promotion (Seller).
     */
    public function uploadProof(Request $request, Promotion $promotion): JsonResponse
    {
        if ($promotion->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($promotion->payment_method !== 'manual_transfer' || $promotion->payment_status !== 'pending') {
            return response()->json(['message' => 'Promosi ini tidak dapat diupload bukti transfernya.'], 422);
        }

        $request->validate([
            'proof_image' => 'required|image|max:10240',
        ]);

        if ($promotion->manual_proof_path) {
            Storage::disk('public')->delete($promotion->manual_proof_path);
        }

        $path = $request->file('proof_image')->store('payments/promotions', 'public');
        $promotion->update([
            'manual_proof_path' => $path,
            'manual_review_status' => 'pending',
            'ocr_note' => null,
        ]);

        ProcessPromotionPaymentProofJob::dispatch($promotion);

        return response()->json([
            'message' => 'Bukti transfer berhasil diupload dan sedang diperiksa.',
            'data'    => $promotion,
        ]);
    }

    /**
     * Approve a manual-transfer promotion payment (Admin).
     */
    public function approvePayment(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $promotion = Promotion::findOrFail($id);
        if ($promotion->payment_method !== 'manual_transfer') {
            return response()->json(['message' => 'Promosi ini bukan pembayaran transfer manual.'], 422);
        }

        $this->promotionActivationService->activate($promotion);
        $promotion->update(['manual_review_status' => 'approved']);

        return response()->json(['message' => 'Pembayaran disetujui, promosi diaktifkan.', 'data' => $promotion]);
    }

    /**
     * Reject a manual-transfer promotion payment (Admin).
     */
    public function rejectPayment(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $promotion = Promotion::findOrFail($id);
        if ($promotion->payment_method !== 'manual_transfer') {
            return response()->json(['message' => 'Promosi ini bukan pembayaran transfer manual.'], 422);
        }

        $promotion->update([
            'payment_status' => 'failed',
            'manual_review_status' => 'rejected',
        ]);

        return response()->json(['message' => 'Pembayaran ditolak.', 'data' => $promotion]);
    }

    /**
     * Recreate Snap Token for a pending promotion to allow changing payment method.
     */
    public function recreateSnapToken(Request $request, Promotion $promotion): JsonResponse
    {
        if ($promotion->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($promotion->payment_status !== 'pending') {
            return response()->json(['message' => 'Hanya promosi berstatus pending yang dapat diubah metode pembayarannya.'], 422);
        }

        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Generate a new order ID
        $newOrderId = 'PROMO-' . time() . '-' . $request->user()->id . '-R';

        $params = [
            'transaction_details' => [
                'order_id' => $newOrderId,
                'gross_amount' => (int) $promotion->amount_paid,
            ],
            'customer_details' => [
                'first_name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ];

        try {
            $newSnapToken = \Midtrans\Snap::getSnapToken($params);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mendapatkan token pembayaran baru dari Midtrans', 'error' => $e->getMessage()], 500);
        }

        // Update the promotion with new order ID and snap token
        $promotion->update([
            'order_id'   => $newOrderId,
            'snap_token' => $newSnapToken,
        ]);

        return response()->json([
            'message' => 'Berhasil membuat ulang transaksi.',
            'snap_token' => $newSnapToken,
            'order_id' => $newOrderId
        ]);
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
            ->get()
            ->map(function ($promo) {
                if ($promo->ad_media_url && str_starts_with($promo->ad_media_url, '/storage/')) {
                    $promo->ad_media_url = '/api' . $promo->ad_media_url;
                }
                if ($promo->manual_proof_path && !str_starts_with($promo->manual_proof_path, '/api/storage/')) {
                    $promo->manual_proof_path = '/api/storage/' . $promo->manual_proof_path;
                }
                if ($promo->product && $promo->product->foto && !str_starts_with($promo->product->foto, '/api/storage/')) {
                    $promo->product->foto = '/api/storage/' . $promo->product->foto;
                }
                return $promo;
            });

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

        $promotions = Promotion::with(['product', 'package', 'seller'])->latest()->get()->map(function ($promo) {
            if ($promo->ad_media_url && str_starts_with($promo->ad_media_url, '/storage/')) {
                $promo->ad_media_url = '/api' . $promo->ad_media_url;
            }
            if ($promo->manual_proof_path && !str_starts_with($promo->manual_proof_path, '/api/storage/')) {
                $promo->manual_proof_path = '/api/storage/' . $promo->manual_proof_path;
            }
            if ($promo->product && $promo->product->foto && !str_starts_with($promo->product->foto, '/api/storage/')) {
                $promo->product->foto = '/api/storage/' . $promo->product->foto;
            }
            return $promo;
        });
        return response()->json(['data' => $promotions]);
    }

    /**
     * List the random-blast target accounts for a promotion (Admin).
     */
    public function recipients(Request $request, Promotion $promotion): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $ids = $promotion->target_user_ids;
        $users = $ids
            ? User::whereIn('id', $ids)->get(['id', 'name', 'email', 'role'])
            : collect();

        return response()->json([
            'data' => $users,
            'is_targeted' => (bool) $ids,
            'recipient_count' => $promotion->package?->random_recipient_count,
        ]);
    }

    /**
     * Re-roll the random recipient list for a promotion (Admin).
     * Useful for demoing the random-targeting feature without waiting for a new purchase.
     */
    public function rerollRecipients(Request $request, Promotion $promotion): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$promotion->package?->random_recipient_count) {
            return response()->json(['message' => 'Paket promosi ini tidak memiliki batas jumlah akun random.'], 422);
        }

        $ids = $this->promotionActivationService->rollRandomRecipients($promotion);
        $promotion->update(['target_user_ids' => $ids]);

        $users = $ids
            ? User::whereIn('id', $ids)->get(['id', 'name', 'email', 'role'])
            : collect();

        return response()->json([
            'message' => 'Daftar akun random berhasil digenerate ulang.',
            'data' => $users,
        ]);
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

        $this->promotionActivationService->activate($promotion);

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
            'random_recipient_count' => 'nullable|integer|min:1',
        ]);

        $package = PromotionPackage::create([
            'name'          => $request->name,
            'duration_days' => $request->duration_days,
            'quota_impressions' => $request->quota_impressions ?? null,
            'random_recipient_count' => $request->random_recipient_count ?? null,
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
            'quota_impressions' => 'sometimes|nullable|integer|min:1',
            'random_recipient_count' => 'sometimes|nullable|integer|min:1',
            'price'         => 'sometimes|required|numeric|min:0',
            'is_active'     => 'sometimes|boolean',
        ]);

        $package->update($request->only(['name', 'duration_days', 'quota_impressions', 'random_recipient_count', 'price', 'is_active']));

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

    /**
     * Delete a promotion (Admin).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $promotion = Promotion::findOrFail($id);

        if ($promotion->product) {
            $promotion->product->update([
                'is_promoted' => false,
                'promoted_until' => null,
            ]);
        }

        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted successfully.']);
    }
}
