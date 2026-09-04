<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Hwkdo\IntranetAppBase\Contracts\UserSearchPreferencesSourceInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class DefaultUserSearchPreferencesSource implements UserSearchPreferencesSourceInterface
{
    public function showFavoritesInEmptySearch(Authenticatable $user): bool
    {
        return true;
    }

    public function showFavoritesHeaderIcon(Authenticatable $user): bool
    {
        return true;
    }

    public function previewResultsPerSource(Authenticatable $user): int
    {
        return 2;
    }
}
