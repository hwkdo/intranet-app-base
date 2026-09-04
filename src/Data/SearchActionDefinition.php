<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class SearchActionDefinition
{
    /**
     * @param  list<string>  $keywords
     * @param  array<string, mixed>  $routeParameters
     * @param  array<string, mixed>  $queryParameters
     * @param  list<string>  $anyOfPermissions  If non-empty, eligible when the user can any of these (instead of $permission).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $keywords,
        public readonly string $routeName,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly string $icon,
        public readonly ?string $permission = null,
        public readonly ?string $subtitle = null,
        public readonly int $sort = 100,
        public readonly bool $download = false,
        public readonly array $routeParameters = [],
        public readonly array $queryParameters = [],
        public readonly array $anyOfPermissions = [],
    ) {}

    public function isEligible(Authenticatable $user): bool
    {
        if ($this->anyOfPermissions !== []) {
            if (! method_exists($user, 'can')) {
                return false;
            }

            foreach ($this->anyOfPermissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        }

        if ($this->permission === null) {
            return true;
        }

        if (! method_exists($user, 'can')) {
            return false;
        }

        return $user->can($this->permission);
    }

    public function url(): string
    {
        $url = route($this->routeName, $this->routeParameters);

        if ($this->queryParameters === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($this->queryParameters);
    }

    /**
     * Score 0.0–1.0; 0 means no match.
     */
    public function matchScore(string $query): float
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return 0.0;
        }

        $best = 0.0;

        foreach ($this->keywords as $keyword) {
            $normalizedKeyword = $this->normalize($keyword);

            if ($normalizedKeyword === '') {
                continue;
            }

            if ($normalizedKeyword === $normalizedQuery) {
                $best = max($best, 1.0);

                continue;
            }

            if (Str::startsWith($normalizedKeyword, $normalizedQuery)) {
                $best = max($best, 0.8);

                continue;
            }

            if (Str::contains($normalizedKeyword, $normalizedQuery)) {
                $best = max($best, 0.5);
            }
        }

        $normalizedTitle = $this->normalize($this->title);

        if ($normalizedTitle !== '') {
            if ($normalizedTitle === $normalizedQuery) {
                $best = max($best, 0.9);
            } elseif (Str::startsWith($normalizedTitle, $normalizedQuery)) {
                $best = max($best, 0.7);
            } elseif (Str::contains($normalizedTitle, $normalizedQuery)) {
                $best = max($best, 0.4);
            }
        }

        return $best;
    }

    public function toSearchResult(?float $score = null): SearchResult
    {
        return new SearchResult(
            title: $this->title,
            url: $this->url(),
            appIdentifier: $this->appIdentifier,
            appName: $this->appName,
            icon: $this->icon,
            subtitle: $this->subtitle ?? $this->appName,
            sourceKey: 'intranet.actions',
            score: $score,
            download: $this->download,
        );
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return $value;
    }
}
