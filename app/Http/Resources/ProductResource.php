<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = auth('sanctum')->user();
        $bypassTargeting = $viewer && ($viewer->id === $this->user_id || $viewer->role === 'super_admin');

        return [
            'id'             => $this->id,
            'nama_barang'    => $this->nama_barang,
            'deskripsi'      => $this->deskripsi,
            'harga'          => $this->harga,
            'harga_minimum_tawaran' => $this->harga_minimum_tawaran,
            'tawaran_diaktifkan' => (bool) $this->tawaran_diaktifkan,
            'foto'           => $this->foto ? '/api/storage/' . $this->foto : null,
            'kondisi'        => $this->kondisi,
            'durasi_pemakaian' => $this->durasi_pemakaian,
            'metode_pembayaran' => $this->metode_pembayaran,
            'status_terjual' => (bool) $this->status_terjual,
            'terjual_pada'   => $this->terjual_pada,
            'dipromosikan'   => $this->isPromotedFor($viewer?->id, $bypassTargeting),
            'is_favorited'   => $this->is_favorited,
            'dipromosikan_hingga' => $this->dipromosikan_hingga,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'distance_km'    => $this->distance_km ?? null, // set dynamically in geo search
            'user'           => new UserResource($this->whenLoaded('user')),
            'category'       => new CategoryResource($this->whenLoaded('category')),
            'images'         => $this->whenLoaded('images', fn() =>
                $this->images->map(fn($img) => [
                    'id'           => $img->id,
                    'jalur_gambar' => '/api/storage/' . $img->jalur_gambar,
                    'utama'        => (bool) $img->utama,
                ])
            ),
            'created_at'     => $this->created_at,
        ];
    }
}
