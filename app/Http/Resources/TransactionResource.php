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
                                        ? Storage::url($this->payment_proof_path)
                                        : null,
            'has_payment_proof'    => !is_null($this->payment_proof_path),
            'product'              => $this->whenLoaded('product', fn() => [
                'id'          => $this->product->id,
                'nama_barang' => $this->product->nama_barang,
                'harga'       => $this->product->harga,
                'foto'        => $this->product->foto
                                    ? Storage::url($this->product->foto)
                                    : null,
                'kondisi'     => $this->product->kondisi,
            ]),
            'buyer'                => $this->whenLoaded('buyer', fn() => [
                'id'     => $this->buyer->id,
                'name'   => $this->buyer->name,
                'phone'  => $this->buyer->phone,
                'avatar' => $this->buyer->avatar,
            ]),
            'seller'               => $this->whenLoaded('seller', fn() => [
                'id'     => $this->seller->id,
                'name'   => $this->seller->name,
                'phone'  => $this->seller->phone,
                'avatar' => $this->seller->avatar,
            ]),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
