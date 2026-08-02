<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'message' => $this->message,
            'reply_to' => $this->whenLoaded('replyTo', function () {
                if (!$this->replyTo) {
                    return null;
                }
                return [
                    'id' => $this->replyTo->id,
                    'message' => $this->replyTo->message,
                    'sender' => $this->replyTo->relationLoaded('sender') && $this->replyTo->sender
                        ? new UserResource($this->replyTo->sender)
                        : null,
                ];
            }),
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at,
        ];
    }
}
