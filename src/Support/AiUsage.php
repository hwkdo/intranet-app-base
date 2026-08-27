<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

final class AiUsage
{
    public const PERMISSION = 'allow_ai_usage';

    public const DENIED_MESSAGE = 'Sie sind nicht zur KI-Nutzung berechtigt.';

    public static function allowed(?Authorizable $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && $user->can(self::PERMISSION);
    }
}
