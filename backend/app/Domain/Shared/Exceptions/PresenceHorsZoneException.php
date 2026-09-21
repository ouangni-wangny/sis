<?php

namespace App\Domain\Shared\Exceptions;

class PresenceHorsZoneException extends DomainException
{
    protected int $status = 422;

    public function __construct(string $message = 'Présence hors zone autorisée.')
    {
        parent::__construct($message);
    }
}
