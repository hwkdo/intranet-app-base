<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface UserSearchPreferencesSourceInterface
{
    public function showFavoritesInEmptySearch(Authenticatable $user): bool;

    public function showFavoritesHeaderIcon(Authenticatable $user): bool;

    public function previewResultsPerSource(Authenticatable $user): int;
}
