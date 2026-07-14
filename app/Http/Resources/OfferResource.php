<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->product_id,
            'buyer_id'      => $this->buyer_id,
            'seller_id'     => $this->seller_id,
            'product'       => new ProductResource($this->whenLoaded('product')),
            'buyer'         => new UserResource($this->whenLoaded('buyer')),
            'seller'        => new UserResource($this->whenLoaded('seller')),
            'offered_price' => $this->offered_price,
            'status'        => $this->status,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
