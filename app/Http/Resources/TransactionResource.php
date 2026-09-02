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
            'metode_pembayaran'    => $this->metode_pembayaran,
            'status'               => $this->status,
            'harga_disepakati'     => $this->harga_disepakati,
            'catatan'              => $this->catatan,
            'url_bukti_pembayaran' => $this->jalur_bukti_pembayaran
                                        ? '/api/storage/' . $this->jalur_bukti_pembayaran
                                        : null,
            'punya_bukti_pembayaran' => !is_null($this->jalur_bukti_pembayaran),
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
                'nama'   => $this->buyer->nama,
                'no_telepon'  => $this->buyer->no_telepon,
                'foto_profil' => $this->buyer->foto_profil ? (str_starts_with($this->buyer->foto_profil, 'http') ? $this->buyer->foto_profil : '/api/storage/' . $this->buyer->foto_profil) : null,
            ]),
            'seller'               => $this->whenLoaded('seller', fn() => [
                'id'     => $this->seller->id,
                'nama'   => $this->seller->nama,
                'no_telepon'  => $this->seller->no_telepon,
                'foto_profil' => $this->seller->foto_profil ? (str_starts_with($this->seller->foto_profil, 'http') ? $this->seller->foto_profil : '/api/storage/' . $this->seller->foto_profil) : null,
                'bank_accounts' => $this->seller->bankAccounts ?? [],
            ]),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
