<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
// use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::all());
    }

    /**
     * Phase 5.2 - Store a newly created category (Admin Only).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->only('name', 'description'));

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => new CategoryResource($category)
        ], 201);
    }

    /**
     * Phase 5.2 - Update the specified category (Admin Only).
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($request->only('name', 'description'));

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => new CategoryResource($category)
        ]);
    }

    /**
     * Phase 5.2 - Remove the specified category (Admin Only).
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus kategori yang masih memiliki produk.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.'
        ]);
    }
}
