<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $documentPath = null;
        
        if ($request->hasFile('identity_document')) {
            $file = $request->file('identity_document');
            $documentPath = $file->store('identity_documents', 'public');
            
            // Verifikasi dokumen dengan OCR AI
            $verificationService = new \App\Services\KtpVerificationService();
            $absolutePath = storage_path('app/public/' . $documentPath);
            
            try {
                $isValid = $verificationService->verify($absolutePath);
                if (!$isValid) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($documentPath);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Pendaftaran gagal: Gambar yang diunggah tidak terdeteksi sebagai KTP atau KTM yang sah. Pastikan tulisan terbaca dengan jelas.'
                    ], 422);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($documentPath);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'asal_kampus' => $request->asal_kampus,
            'role' => $request->role ?? 'pembeli',
            'identity_document_path' => $documentPath,
            'is_identity_verified' => $documentPath ? true : false,
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('bankAccounts');

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi berhasil. KTP/KTM Anda telah lolos verifikasi AI otomatis. Silakan cek email Anda untuk verifikasi.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password yang Anda masukkan salah.',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('bankAccounts');

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'email_verified' => $user->hasVerifiedEmail(),
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        $user = $request->user();
        $user->load('bankAccounts');
        return new UserResource($user);
    }

    /**
     * Upgrade user role to penjual.
     */
    public function upgradeRole(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'penjual') {
            return response()->json([
                'message' => 'Anda sudah terdaftar sebagai penjual'
            ], 400);
        }

        $request->validate([
            'identity_document' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120']
        ]);

        $documentPath = null;
        if ($request->hasFile('identity_document')) {
            $file = $request->file('identity_document');
            $documentPath = $file->store('identity_documents', 'public');
            
            // Verifikasi dokumen dengan OCR AI
            $verificationService = new \App\Services\KtpVerificationService();
            $absolutePath = storage_path('app/public/' . $documentPath);
            
            try {
                $isValid = $verificationService->verify($absolutePath);
                if (!$isValid) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($documentPath);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Upgrade gagal: Gambar yang diunggah tidak terdeteksi sebagai KTP atau KTM yang sah. Pastikan tulisan terbaca dengan jelas.'
                    ], 422);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($documentPath);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $user->update([
            'role' => 'penjual',
            'identity_document_path' => $documentPath,
            'is_identity_verified' => $documentPath ? true : false,
        ]);

        return response()->json([
            'message' => 'Berhasil upgrade akun menjadi penjual lapak',
            'user'    => new UserResource($user)
        ]);
    }

    /**
     * Phase 2.2 — Update authenticated user profile.
     * PUT /api/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'phone'       => 'sometimes|nullable|string|max:20',
            'asal_kampus' => 'sometimes|nullable|string|max:255',
            'avatar'      => 'sometimes|nullable|image|max:10240',
        ]);

        $user = $request->user();
        $data = $request->only(['name', 'phone', 'asal_kampus']);

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists and not a Google avatar URL
            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * Phase 2.2 — Change authenticated user password.
     * PUT /api/password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    /**
     * Phase 2.2 — Save user geolocation coordinates.
     * PUT /api/location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $request->user()->update([
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json(['message' => 'Lokasi berhasil disimpan.']);
    }
}
