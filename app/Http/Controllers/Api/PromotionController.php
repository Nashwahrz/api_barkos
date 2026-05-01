<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
     * Purchase a promotion for a product (Seller).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'penjual') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'package_id' => 'required|exists:promotion_packages,id',
        ]);

        $product = Product::findOrFail($request->product_id);
        
        // Ensure the seller owns the product
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package = PromotionPackage::findOrFail($request->package_id);

        // Calculate end date based on package duration
        $startDate = Carbon::now();
        // If already promoted, extend from current end date
        if ($product->is_promoted && $product->promoted_until && Carbon::parse($product->promoted_until)->isFuture()) {
            $startDate = Carbon::parse($product->promoted_until);
        }
        
        $endDate = $startDate->copy()->addDays($package->duration_days);

        // Record the promotion (simulating payment success instantly as per requirements)
        $promotion = Promotion::create([
            'product_id' => $product->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => Carbon::now(),
            'end_date' => $endDate,
        ]);

        // Update the product
        $product->update([
            'is_promoted' => true,
            'promoted_until' => $endDate,
        ]);

        return response()->json([
            'message' => 'Promosi berhasil diaktifkan!',
            'data' => $promotion
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
}
