<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Get platform statistics for Super Admin.
     */
    public function stats(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'users' => [
                'total' => User::count(),
                'penjual' => User::where('role', 'penjual')->count(),
                'pembeli' => User::where('role', 'pembeli')->count(),
                'super_admin' => User::where('role', 'super_admin')->count(),
            ],
            'products' => [
                'total' => Product::count(),
                'available' => Product::where('status_terjual', false)->count(),
                'sold' => Product::where('status_terjual', true)->count(),
            ],
            'reports' => [
                'total' => Report::count(),
                'pending' => Report::where('status', 'pending')->count(),
                'resolved' => Report::where('status', 'resolved')->count(),
            ],
            'activities' => [
                'chats' => Chat::count(),
            ]
        ];

        return response()->json([
            'stats' => $stats
        ]);
    }

    /**
     * Get recent activities for the dashboard.
     */
    public function recentActivities(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $recentUsers = User::latest()->limit(5)->get();
        $recentProducts = Product::with(['user', 'category'])->latest()->limit(5)->get();
        $recentReports = Report::with(['reporter', 'product'])->latest()->limit(5)->get();

        return response()->json([
            'recent_users' => $recentUsers,
            'recent_products' => $recentProducts,
            'recent_reports' => $recentReports,
        ]);
    }
}
