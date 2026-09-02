<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Favorite;

class FavoriteController extends Controller
{
    /**
     * Get the authenticated user's favorite products.
     */
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with(['product.images', 'product.user'])->latest()->get();
        
        // Map to return just the products
        $products = $favorites->map(function ($favorite) {
            return $favorite->product;
        });
        
        return response()->json([
            'status' => 'success',
            'data' => \App\Http\Resources\ProductResource::collection($products)
        ]);
    }

    /**
     * Toggle the favorite status of a product.
     */
    public function toggle(Request $request, Product $product)
    {
        $user = $request->user();
        $favorite = $user->favorites()->where('id_produk', $product->id_produk)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Produk dihapus dari favorit',
                'is_favorited' => false
            ]);
        } else {
            $user->favorites()->create([
                'id_produk' => $product->id_produk
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Produk ditambahkan ke favorit',
                'is_favorited' => true
            ]);
        }
    }
}
