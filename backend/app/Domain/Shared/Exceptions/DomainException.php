<?php

namespace App\Domain\Shared\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DomainException extends Exception
{
    protected int $status = 422;

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => class_basename(static::class),
        ], $this->status);
    }

    public function status(): int
    {
        return $this->status;
    }
}
