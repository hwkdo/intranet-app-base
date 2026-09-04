<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Contracts\GlobalSearchSettingsSourceInterface;
use Hwkdo\IntranetAppBase\Contracts\UserSearchPreferencesSourceInterface;
use Hwkdo\IntranetAppBase\Data\SearchResponse;
use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSearchInterface;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchService
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     * @param  \Closure(string, array): ?string|null  $appClassResolver
     * @param  \Closure(): list<class-string<SearchSourceInterface>>|null  $hostSourcesResolver
     */
    public function __construct(
        private readonly GlobalSearchSettingsSourceInterface $settingsSource,
        private readonly ?\Closure $packagesResolver = null,
        private readonly ?\Closure $appClassResolver = null,
        private readonly ?\Closure $hostSourcesResolver = null,
        private readonly ?UserSearchPreferencesSourceInterface $userPreferences = null,
    ) {}
    public function minChars(): int
    {
        return max(1, $this->settingsSource->minChars());
    }

    public function previewLimit(): int
    {
        return max(1, $this->settingsSource->previewLimit());
    }

    public function modalLimit(): int
    {
        return max(1, $this->settingsSource->modalLimit());
    }

    public function searchPreview(Authenticatable $user, string $query): SearchResponse
    {
        return $this->search(
            $user,
            $query,
            $this->previewLimit(),
            $this->userPreferencesSource()->previewResultsPerSource($user),
        );
    }

    public function searchModal(Authenticatable $user, string $query): SearchResponse
    {
        return $this->search($user, $query, $this->modalLimit());
    }

    public function search(
        Authenticatable $user,
        string $query,
        int $limit,
        ?int $maxPerSource = null,
    ): SearchResponse {
        $query = trim($query);

        if ($query === '' || Str::length($query) < $this->minChars()) {
            return new SearchResponse(collect(), collect(), 0);
        }

        $merged = collect();

        foreach ($this->resolveSources($user) as $source) {
            try {
                $merged = $merged->merge($source->search($query, $user, $limit));
            } catch (\Throwable $exception) {
                Log::error('SearchSource failed', [
                    'source' => $source::class,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $sorted = $this->sortResults($merged);
        $totalCount = $sorted->count();
        $results = $this->diversifyResults($sorted, $limit, $maxPerSource);

        return new SearchResponse(
            results: $results,
            groupedResults: $results->groupBy('appIdentifier'),
            totalCount: $totalCount,
        );
    }

    /**
     * @return Collection<int, SearchSourceInterface>
     */
    public function resolveSources(Authenticatable $user): Collection
    {
        $sources = collect();

        foreach ($this->hostSourceClasses() as $sourceClass) {
            if (! class_exists($sourceClass)) {
                Log::warning('Host SearchSource class not found', ['class' => $sourceClass]);

                continue;
            }

            /** @var SearchSourceInterface $source */
            $source = app($sourceClass);

            if ($source->isAvailableFor($user)) {
                $sources->push($source);
            }
        }

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = $this->resolveAppClass($packageName, $packageData);

            if ($appClass === null || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesSearchInterface::class, true)) {
                continue;
            }

            if (! is_a($appClass, IntranetAppInterface::class, true)) {
                continue;
            }

            if (! $this->userCanSeeApp($user, $appClass::identifier())) {
                continue;
            }

            foreach ($appClass::searchSources() as $sourceClass) {
                if (! class_exists($sourceClass)) {
                    Log::warning('SearchSource class not found', ['class' => $sourceClass]);

                    continue;
                }

                /** @var SearchSourceInterface $source */
                $source = app($sourceClass);

                if ($source->isAvailableFor($user)) {
                    $sources->push($source);
                }
            }
        }

        return $sources;
    }

    private function userCanSeeApp(Authenticatable $user, string $identifier): bool
    {
        if (! method_exists($user, 'can')) {
            return true;
        }

        return $user->can('see-app-'.$identifier);
    }

    /**
     * @param  Collection<int, SearchResult>  $results
     * @return Collection<int, SearchResult>
     */
    private function sortResults(Collection $results): Collection
    {
        return $results
            ->sortByDesc(fn (SearchResult $result): float => $result->score ?? 0.0)
            ->values();
    }

    /**
     * Round-robin across sources so one noisy source (e.g. users) cannot fill the entire limit.
     *
     * @param  Collection<int, SearchResult>  $results
     * @return Collection<int, SearchResult>
     */
    private function diversifyResults(Collection $results, int $limit, ?int $maxPerSource = null): Collection
    {
        if ($results->count() <= $limit && $maxPerSource === null) {
            return $results->values();
        }

        /** @var Collection<string, Collection<int, SearchResult>> $bySource */
        $bySource = $results
            ->groupBy(fn (SearchResult $result): string => $result->sourceKey ?? $result->appIdentifier)
            ->map(function (Collection $group) use ($maxPerSource): Collection {
                $values = $group->values();

                if ($maxPerSource !== null && $maxPerSource > 0) {
                    return $values->take($maxPerSource)->values();
                }

                return $values;
            });

        if ($bySource->sum(fn (Collection $group): int => $group->count()) <= $limit) {
            return $bySource->flatten(1)->values();
        }

        $diversified = collect();

        while ($diversified->count() < $limit) {
            $added = false;

            foreach ($bySource as $key => $group) {
                if ($group->isEmpty()) {
                    continue;
                }

                $diversified->push($group->shift());
                $added = true;

                if ($diversified->count() >= $limit) {
                    break;
                }
            }

            if (! $added) {
                break;
            }
        }

        return $diversified->values();
    }

    private function userPreferencesSource(): UserSearchPreferencesSourceInterface
    {
        return $this->userPreferences ?? app(UserSearchPreferencesSourceInterface::class);
    }

    /**
     * @return list<class-string<SearchSourceInterface>>
     */
    private function hostSourceClasses(): array
    {
        if ($this->hostSourcesResolver !== null) {
            return ($this->hostSourcesResolver)();
        }

        /** @var list<class-string<SearchSourceInterface>> $sources */
        $sources = config('intranet-app-base.search_sources', []);

        return $sources;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePackages(): array
    {
        if ($this->packagesResolver !== null) {
            return ($this->packagesResolver)();
        }

        return IntranetAppBase::getIntranetAppPackages();
    }

    private function resolveAppClass(string $packageName, array $packageData): ?string
    {
        if ($this->appClassResolver !== null) {
            return ($this->appClassResolver)($packageName, $packageData);
        }

        return IntranetAppBase::getAppClass($packageName, $packageData);
    }
}
