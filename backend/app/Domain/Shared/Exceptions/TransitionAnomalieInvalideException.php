<?php

namespace App\Domain\Shared\Exceptions;

class TransitionAnomalieInvalideException extends DomainException
{
    protected int $status = 422;

    public function __construct(string $message = 'Transition de statut d'anomalie invalide.')
    {
        parent::__construct($message);
    }
}
