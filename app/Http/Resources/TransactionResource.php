<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TransactionResource extends JsonResource
{
    /**
     * Phase 3 — TRD §6.3
     * Transform transaction to API response format.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'payment_method'       => $this->payment_method,
            'status'               => $this->status,
            'agreed_price'         => $this->agreed_price,
            'notes'                => $this->notes,
            'payment_proof_url'    => $this->payment_proof_path
                                        ? '/api/storage/' . $this->payment_proof_path
                                        : null,
            'has_payment_proof'    => !is_null($this->payment_proof_path),
            'product'              => $this->whenLoaded('product', fn() => [
                'id'          => $this->product->id,
                'nama_barang' => $this->product->nama_barang,
                'harga'       => $this->product->harga,
                'foto'        => $this->product->foto
                                    ? '/api/storage/' . $this->product->foto
                                    : null,
                'kondisi'     => $this->product->kondisi,
            ]),
            'buyer'                => $this->whenLoaded('buyer', fn() => [
                'id'     => $this->buyer->id,
                'name'   => $this->buyer->name,
                'phone'  => $this->buyer->phone,
                'avatar' => $this->buyer->avatar ? (str_starts_with($this->buyer->avatar, 'http') ? $this->buyer->avatar : '/api/storage/' . $this->buyer->avatar) : null,
            ]),
            'seller'               => $this->whenLoaded('seller', fn() => [
                'id'     => $this->seller->id,
                'name'   => $this->seller->name,
                'phone'  => $this->seller->phone,
                'avatar' => $this->seller->avatar ? (str_starts_with($this->seller->avatar, 'http') ? $this->seller->avatar : '/api/storage/' . $this->seller->avatar) : null,
                'bank_accounts' => $this->seller->bankAccounts ?? [],
            ]),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
