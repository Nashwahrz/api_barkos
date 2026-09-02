<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentBankAccount;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentSettingController extends Controller
{
    /**
     * Get current payment settings + active bank accounts (any authenticated user).
     */
    public function show(): JsonResponse
    {
        $settings = PaymentSetting::current();

        return response()->json([
            'data' => [
                'midtrans_enabled' => $settings->midtrans_diaktifkan,
                'manual_transfer_enabled' => $settings->transfer_manual_diaktifkan,
                'qris_image_url' => $settings->jalur_gambar_qris ? '/api/storage/' . $settings->jalur_gambar_qris : null,
                'bank_accounts' => PaymentBankAccount::where('aktif', true)->get(),
            ],
        ]);
    }

    /**
     * Update payment settings (Admin only).
     */
    public function update(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'midtrans_enabled' => 'sometimes|boolean',
            'manual_transfer_enabled' => 'sometimes|boolean',
            'qris_image' => 'nullable|image|max:5120',
        ]);

        $settings = PaymentSetting::current();

        if ($request->hasFile('qris_image')) {
            if ($settings->jalur_gambar_qris) {
                Storage::disk('public')->delete($settings->jalur_gambar_qris);
            }
            $settings->jalur_gambar_qris = $request->file('qris_image')->store('payments/qris', 'public');
        }

        if ($request->has('midtrans_enabled')) {
            $settings->midtrans_diaktifkan = $request->boolean('midtrans_enabled');
        }
        if ($request->has('manual_transfer_enabled')) {
            $settings->transfer_manual_diaktifkan = $request->boolean('manual_transfer_enabled');
        }

        $settings->save();

        return response()->json([
            'message' => 'Pengaturan pembayaran berhasil diperbarui.',
            'data' => [
                'midtrans_enabled' => $settings->midtrans_diaktifkan,
                'manual_transfer_enabled' => $settings->transfer_manual_diaktifkan,
                'qris_image_url' => $settings->jalur_gambar_qris ? '/api/storage/' . $settings->jalur_gambar_qris : null,
            ],
        ]);
    }
}
