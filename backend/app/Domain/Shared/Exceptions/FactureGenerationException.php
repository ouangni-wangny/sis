<?php

namespace App\Domain\Shared\Exceptions;

class FactureGenerationException extends DomainException
{
    protected int $status = 422;

    public function __construct(string $message = 'Impossible de générer la facture.')
    {
        parent::__construct($message);
    }
}
