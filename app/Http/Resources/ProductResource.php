<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nama_barang'    => $this->nama_barang,
            'deskripsi'      => $this->deskripsi,
            'harga'          => $this->harga,
            'minimum_offer_price' => $this->minimum_offer_price,
            'foto'           => $this->foto ? '/api/storage/' . $this->foto : null,
            'kondisi'        => $this->kondisi,
            'durasi_pemakaian' => $this->durasi_pemakaian,
            'status_terjual' => (bool) $this->status_terjual,
            'is_promoted'    => (bool) $this->is_promoted,
            'promoted_until' => $this->promoted_until,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'distance_km'    => $this->distance_km ?? null, // set dynamically in geo search
            'user'           => new UserResource($this->whenLoaded('user')),
            'category'       => new CategoryResource($this->whenLoaded('category')),
            'images'         => $this->whenLoaded('images', fn() =>
                $this->images->map(fn($img) => [
                    'id'         => $img->id,
                    'image_path' => '/api/storage/' . $img->image_path,
                    'is_primary' => (bool) $img->is_primary,
                ])
            ),
            'created_at'     => $this->created_at,
        ];
    }
}
