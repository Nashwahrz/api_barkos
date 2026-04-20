<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_barang' => $this->nama_barang,
            'deskripsi' => $this->deskripsi,
            'harga' => $this->harga,
            'foto' => $this->foto,
            'kondisi' => $this->kondisi,
            'status_terjual' => (bool) $this->status_terjual,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'user' => new UserResource($this->whenLoaded('user')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at,
        ];
    }
}
