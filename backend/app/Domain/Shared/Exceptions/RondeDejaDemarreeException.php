<?php

namespace App\Domain\Shared\Exceptions;

class RondeDejaDemarreeException extends DomainException
{
    protected int $status = 409;

    public function __construct(string $message = 'Une ronde est déjà démarrée.')
    {
        parent::__construct($message);
    }
}
