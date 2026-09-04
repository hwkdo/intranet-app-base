<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Hwkdo\IntranetAppBase\Models\IntranetSearchFavorite;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SearchFavoriteStore
{
    public const MAX_FAVORITES = 25;

    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    /**
     * @return Collection<int, SearchResult>
     */
    public function list(Authenticatable $user): Collection
    {
        $favorites = IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($favorites->isEmpty()) {
            return collect();
        }

        $sources = $this->searchService
            ->resolveSources($user)
            ->keyBy(fn (SearchSourceInterface $source): string => $source->key());

        return $favorites
            ->map(function (IntranetSearchFavorite $favorite) use ($user, $sources): ?SearchResult {
                $source = $sources->get($favorite->source_key);

                if ($source instanceof SearchSourceInterface) {
                    try {
                        $entityId = $this->entityIdFromKey($favorite->favorite_key, $favorite->source_key);
                        $resolved = $source->resolveFavorite($entityId, $user);

                        if ($resolved !== null) {
                            return $resolved;
                        }

                        // Source is available but item is gone / not allowed → hide.
                        return null;
                    } catch (\Throwable $exception) {
                        Log::warning('SearchFavorite resolve failed', [
                            'favorite_key' => $favorite->favorite_key,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                // Source unavailable (e.g. lost app permission) → hide.
                if ($source === null) {
                    return null;
                }

                return $favorite->toSearchResult();
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    public function favoritedKeys(Authenticatable $user): array
    {
        return IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->pluck('favorite_key')
            ->all();
    }

    public function isFavorited(Authenticatable $user, string $favoriteKey): bool
    {
        return IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('favorite_key', $favoriteKey)
            ->exists();
    }

    /**
     * @return bool True when the result is favorited after the toggle.
     *
     * @throws RuntimeException When the limit would be exceeded.
     */
    public function toggle(Authenticatable $user, SearchResult $result): bool
    {
        $existing = IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('favorite_key', $result->favoriteKey)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        $count = IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->count();

        if ($count >= self::MAX_FAVORITES) {
            throw new RuntimeException('Maximal '.self::MAX_FAVORITES.' Favoriten möglich.');
        }

        $nextSort = (int) IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->max('sort') + 1;

        IntranetSearchFavorite::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'favorite_key' => $result->favoriteKey,
            'title' => $result->title,
            'url' => $result->url,
            'icon' => $result->icon,
            'subtitle' => $result->subtitle,
            'app_identifier' => $result->appIdentifier,
            'app_name' => $result->appName,
            'source_key' => $result->sourceKey ?? $result->appIdentifier,
            'download' => $result->download,
            'sort' => $nextSort,
        ]);

        return true;
    }

    public function remove(Authenticatable $user, string $favoriteKey): void
    {
        IntranetSearchFavorite::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('favorite_key', $favoriteKey)
            ->delete();
    }

    private function entityIdFromKey(string $favoriteKey, string $sourceKey): string
    {
        $prefix = $sourceKey.':';

        if (str_starts_with($favoriteKey, $prefix)) {
            return substr($favoriteKey, strlen($prefix));
        }

        $pos = strpos($favoriteKey, ':');

        return $pos === false ? $favoriteKey : substr($favoriteKey, $pos + 1);
    }
}
