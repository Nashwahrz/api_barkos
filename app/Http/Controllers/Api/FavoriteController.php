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
        $favorites = $request->user()->favorites()->with('product.images')->latest()->get();
        
        // Map to return just the products, or return the favorites with nested products
        $products = $favorites->map(function ($favorite) {
            return $favorite->product;
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Toggle the favorite status of a product.
     */
    public function toggle(Request $request, Product $product)
    {
        $user = $request->user();
        $favorite = $user->favorites()->where('product_id', $product->id)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Produk dihapus dari favorit',
                'is_favorited' => false
            ]);
        } else {
            $user->favorites()->create([
                'product_id' => $product->id
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Produk ditambahkan ke favorit',
                'is_favorited' => true
            ]);
        }
    }
}
