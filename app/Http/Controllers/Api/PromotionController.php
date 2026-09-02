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
        $packages = PromotionPackage::where('aktif', true)->get();
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
            ->visibleTo(auth('sanctum')->id())
            ->whereHas('product', function ($query) {
                $query->where('status_terjual', false);
            })
            ->whereIn('jenis_iklan', ['image', 'video'])
            ->whereNotNull('url_media_iklan')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($promo) {
                $mediaUrl = $promo->url_media_iklan;
                if ($mediaUrl && str_starts_with($mediaUrl, '/storage/')) {
                    $mediaUrl = '/api' . $mediaUrl;
                }

                return [
                    'id_promosi'   => $promo->id_promosi,
                    'jenis_iklan'      => $promo->jenis_iklan,
                    'url_media_iklan' => $mediaUrl,
                    'judul_iklan'     => $promo->judul_iklan,
                    'id_produk'    => $promo->id_produk,
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
            'product_id'    => 'required|exists:produk,id_produk',
            'package_id'    => 'required|exists:paket_promosi,id_paket_promosi',
            'metode_pembayaran' => 'required|in:midtrans,manual_transfer',
            // Ad fields — optional
            'jenis_iklan'       => 'nullable|in:none,image,video',
            'url_media_iklan'  => 'nullable|string|max:2000',
            'file_media_iklan' => 'nullable|file|mimes:jpeg,png,jpg,webp,gif,mp4,mov,avi,webm,mkv|max:2097152', // max 2GB
            'judul_iklan'      => 'nullable|string|max:200',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Ensure the seller owns the product
        if ($product->id_pengguna !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package = PromotionPackage::findOrFail($request->package_id);

        $paymentSetting = PaymentSetting::current();
        if ($request->metode_pembayaran === 'midtrans' && !$paymentSetting->midtrans_diaktifkan) {
            return response()->json(['message' => 'Metode pembayaran Midtrans sedang tidak tersedia.'], 422);
        }
        if ($request->metode_pembayaran === 'manual_transfer' && !$paymentSetting->transfer_manual_diaktifkan) {
            return response()->json(['message' => 'Metode transfer manual sedang tidak tersedia.'], 422);
        }

        // Prepare ad media
        $adMediaUrl = $request->url_media_iklan;
        if ($request->hasFile('file_media_iklan')) {
            $path = $request->file('file_media_iklan')->store('promotions', 'public');
            $adMediaUrl = '/api/storage/' . $path;
        }

        if ($request->metode_pembayaran === 'manual_transfer') {
            $promotion = Promotion::create([
                'order_id'     => 'PROMO-' . time() . '-' . $request->user()->id,
                'status_pembayaran' => 'pending',
                'metode_pembayaran' => 'manual_transfer',
                'status_peninjauan_manual' => 'pending',
                'id_produk'    => $product->id_produk,
                'id_penjual'   => $request->user()->id,
                'id_paket_promosi' => $package->id_paket_promosi,
                'status'       => 'active',
                'mulai_pada'     => Carbon::now(),
                'berakhir_pada'       => Carbon::now(),
                'jumlah_dibayar'  => $package->harga,
                'jenis_iklan'      => $request->jenis_iklan ?? 'none',
                'url_media_iklan' => $adMediaUrl,
                'judul_iklan'     => $request->judul_iklan,
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
                'gross_amount' => (int) $package->harga,
            ],
            'customer_details' => [
                'first_name' => $request->user()->nama,
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
            'status_pembayaran' => 'pending',
            'metode_pembayaran' => 'midtrans',
            'id_produk'    => $product->id_produk,
            'id_penjual'   => $request->user()->id,
            'id_paket_promosi' => $package->id_paket_promosi,
            'status'       => 'active', // overall promotion status, but payment is pending
            'mulai_pada'     => Carbon::now(), // Will be properly adjusted on success webhook
            'berakhir_pada'       => Carbon::now(), // Will be properly adjusted on success webhook
            'jumlah_dibayar'  => $package->harga,
            'jenis_iklan'      => $request->jenis_iklan ?? 'none',
            'url_media_iklan' => $adMediaUrl,
            'judul_iklan'     => $request->judul_iklan,
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
        if ($promotion->id_penjual !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($promotion->metode_pembayaran !== 'manual_transfer' || $promotion->status_pembayaran !== 'pending') {
            return response()->json(['message' => 'Promosi ini tidak dapat diupload bukti transfernya.'], 422);
        }

        $request->validate([
            'proof_image' => 'required|image|max:10240',
        ]);

        if ($promotion->jalur_bukti_manual) {
            Storage::disk('public')->delete($promotion->jalur_bukti_manual);
        }

        $path = $request->file('proof_image')->store('payments/promotions', 'public');
        $promotion->update([
            'jalur_bukti_manual' => $path,
            'status_peninjauan_manual' => 'pending',
            'catatan_ocr' => null,
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
        if ($promotion->metode_pembayaran !== 'manual_transfer') {
            return response()->json(['message' => 'Promosi ini bukan pembayaran transfer manual.'], 422);
        }

        $this->promotionActivationService->activate($promotion);
        $promotion->update(['status_peninjauan_manual' => 'approved']);

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
        if ($promotion->metode_pembayaran !== 'manual_transfer') {
            return response()->json(['message' => 'Promosi ini bukan pembayaran transfer manual.'], 422);
        }

        $promotion->update([
            'status_pembayaran' => 'failed',
            'status_peninjauan_manual' => 'rejected',
        ]);

        return response()->json(['message' => 'Pembayaran ditolak.', 'data' => $promotion]);
    }

    /**
     * Recreate Snap Token for a pending promotion to allow changing payment method.
     */
    public function recreateSnapToken(Request $request, Promotion $promotion): JsonResponse
    {
        if ($promotion->id_penjual !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($promotion->status_pembayaran !== 'pending') {
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
                'gross_amount' => (int) $promotion->jumlah_dibayar,
            ],
            'customer_details' => [
                'first_name' => $request->user()->nama,
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
                $query->where('id_pengguna', $request->user()->id);
            })
            ->latest()
            ->get()
            ->map(function ($promo) {
                if ($promo->url_media_iklan && str_starts_with($promo->url_media_iklan, '/storage/')) {
                    $promo->url_media_iklan = '/api' . $promo->url_media_iklan;
                }
                if ($promo->jalur_bukti_manual && !str_starts_with($promo->jalur_bukti_manual, '/api/storage/')) {
                    $promo->jalur_bukti_manual = '/api/storage/' . $promo->jalur_bukti_manual;
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
            if ($promo->url_media_iklan && str_starts_with($promo->url_media_iklan, '/storage/')) {
                $promo->url_media_iklan = '/api' . $promo->url_media_iklan;
            }
            if ($promo->jalur_bukti_manual && !str_starts_with($promo->jalur_bukti_manual, '/api/storage/')) {
                $promo->jalur_bukti_manual = '/api/storage/' . $promo->jalur_bukti_manual;
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

        $ids = $promotion->id_pengguna_target;
        $users = $ids
            ? User::whereIn('id', $ids)->get(['id', 'nama', 'email', 'role'])
            : collect();

        return response()->json([
            'data' => $users,
            'is_targeted' => (bool) $ids,
            'recipient_count' => $promotion->package?->jumlah_penerima_acak,
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

        if (!$promotion->package?->jumlah_penerima_acak) {
            return response()->json(['message' => 'Paket promosi ini tidak memiliki batas jumlah akun random.'], 422);
        }

        $ids = $this->promotionActivationService->rollRandomRecipients($promotion);
        $promotion->update(['id_pengguna_target' => $ids]);

        $users = $ids
            ? User::whereIn('id', $ids)->get(['id', 'nama', 'email', 'role'])
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
            ->where('id_penjual', $request->user()->id)
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
            'nama'          => $request->name,
            'durasi_hari'   => $request->duration_days,
            'jumlah_penerima_acak' => $request->random_recipient_count ?? null,
            'harga'         => $request->price,
            'aktif'         => true,
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
            'random_recipient_count' => 'sometimes|nullable|integer|min:1',
            'price'         => 'sometimes|required|numeric|min:0',
            'is_active'     => 'sometimes|boolean',
        ]);

        $data = [];
        if ($request->has('random_recipient_count')) {
            $data['jumlah_penerima_acak'] = $request->input('random_recipient_count');
        }
        if ($request->has('name')) {
            $data['nama'] = $request->input('name');
        }
        if ($request->has('duration_days')) {
            $data['durasi_hari'] = $request->input('duration_days');
        }
        if ($request->has('price')) {
            $data['harga'] = $request->input('price');
        }
        if ($request->has('is_active')) {
            $data['aktif'] = $request->boolean('is_active');
        }

        $package->update($data);

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
                'dipromosikan' => false,
                'dipromosikan_hingga' => null,
            ]);
        }

        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted successfully.']);
    }
}
