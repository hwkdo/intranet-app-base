<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum UserSignatureAction: string
{
    case Registered = 'registered';
    case Revoked = 'revoked';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registriert',
            self::Revoked => 'Widerrufen',
            self::Used => 'Verwendet',
        };
    }
}
