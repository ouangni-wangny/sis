<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SisNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'titre' => $this->titre,
            'message' => $this->message,
            'meta' => $this->meta,
            'lue_le' => $this->lue_le,
            'created_at' => $this->created_at,
        ];
    }
}
