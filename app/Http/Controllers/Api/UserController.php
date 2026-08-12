<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users (Super Admin Only).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::withCount('receivedReports')->latest()->get();
        $users->each(fn (User $u) => $u->avatar = self::resolveAvatarUrl($u->avatar));

        return response()->json(['data' => $users]);
    }

    /**
     * Normalize a stored avatar value into a usable URL: Google avatars are
     * already full URLs, while self-uploaded avatars are bare storage paths
     * (e.g. "avatars/xxx.jpg") that need the "/api/storage/" prefix — same
     * transform as UserResource::toArray() applies elsewhere.
     */
    private static function resolveAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        return str_starts_with($avatar, 'http') ? $avatar : '/api/storage/' . $avatar;
    }

    /**
     * Public-safe brief profile for any logged-in user to view about another user
     * (e.g. tapping a seller's name on a product page or in a chat thread).
     * Deliberately excludes contact details (email/phone/location) that the
     * admin-only show() endpoint below returns.
     */
    public function publicProfile(User $user): JsonResponse
    {
        return response()->json([
            'data' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'avatar'      => self::resolveAvatarUrl($user->avatar),
                'asal_kampus' => $user->asal_kampus,
                'role'        => $user->role,
                'created_at'  => $user->created_at,
                'is_online'   => $user->isOnline(),
                'last_active_at' => $user->last_active_at,
                'activity'    => [
                    'products_count'        => $user->products()->where('status_terjual', false)->count(),
                    'products_sold_count'   => $user->products()->where('status_terjual', true)->count(),
                ],
            ],
        ]);
    }

    /**
     * Display the specified user profile.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'avatar'      => self::resolveAvatarUrl($user->avatar),
                'identity_document_url' => self::resolveAvatarUrl($user->identity_document_path),
                'asal_kampus' => $user->asal_kampus,
                'role'        => $user->role,
                'is_active'   => $user->is_active,
                'created_at'  => $user->created_at,
                'last_active_at' => $user->last_active_at,
                'is_online'   => $user->isOnline(),
                'activity'    => [
                    'products_count'          => $user->products()->count(),
                    'products_sold_count'     => $user->products()->where('status_terjual', true)->count(),
                    'transactions_as_seller_count'   => $user->sellerTransactions()->count(),
                    'transactions_completed_count'   => $user->sellerTransactions()->where('status', 'completed')->count(),
                    'transactions_as_buyer_count'    => $user->buyerTransactions()->count(),
                ],
            ]
        ]);
    }

    /**
     * Phase 2.4 — PRD §2.3.2
     * Activate or deactivate a user account (Super Admin only).
     * PATCH /api/users/{user}/status
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Cannot deactivate super admin'], 422);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message'   => $user->is_active ? 'Akun pengguna diaktifkan.' : 'Akun pengguna dinonaktifkan.',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Remove the specified user from storage (Super Admin Only).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Cannot delete super admin'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
