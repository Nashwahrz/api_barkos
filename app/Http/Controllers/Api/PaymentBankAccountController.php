<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentBankAccountController extends Controller
{
    /**
     * List all bank accounts (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => PaymentBankAccount::latest()->get()]);
    }

    /**
     * Create a new bank account (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:100',
            'account_name' => 'required|string|max:255',
        ]);

        $account = PaymentBankAccount::create([
            'nama_bank' => $request->bank_name,
            'nomor_rekening' => $request->account_number,
            'nama_pemilik_rekening' => $request->account_name,
            'aktif' => true,
        ]);

        return response()->json(['message' => 'Rekening berhasil ditambahkan.', 'data' => $account], 201);
    }

    /**
     * Update a bank account (Admin only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $account = PaymentBankAccount::findOrFail($id);

        $request->validate([
            'bank_name' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|max:100',
            'account_name' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = [];
        if ($request->has('bank_name')) {
            $data['nama_bank'] = $request->input('bank_name');
        }
        if ($request->has('account_number')) {
            $data['nomor_rekening'] = $request->input('account_number');
        }
        if ($request->has('account_name')) {
            $data['nama_pemilik_rekening'] = $request->input('account_name');
        }
        if ($request->has('is_active')) {
            $data['aktif'] = $request->boolean('is_active');
        }

        $account->update($data);

        return response()->json(['message' => 'Rekening berhasil diperbarui.', 'data' => $account]);
    }

    /**
     * Delete a bank account (Admin only).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        PaymentBankAccount::findOrFail($id)->delete();

        return response()->json(['message' => 'Rekening berhasil dihapus.']);
    }
}
