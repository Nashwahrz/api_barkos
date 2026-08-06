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
            'minimum_offer_price' => $this->minimum_offer_price,
            'is_offer_enabled' => (bool) $this->is_offer_enabled,
            'foto'           => $this->foto ? '/api/storage/' . $this->foto : null,
            'kondisi'        => $this->kondisi,
            'durasi_pemakaian' => $this->durasi_pemakaian,
            'status_terjual' => (bool) $this->status_terjual,
            'sold_at'        => $this->sold_at,
            'is_promoted'    => $this->isPromotedFor($viewer?->id, $bypassTargeting),
            'is_favorited'   => $this->is_favorited,
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
