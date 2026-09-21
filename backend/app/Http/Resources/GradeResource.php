<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'libelle' => $this->libelle,
        'type_agent' => $this->type_agent,
        'description' => $this->description,
        ];
    }
}
