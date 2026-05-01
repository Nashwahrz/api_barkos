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

        $users = User::latest()->get();

        return response()->json(['data' => $users]);
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
                'avatar'      => $user->avatar,
                'asal_kampus' => $user->asal_kampus,
                'role'        => $user->role,
                'is_active'   => $user->is_active,
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
