<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * Phase 2.1 — TRD §6.4
     * Display a listing of products with optional geolocation, keyword, category, price, and condition filters.
     * Promoted products always appear first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $lat      = $request->query('lat');
        $lng      = $request->query('lng');
        $radius   = $request->query('radius', 1000); // default 1 km
        $keyword  = $request->query('keyword');
        $catId    = $request->query('category_id');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $kondisi  = $request->query('kondisi');

        $viewerId = auth('sanctum')->id();

        $query = Product::with(['user', 'category', 'promotions' => function ($q) {
                $q->where('status', 'active')
                  ->where('payment_status', 'paid')
                  ->where('end_at', '>', now());
            }])
            ->where('status_terjual', false);

        // ── Keyword filter ────────────────────────────────────────────────
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('nama_barang', 'like', "%{$keyword}%")
                  ->orWhere('deskripsi',  'like', "%{$keyword}%");
            });
        }

        // ── Category filter ───────────────────────────────────────────────
        if ($catId) {
            $query->where('category_id', $catId);
        }

        // ── Price range filter ────────────────────────────────────────────
        if ($minPrice) {
            $query->where('harga', '>=', (int) $minPrice);
        }
        if ($maxPrice) {
            $query->where('harga', '<=', (int) $maxPrice);
        }

        // ── Condition filter ──────────────────────────────────────────────
        if ($kondisi) {
            $query->where('kondisi', $kondisi);
        }

        // ── Geolocation: Haversine filter + sorting (TRD §6.4) ───────────
        if ($lat && $lng) {
            $lat    = (float) $lat;
            $lng    = (float) $lng;
            $radius = (int) $radius; // metres
            $earthR = 6371000;       // Earth radius in metres

            // Bounding-box pre-filter for performance (degrees ≈ metres / 111_000)
            $delta = $radius / 111000;
            $query->whereBetween('latitude',  [$lat - $delta, $lat + $delta])
                  ->whereBetween('longitude', [$lng - $delta, $lng + $delta])
                  ->whereNotNull('latitude')
                  ->whereNotNull('longitude');

            // Fetch then apply precise Haversine in PHP
            $products = $query->get()->map(function ($product) use ($lat, $lng, $earthR, $viewerId) {
                $pLat = (float) $product->latitude;
                $pLng = (float) $product->longitude;

                $dLat = deg2rad($pLat - $lat);
                $dLng = deg2rad($pLng - $lng);

                $a = sin($dLat / 2) ** 2
                   + cos(deg2rad($lat)) * cos(deg2rad($pLat)) * sin($dLng / 2) ** 2;

                $distanceM  = $earthR * 2 * asin(sqrt($a));
                $product->distance_km = round($distanceM / 1000, 2);
                $product->promoted_for_viewer = $this->isPromotedForViewer($product, $viewerId) ? 1 : 0;

                return $product;
            })
            ->filter(fn($p) => ($p->distance_km * 1000) <= $radius)
            ->sortBy([
                ['promoted_for_viewer', 'desc'],   // promoted-to-this-viewer products first
                ['distance_km', 'asc'],            // then nearest
            ])
            ->values();

            $promotedProductIds = $products->filter(fn($p) => $p->promoted_for_viewer)->pluck('id')->toArray();
            if (!empty($promotedProductIds)) {
                \App\Jobs\ProcessPromotionImpressionsJob::dispatchAfterResponse($promotedProductIds);
            }

            return ProductResource::collection($products);
        }

        // ── Fallback: no geo params → promoted (for this viewer) first, then latest ─────────
        $products = $query
            ->orderByRaw(
                "CASE WHEN is_promoted = 1 AND (promoted_until IS NULL OR promoted_until > NOW()) AND EXISTS (
                    SELECT 1 FROM promotions
                    WHERE promotions.product_id = products.id
                      AND promotions.status = 'active'
                      AND promotions.payment_status = 'paid'
                      AND promotions.end_at > NOW()
                      AND (promotions.target_user_ids IS NULL OR JSON_CONTAINS(promotions.target_user_ids, ?))
                ) THEN 1 ELSE 0 END DESC",
                [json_encode($viewerId)]
            )
            ->latest()
            ->paginate(20);

        $promotedProductIds = collect($products->items())
            ->filter(fn($p) => $this->isPromotedForViewer($p, $viewerId))
            ->pluck('id')->toArray();
        if (!empty($promotedProductIds)) {
            \App\Jobs\ProcessPromotionImpressionsJob::dispatchAfterResponse($promotedProductIds);
        }

        return ProductResource::collection($products);
    }

    /**
     * Whether a product's active promotion should be surfaced (boosted/bannered) to
     * this particular viewer — untargeted promotions show to everyone, targeted ones
     * only to the random accounts rolled into the promotion's target_user_ids.
     */
    private function isPromotedForViewer(Product $product, ?int $viewerId): bool
    {
        if (!$product->is_promoted) {
            return false;
        }

        $promotion = $product->relationLoaded('promotions')
            ? $product->promotions->first()
            : $product->promotions()
                ->where('status', 'active')
                ->where('payment_status', 'paid')
                ->where('end_at', '>', now())
                ->latest()
                ->first();

        if (!$promotion || empty($promotion->target_user_ids)) {
            return true;
        }

        return $viewerId && in_array($viewerId, $promotion->target_user_ids);
    }

    /**
     * Display a listing of products owned by the authenticated user.
     */
    public function myProducts(): AnonymousResourceCollection
    {
        $products = Auth::user()->products()->with('category')->latest()->get();
        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['is_offer_enabled']) && !$data['is_offer_enabled']) {
            $data['minimum_offer_price'] = null;
        }

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        $product = Auth::user()->products()->create($data);

        // Notify super_admins by email about the new product
        \App\Jobs\NotifyAdminsOfNewProductJob::dispatchAfterResponse($product);

        return response()->json([
            'message' => 'Product created successfully',
            'data'    => new ProductResource($product->load(['user', 'category'])),
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['user.bankAccounts', 'category', 'images']));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        if ($product->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        if (isset($data['is_offer_enabled']) && !$data['is_offer_enabled']) {
            $data['minimum_offer_price'] = null;
        }

        if ($request->hasFile('foto')) {
            if ($product->foto) {
                Storage::disk('public')->delete($product->foto);
            }
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => new ProductResource($product->load(['user', 'category'])),
        ]);
    }

    /**
     * Phase 2.3 — Toggle sold/available status for a product.
     */
    public function toggleStatus(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product->update(['status_terjual' => !$product->status_terjual]);

        return response()->json([
            'message'       => $product->status_terjual ? 'Produk ditandai sebagai terjual.' : 'Produk kembali tersedia.',
            'status_terjual' => $product->status_terjual,
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($product->foto) {
            Storage::disk('public')->delete($product->foto);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
