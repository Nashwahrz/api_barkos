<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Phase 3 — PRD §2.2.3, TRD §6.3
     * Validate buyer's order placement request.
     */
    public function rules(): array
    {
        return [
            'id_produk'         => 'required|exists:produk,id_produk',
            'metode_pembayaran' => 'required|in:cod,bank_transfer',
            'harga_disepakati'  => 'required|integer|min:1',
            'catatan'           => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'id_produk.required'         => 'Produk harus dipilih.',
            'id_produk.exists'           => 'Produk tidak ditemukan.',
            'metode_pembayaran.required' => 'Metode pembayaran harus dipilih.',
            'metode_pembayaran.in'       => 'Metode pembayaran harus COD atau transfer bank.',
            'harga_disepakati.required'  => 'Harga kesepakatan harus diisi.',
            'harga_disepakati.min'       => 'Harga harus lebih dari 0.',
        ];
    }
}
