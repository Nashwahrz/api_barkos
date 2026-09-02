<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    /**
     * Phase 3 — PRD §2.2.3, §2.1.4
     *
     * State Machine:
     *   pending  →  confirmed  →  completed
     *   pending  →  cancelled  (by buyer)
     *   confirmed → cancelled  (by seller reject)
     *
     *   Bank Transfer: pending/confirmed + upload proof → seller confirms order → seller confirms receipt → completed
     *   COD:           confirmed → completed directly
     */

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/transactions
    // Returns filtered list: buyer sees own purchases, seller sees own sales
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $as = $request->query('as');

        if ($as === 'buyer') {
            $transactions = Transaction::with(['product', 'seller'])
                ->where('buyer_id', $user->id)
                ->latest()
                ->get();
        } elseif ($as === 'seller' || $user->role === 'penjual') {
            // Seller: see all transactions on their products
            $transactions = Transaction::with(['product', 'buyer'])
                ->where('seller_id', $user->id)
                ->latest()
                ->get();
        } elseif ($user->role === 'super_admin') {
            // Admin: see all transactions
            $transactions = Transaction::with(['product', 'buyer', 'seller'])
                ->latest()
                ->get();
        } else {
            // Buyer: see own purchase history
            $transactions = Transaction::with(['product', 'seller'])
                ->where('buyer_id', $user->id)
                ->latest()
                ->get();
        }

        return TransactionResource::collection($transactions);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/transactions/{id}
    // ─────────────────────────────────────────────────────────────────────
    public function show(Transaction $transaction): TransactionResource|JsonResponse
    {
        $user = Auth::user();

        // Only parties involved or admin may view
        if ($transaction->buyer_id !== $user->id
            && $transaction->seller_id !== $user->id
            && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new TransactionResource($transaction->load(['product', 'buyer', 'seller']));
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/transactions
    // Buyer places a new order
    // ─────────────────────────────────────────────────────────────────────
    public function store(TransactionRequest $request): JsonResponse
    {
        $buyer   = $request->user();
        $product = Product::findOrFail($request->product_id);

        // Cannot buy own product
        if ($product->user_id === $buyer->id) {
            return response()->json(['message' => 'Anda tidak dapat membeli produk milik sendiri.'], 422);
        }

        // Product must be available
        if ($product->status_terjual) {
            return response()->json(['message' => 'Produk ini sudah terjual.'], 422);
        }

        // Prevent duplicate pending order on same product by same buyer
        $existing = Transaction::where('product_id', $product->id)
            ->where('buyer_id', $buyer->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Anda sudah memiliki order aktif untuk produk ini.'], 422);
        }

        // Validate harga_disepakati
        $isValidPrice = false;
        if ((int)$request->harga_disepakati === (int)$product->harga) {
            $isValidPrice = true;
        } else {
            // Check if there is an accepted offer with this price
            $acceptedOffer = \App\Models\Offer::where('product_id', $product->id)
                ->where('buyer_id', $buyer->id)
                ->where('status', 'accepted')
                ->where('harga_tawaran', $request->harga_disepakati)
                ->exists();
            if ($acceptedOffer) {
                $isValidPrice = true;
            }
        }

        if (!$isValidPrice) {
            return response()->json(['message' => 'Harga tidak valid. Harus sesuai harga produk atau penawaran yang disetujui.'], 422);
        }

        $transaction = Transaction::create([
            'product_id'     => $product->id,
            'buyer_id'       => $buyer->id,
            'seller_id'      => $product->user_id,
            'metode_pembayaran' => $request->metode_pembayaran,
            'status'         => 'pending',
            'harga_disepakati'   => $request->harga_disepakati,
            'catatan'          => $request->catatan,
        ]);

        $seller = \App\Models\User::find($product->user_id);
        $seller->notify(new \App\Notifications\TransactionNotification(
            $transaction,
            "Pesanan baru masuk dari {$buyer->nama} untuk produk {$product->nama_barang} seharga Rp " . number_format($request->harga_disepakati, 0, ',', '.'),
            'new_order'
        ));

        return response()->json([
            'message' => 'Order berhasil dibuat. Menunggu konfirmasi penjual.',
            'data'    => new TransactionResource($transaction->load(['product', 'buyer', 'seller'])),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH /api/transactions/{id}/payment-method
    // Buyer changes the payment method
    // ─────────────────────────────────────────────────────────────────────
    public function updatePaymentMethod(Request $request, Transaction $transaction): JsonResponse
    {
        $request->validate([
            'metode_pembayaran' => 'required|in:cod,bank_transfer',
        ], [
            'metode_pembayaran.required' => 'Metode pembayaran harus dipilih.',
            'metode_pembayaran.in'       => 'Metode pembayaran harus COD atau transfer bank.',
        ]);

        if ($transaction->buyer_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pembeli yang bisa mengubah metode pembayaran.'], 403);
        }

        if (in_array($transaction->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Tidak dapat mengubah metode pembayaran pada pesanan ini.'], 422);
        }

        if ($transaction->has_payment_proof) {
            return response()->json(['message' => 'Tidak dapat mengubah metode pembayaran karena bukti pembayaran sudah diunggah.'], 422);
        }

        $transaction->update([
            'metode_pembayaran' => $request->metode_pembayaran
        ]);

        return response()->json([
            'message' => 'Metode pembayaran berhasil diubah.',
            'data'    => new TransactionResource($transaction->load(['product', 'buyer', 'seller'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH /api/transactions/{id}/confirm
    // Seller confirms or rejects the order
    // ─────────────────────────────────────────────────────────────────────
    public function confirm(Request $request, Transaction $transaction): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:confirm,reject',
        ]);

        if ($transaction->seller_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya penjual yang bisa mengkonfirmasi order.'], 403);
        }

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Order ini tidak dalam status pending.'], 422);
        }

        if ($request->action === 'confirm') {
            $transaction->update(['status' => 'confirmed']);

            // Tandai produk sebagai terjual SEGERA saat dikonfirmasi,
            // agar pembeli lain tidak bisa memesan produk yang sama.
            // Penjual tetap harus klik "Selesaikan" setelah barang diserahkan.
            $transaction->product->update(['status_terjual' => true, 'terjual_pada' => now()]);

            $message = 'Order dikonfirmasi. Produk otomatis ditandai terjual. Hubungi pembeli untuk proses selanjutnya.';

            $buyer = \App\Models\User::find($transaction->buyer_id);
            $buyer->notify(new \App\Notifications\TransactionNotification(
                $transaction,
                "Pesanan Anda untuk produk {$transaction->product->nama_barang} telah dikonfirmasi penjual.",
                'order_confirmed'
            ));
        } else {
            $transaction->update(['status' => 'cancelled']);
            $message = 'Order ditolak.';

            $buyer = \App\Models\User::find($transaction->buyer_id);
            $buyer->notify(new \App\Notifications\TransactionNotification(
                $transaction,
                "Maaf, pesanan Anda untuk produk {$transaction->product->nama_barang} telah ditolak oleh penjual.",
                'order_rejected'
            ));
        }

        return response()->json([
            'message' => $message,
            'data'    => new TransactionResource($transaction->fresh()->load(['product', 'buyer', 'seller'])),
        ]);
    }


    // ─────────────────────────────────────────────────────────────────────
    // PATCH /api/transactions/{id}/payment
    // Buyer uploads payment proof (bank transfer only)
    // File storage: storage/app/public/payments/ — TRD §6.5
    // ─────────────────────────────────────────────────────────────────────
    public function uploadPayment(Request $request, Transaction $transaction): JsonResponse
    {
        $request->validate([
            'payment_proof' => 'required|image|max:10240', // max 10 MB
        ]);

        if ($transaction->buyer_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pembeli yang bisa upload bukti bayar.'], 403);
        }

        if ($transaction->metode_pembayaran !== 'bank_transfer') {
            return response()->json(['message' => 'Upload bukti hanya untuk transaksi bank transfer.'], 422);
        }

        if (in_array($transaction->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Tidak dapat upload bukti untuk order yang sudah selesai atau dibatalkan.'], 422);
        }

        // Delete old proof if exists
        if ($transaction->jalur_bukti_pembayaran) {
            Storage::disk('public')->delete($transaction->jalur_bukti_pembayaran);
        }

        $path = $request->file('payment_proof')->store('payments', 'public');

        $transaction->update(['jalur_bukti_pembayaran' => $path]);

        $seller = \App\Models\User::find($transaction->seller_id);
        $seller->notify(new \App\Notifications\TransactionNotification(
            $transaction,
            "Pembeli telah mengunggah bukti pembayaran untuk pesanan {$transaction->product->nama_barang}.",
            'payment_uploaded'
        ));

        return response()->json([
            'message'           => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi penjual.',
            'url_bukti_pembayaran' => '/api/storage/' . $path,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH /api/transactions/{id}/complete
    // Seller marks physical handover as done (transaction fully complete)
    // ─────────────────────────────────────────────────────────────────────
    public function complete(Transaction $transaction): JsonResponse
    {
        if ($transaction->seller_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya penjual yang bisa menandai transaksi selesai.'], 403);
        }

        if ($transaction->status !== 'confirmed') {
            return response()->json(['message' => 'Order harus dalam status dikonfirmasi.'], 422);
        }

        // For bank transfer, payment proof must be uploaded
        if ($transaction->metode_pembayaran === 'bank_transfer' && !$transaction->jalur_bukti_pembayaran) {
            return response()->json(['message' => 'Pembeli belum mengunggah bukti pembayaran.'], 422);
        }

        $transaction->update(['status' => 'completed']);

        // Produk sudah ditandai terjual sejak confirm — tidak perlu diulang di sini.
        // Pastikan tetap true (idempotent) kalau ada edge-case.
        $transaction->product->update(['status_terjual' => true, 'terjual_pada' => now()]);

        $buyer = \App\Models\User::find($transaction->buyer_id);
        $buyer->notify(new \App\Notifications\TransactionNotification(
            $transaction,
            "Pesanan Anda untuk produk {$transaction->product->nama_barang} telah diselesaikan oleh penjual. Terima kasih!",
            'order_completed'
        ));

        return response()->json([
            'message' => 'Transaksi selesai!',
            'data'    => new TransactionResource($transaction->fresh()->load(['product', 'buyer', 'seller'])),
        ]);
    }


    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/transactions/{id}/cancel
    // Buyer cancels a pending order
    // ─────────────────────────────────────────────────────────────────────
    public function cancel(Transaction $transaction): JsonResponse
    {
        if ($transaction->buyer_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pembeli yang bisa membatalkan order.'], 403);
        }

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Hanya order dengan status pending yang bisa dibatalkan.'], 422);
        }

        $transaction->update(['status' => 'cancelled']);

        $seller = \App\Models\User::find($transaction->seller_id);
        $seller->notify(new \App\Notifications\TransactionNotification(
            $transaction,
            "Pembeli membatalkan pesanan untuk produk {$transaction->product->nama_barang}.",
            'order_cancelled'
        ));

        return response()->json([
            'message' => 'Order berhasil dibatalkan.',
            'data'    => new TransactionResource($transaction->fresh()->load(['product', 'buyer', 'seller'])),
        ]);
    }
}
