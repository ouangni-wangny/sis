<?php

namespace App\Domain\Shared\Exceptions;

use Illuminate\Http\JsonResponse;

class VacationConflitException extends DomainException
{
    protected int $status = 409;

    public function __construct(
        string $message = 'Conflit de vacation détecté pour cet agent.',
        public readonly ?string $blockingVacationId = null,
    ) {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => class_basename(static::class),
            'blocking_vacation_id' => $this->blockingVacationId,
        ], $this->status);
    }
}
