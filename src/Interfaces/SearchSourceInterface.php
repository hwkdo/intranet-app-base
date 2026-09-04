<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\SearchResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface SearchSourceInterface
{
    public function key(): string;

    public function label(): string;

    public function appIdentifier(): string;

    public function appName(): string;

    public function icon(): string;

    public function isAvailableFor(Authenticatable $user): bool;

    /**
     * @return Collection<int, SearchResult>
     */
    public function search(string $query, Authenticatable $user, int $limit): Collection;

    /**
     * Re-resolve a favorited entity for the current user.
     * Return null when the item no longer exists or the user may not see it.
     */
    public function resolveFavorite(string $entityId, Authenticatable $user): ?SearchResult;
}
