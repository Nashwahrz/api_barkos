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
     *   Bank Transfer: confirmed + upload proof → seller confirms receipt → completed
     *   COD:           confirmed → completed directly
     */

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/transactions
    // Returns filtered list: buyer sees own purchases, seller sees own sales
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if ($user->role === 'penjual') {
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

        $transaction = Transaction::create([
            'product_id'     => $product->id,
            'buyer_id'       => $buyer->id,
            'seller_id'      => $product->user_id,
            'payment_method' => $request->payment_method,
            'status'         => 'pending',
            'agreed_price'   => $request->agreed_price,
            'notes'          => $request->notes,
        ]);

        return response()->json([
            'message' => 'Order berhasil dibuat. Menunggu konfirmasi penjual.',
            'data'    => new TransactionResource($transaction->load(['product', 'buyer', 'seller'])),
        ], 201);
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
            $message = 'Order dikonfirmasi. Hubungi pembeli untuk proses selanjutnya.';
        } else {
            $transaction->update(['status' => 'cancelled']);
            $message = 'Order ditolak.';
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
            'payment_proof' => 'required|image|max:5120', // max 5 MB
        ]);

        if ($transaction->buyer_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pembeli yang bisa upload bukti bayar.'], 403);
        }

        if ($transaction->payment_method !== 'bank_transfer') {
            return response()->json(['message' => 'Upload bukti hanya untuk transaksi bank transfer.'], 422);
        }

        if ($transaction->status !== 'confirmed') {
            return response()->json(['message' => 'Order harus dikonfirmasi penjual terlebih dahulu.'], 422);
        }

        // Delete old proof if exists
        if ($transaction->payment_proof_path) {
            Storage::disk('public')->delete($transaction->payment_proof_path);
        }

        $path = $request->file('payment_proof')->store('payments', 'public');

        $transaction->update(['payment_proof_path' => $path]);

        return response()->json([
            'message'           => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi penjual.',
            'payment_proof_url' => Storage::url($path),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH /api/transactions/{id}/complete
    // Seller confirms transaction is fully complete
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
        if ($transaction->payment_method === 'bank_transfer' && !$transaction->payment_proof_path) {
            return response()->json(['message' => 'Pembeli belum mengunggah bukti pembayaran.'], 422);
        }

        $transaction->update(['status' => 'completed']);

        // Mark product as sold
        $transaction->product->update(['status_terjual' => true]);

        return response()->json([
            'message' => 'Transaksi selesai! Produk ditandai sebagai terjual.',
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

        return response()->json([
            'message' => 'Order berhasil dibatalkan.',
            'data'    => new TransactionResource($transaction->fresh()->load(['product', 'buyer', 'seller'])),
        ]);
    }
}
