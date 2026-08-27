<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Illuminate\Contracts\Auth\Authenticatable;

class SetupDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly string $group,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly string $component,
        public readonly int $sort = 0,
        public readonly int $version = 1,
        public readonly ?string $permission = null,
    ) {}

    public function isEligible(Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        if (! method_exists($user, 'can')) {
            return false;
        }

        return $user->can($this->permission);
    }
}
