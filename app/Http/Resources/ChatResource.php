<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_obrolan' => $this->id_obrolan,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'pesan' => $this->pesan,
            'reply_to' => $this->whenLoaded('replyTo', function () {
                if (!$this->replyTo) {
                    return null;
                }
                return [
                    'id_obrolan' => $this->replyTo->id_obrolan,
                    'pesan' => $this->replyTo->pesan,
                    'sender' => $this->replyTo->relationLoaded('sender') && $this->replyTo->sender
                        ? new UserResource($this->replyTo->sender)
                        : null,
                ];
            }),
            'sudah_dibaca' => (bool) $this->sudah_dibaca,
            'created_at' => $this->created_at,
        ];
    }
}
