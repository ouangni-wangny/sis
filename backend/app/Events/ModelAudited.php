<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelAudited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $action,
        public ?array $ancien,
        public ?array $nouveau,
    ) {}
}
