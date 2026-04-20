<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'harga' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'string'], // Simplified as per user choice earlier (string/URL)
            'kondisi' => ['required', Rule::in(['baru', 'sangat baik', 'layak pakai'])],
            'status_terjual' => ['nullable', 'boolean'],
        ];
    }
}
