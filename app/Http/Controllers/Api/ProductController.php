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

        $query = Product::with(['user', 'category'])
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
            $products = $query->get()->map(function ($product) use ($lat, $lng, $earthR) {
                $pLat = (float) $product->latitude;
                $pLng = (float) $product->longitude;

                $dLat = deg2rad($pLat - $lat);
                $dLng = deg2rad($pLng - $lng);

                $a = sin($dLat / 2) ** 2
                   + cos(deg2rad($lat)) * cos(deg2rad($pLat)) * sin($dLng / 2) ** 2;

                $distanceM  = $earthR * 2 * asin(sqrt($a));
                $product->distance_km = round($distanceM / 1000, 2);

                return $product;
            })
            ->filter(fn($p) => ($p->distance_km * 1000) <= $radius)
            ->sortBy([
                ['is_promoted', 'desc'],   // promoted products first
                ['distance_km', 'asc'],    // then nearest
            ])
            ->values();

            return ProductResource::collection($products);
        }

        // ── Fallback: no geo params → promoted first, then latest ─────────
        $products = $query
            ->orderByDesc('is_promoted')
            ->latest()
            ->paginate(20);

        return ProductResource::collection($products);
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

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        $product = Auth::user()->products()->create($data);

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
        return new ProductResource($product->load(['user', 'category', 'images']));
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
