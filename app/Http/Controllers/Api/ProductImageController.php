<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    /**
     * Phase 2.3 — Upload additional images for a product.
     * POST /api/products/{product}/images
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        if ($product->id_pengguna !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'images'   => 'required|array|min:1|max:5',
            'images.*' => 'required|image|max:20480',
        ]);

        $uploaded = [];
        foreach ($request->file('images') as $file) {
            $path  = $file->store('products', 'public');
            $image = ProductImage::create([
                'id_produk' => $product->id_produk,
                'jalur_gambar' => $path,
                'utama' => false,
            ]);
            $uploaded[] = [
                'id_gambar_produk' => $image->id_gambar_produk,
                'jalur_gambar' => '/api/storage/' . $path,
                'utama'        => false,
            ];
        }

        return response()->json([
            'message' => count($uploaded) . ' gambar berhasil diunggah.',
            'data'    => $uploaded,
        ], 201);
    }

    /**
     * Phase 2.3 — Delete a product image.
     * DELETE /api/products/{product}/images/{image}
     */
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        if ($product->id_pengguna !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($image->id_produk !== $product->id_produk) {
            return response()->json(['message' => 'Image does not belong to this product'], 422);
        }

        Storage::disk('public')->delete($image->jalur_gambar);
        $image->delete();

        return response()->json(['message' => 'Gambar berhasil dihapus.']);
    }

    /**
     * Phase 2.3 — Set an image as primary.
     * PATCH /api/products/{product}/images/{image}/primary
     */
    public function setPrimary(Product $product, ProductImage $image): JsonResponse
    {
        if ($product->id_pengguna !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Unset all primary flags for this product's images first
        ProductImage::where('id_produk', $product->id_produk)->update(['utama' => false]);

        $image->update(['utama' => true]);

        return response()->json(['message' => 'Gambar utama berhasil diubah.']);
    }
}
