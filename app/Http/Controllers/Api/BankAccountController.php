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
        $accounts = BankAccount::where('user_id', Auth::id())->get();
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
            'user_id' => Auth::id(),
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
        ]);

        return response()->json(['message' => 'Rekening berhasil ditambahkan', 'data' => $account], 201);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        if ($bankAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
        ]);

        $bankAccount->update($request->only('bank_name', 'account_number', 'account_name'));

        return response()->json(['message' => 'Rekening berhasil diubah', 'data' => $bankAccount]);
    }

    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $bankAccount->delete();

        return response()->json(['message' => 'Rekening berhasil dihapus']);
    }
}
