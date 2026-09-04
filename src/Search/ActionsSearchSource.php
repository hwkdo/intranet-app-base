<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Search;

use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Hwkdo\IntranetAppBase\Services\SearchActionCatalog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class ActionsSearchSource implements SearchSourceInterface
{
    public function __construct(
        private readonly SearchActionCatalog $catalog,
    ) {}

    public function key(): string
    {
        return 'intranet.actions';
    }

    public function label(): string
    {
        return 'Aktionen';
    }

    public function appIdentifier(): string
    {
        return 'actions';
    }

    public function appName(): string
    {
        return 'Aktionen';
    }

    public function icon(): string
    {
        return 'bolt';
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        return true;
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return $this->catalog
            ->forUser($user)
            ->map(function ($definition) use ($query): ?SearchResult {
                /** @var \Hwkdo\IntranetAppBase\Data\SearchActionDefinition $definition */
                $score = $definition->matchScore($query);

                if ($score <= 0.0) {
                    return null;
                }

                return $definition->toSearchResult($score);
            })
            ->filter()
            ->sortByDesc(fn (SearchResult $result): float => $result->score ?? 0.0)
            ->take($limit)
            ->values();
    }

    public function resolveFavorite(string $entityId, Authenticatable $user): ?SearchResult
    {
        $definition = $this->catalog
            ->forUser($user)
            ->first(fn ($item): bool => $item->key === $entityId);

        if ($definition === null) {
            return null;
        }

        return $definition->toSearchResult();
    }
}
