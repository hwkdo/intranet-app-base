<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Carbon\Carbon;
use Hwkdo\IntranetAppBase\Data\AppReleaseInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GithubAppReleaseService
{
    private const int MAX_TAGS = 50;

    /**
     * @return Collection<int, AppReleaseInfo>
     */
    public function releasesForRepository(string $owner, string $repo): Collection
    {
        $cacheKey = "intranet-app-base.github-releases.v2.{$owner}.{$repo}";
        $ttl = (int) config('intranet-app-base.github_release_cache_ttl', 3600);

        /** @var Collection<int, AppReleaseInfo> $releases */
        $releases = Cache::remember($cacheKey, $ttl, function () use ($owner, $repo): Collection {
            return $this->fetchReleases($owner, $repo);
        });

        return $releases;
    }

    public function findReleaseForTag(Collection $releases, string $versionTag): ?AppReleaseInfo
    {
        $normalized = $this->normalizeTag($versionTag);

        return $releases->first(
            fn (AppReleaseInfo $release): bool => $this->normalizeTag($release->tagName) === $normalized
        );
    }

    public function previousRelease(Collection $releases, AppReleaseInfo $current): ?AppReleaseInfo
    {
        $sorted = $releases->sortByDesc(
            fn (AppReleaseInfo $release): int => $release->publishedAt?->getTimestamp() ?? 0
        )->values();

        $index = $sorted->search(
            fn (AppReleaseInfo $release): bool => $this->normalizeTag($release->tagName) === $this->normalizeTag($current->tagName)
        );

        if ($index === false || ! is_int($index)) {
            return null;
        }

        return $sorted->get($index + 1);
    }

    /**
     * @return Collection<int, AppReleaseInfo>
     */
    private function fetchReleases(string $owner, string $repo): Collection
    {
        try {
            $published = $this->fetchPublishedReleases($owner, $repo);

            if ($published->isNotEmpty()) {
                return $published;
            }

            return $this->fetchReleasesFromTags($owner, $repo);
        } catch (RequestException|Throwable) {
            return collect();
        }
    }

    /**
     * @return Collection<int, AppReleaseInfo>
     */
    private function fetchPublishedReleases(string $owner, string $repo): Collection
    {
        $response = $this->httpClient()
            ->get("https://api.github.com/repos/{$owner}/{$repo}/releases", [
                'per_page' => 100,
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            return collect();
        }

        return collect($payload)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['tag_name']))
            ->map(function (array $item): AppReleaseInfo {
                $publishedAt = isset($item['published_at']) && is_string($item['published_at'])
                    ? Carbon::parse($item['published_at'])
                    : null;

                return new AppReleaseInfo(
                    tagName: (string) $item['tag_name'],
                    name: (string) ($item['name'] ?? ''),
                    body: (string) ($item['body'] ?? ''),
                    htmlUrl: (string) ($item['html_url'] ?? ''),
                    publishedAt: $publishedAt,
                );
            })
            ->sortByDesc(fn (AppReleaseInfo $release): int => $release->publishedAt?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Fallback when only git tags exist (no formal GitHub Releases). Uses commit messages as notes.
     *
     * @return Collection<int, AppReleaseInfo>
     */
    private function fetchReleasesFromTags(string $owner, string $repo): Collection
    {
        $response = $this->httpClient()
            ->get("https://api.github.com/repos/{$owner}/{$repo}/tags", [
                'per_page' => self::MAX_TAGS,
            ])
            ->throw();

        $tags = $response->json();

        if (! is_array($tags)) {
            return collect();
        }

        $tagItems = collect($tags)
            ->filter(fn (mixed $tag): bool => is_array($tag) && isset($tag['name'], $tag['commit']['sha']))
            ->take(self::MAX_TAGS);

        if ($tagItems->isEmpty()) {
            return collect();
        }

        $commitsBySha = $this->fetchCommitsBySha($owner, $repo, $tagItems);

        return $tagItems
            ->map(function (array $tag) use ($owner, $repo, $commitsBySha): AppReleaseInfo {
                $tagName = (string) $tag['name'];
                $sha = (string) $tag['commit']['sha'];
                $commit = $commitsBySha[$sha] ?? null;
                $message = is_array($commit)
                    ? trim((string) (($commit['commit'] ?? [])['message'] ?? ''))
                    : '';

                $firstLine = str($message)->before("\n")->trim()->toString();
                $title = $firstLine !== '' ? $firstLine : $tagName;
                $body = $message !== '' && $message !== $firstLine ? $message : '';

                $publishedAt = null;

                if (is_array($commit)) {
                    $date = ($commit['commit']['committer']['date'] ?? null)
                        ?? ($commit['commit']['author']['date'] ?? null);

                    if (is_string($date)) {
                        $publishedAt = Carbon::parse($date);
                    }
                }

                return new AppReleaseInfo(
                    tagName: $tagName,
                    name: $title,
                    body: $body,
                    htmlUrl: "https://github.com/{$owner}/{$repo}/releases/tag/{$tagName}",
                    publishedAt: $publishedAt,
                );
            })
            ->sortByDesc(fn (AppReleaseInfo $release): int => $release->publishedAt?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tagItems
     * @return array<string, array<string, mixed>>
     */
    private function fetchCommitsBySha(string $owner, string $repo, Collection $tagItems): array
    {
        $shas = $tagItems
            ->map(fn (array $tag): string => (string) $tag['commit']['sha'])
            ->unique()
            ->values();

        $cached = [];
        $toFetch = [];

        foreach ($shas as $sha) {
            $cacheKey = "intranet-app-base.github-commit.v1.{$owner}.{$repo}.{$sha}";
            $commit = Cache::get($cacheKey);

            if (is_array($commit)) {
                $cached[$sha] = $commit;
            } else {
                $toFetch[] = $sha;
            }
        }

        if ($toFetch !== []) {
            $fetched = $this->fetchCommitsFromApi($owner, $repo, $toFetch);
            $ttl = (int) config('intranet-app-base.github_release_cache_ttl', 3600);

            foreach ($fetched as $sha => $commit) {
                $cached[$sha] = $commit;
                Cache::put(
                    "intranet-app-base.github-commit.v1.{$owner}.{$repo}.{$sha}",
                    $commit,
                    $ttl,
                );
            }
        }

        return $cached;
    }

    /**
     * @param  list<string>  $shas
     * @return array<string, array<string, mixed>>
     */
    private function fetchCommitsFromApi(string $owner, string $repo, array $shas): array
    {
        $responses = Http::pool(function (Pool $pool) use ($owner, $repo, $shas): void {
            foreach ($shas as $sha) {
                $this->applyGithubDefaults($pool->as($sha))
                    ->get("https://api.github.com/repos/{$owner}/{$repo}/commits/{$sha}");
            }
        });

        $commits = [];

        foreach ($responses as $sha => $response) {
            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $payload = $response->json();

            if (is_array($payload)) {
                $commits[$sha] = $payload;
            }
        }

        return $commits;
    }

    private function httpClient(): PendingRequest
    {
        return $this->applyGithubDefaults(Http::accept('application/vnd.github+json'));
    }

    private function applyGithubDefaults(PendingRequest $request): PendingRequest
    {
        $request = $request->withHeaders([
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'hwkdo-intranet-app-base',
        ]);

        $token = config('intranet-app-base.github_token');

        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }

    private function normalizeTag(string $tag): string
    {
        $tag = trim($tag);

        if ($tag === '') {
            return '';
        }

        return 'v'.ltrim($tag, 'v');
    }
}
