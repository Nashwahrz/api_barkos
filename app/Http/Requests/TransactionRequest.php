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
            'product_id'     => 'required|exists:products,id',
            'payment_method' => 'required|in:cod,bank_transfer',
            'agreed_price'   => 'required|integer|min:1',
            'notes'          => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required'     => 'Produk harus dipilih.',
            'product_id.exists'       => 'Produk tidak ditemukan.',
            'payment_method.required' => 'Metode pembayaran harus dipilih.',
            'payment_method.in'       => 'Metode pembayaran harus COD atau transfer bank.',
            'agreed_price.required'   => 'Harga kesepakatan harus diisi.',
            'agreed_price.min'        => 'Harga harus lebih dari 0.',
        ];
    }
}
