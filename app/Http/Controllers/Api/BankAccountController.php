<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::where('id_pengguna', Auth::id())->get();
        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
        ]);

        $account = BankAccount::create([
            'id_pengguna' => Auth::id(),
            'nama_bank' => $request->bank_name,
            'nomor_rekening' => $request->account_number,
            'nama_pemilik_rekening' => $request->account_name,
        ]);

        return response()->json(['message' => 'Rekening berhasil ditambahkan', 'data' => $account], 201);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        if ($bankAccount->id_pengguna !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
        ]);

        $bankAccount->update([
            'nama_bank' => $request->bank_name,
            'nomor_rekening' => $request->account_number,
            'nama_pemilik_rekening' => $request->account_name,
        ]);

        return response()->json(['message' => 'Rekening berhasil diubah', 'data' => $bankAccount]);
    }

    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->id_pengguna !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $bankAccount->delete();

        return response()->json(['message' => 'Rekening berhasil dihapus']);
    }
}
