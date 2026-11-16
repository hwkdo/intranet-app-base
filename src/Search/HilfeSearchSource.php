<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Search;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Data\SetupDefinition;
use Hwkdo\IntranetAppBase\Data\TourDefinition;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Hwkdo\IntranetAppBase\Services\ManualCatalog;
use Hwkdo\IntranetAppBase\Services\SetupCatalog;
use Hwkdo\IntranetAppBase\Services\TourCatalog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

class HilfeSearchSource implements SearchSourceInterface
{
    public function __construct(
        private readonly SetupCatalog $setups,
        private readonly TourCatalog $tours,
        private readonly ManualCatalog $manuals,
    ) {}

    public function key(): string
    {
        return 'intranet.hilfe';
    }

    public function label(): string
    {
        return 'Hilfe';
    }

    public function appIdentifier(): string
    {
        return 'hilfe';
    }

    public function appName(): string
    {
        return 'Hilfe';
    }

    public function icon(): string
    {
        return 'question-mark-circle';
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        return true;
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return $this->candidates($user)
            ->map(function (array $candidate) use ($query): ?SearchResult {
                $score = $this->matchScore($query, $candidate['haystack']);

                if ($score <= 0.0) {
                    return null;
                }

                return $this->toResult($candidate, $score, $query);
            })
            ->filter()
            ->sortByDesc(fn (SearchResult $result): float => $result->score ?? 0.0)
            ->take($limit)
            ->values();
    }

    public function resolveFavorite(string $entityId, Authenticatable $user): ?SearchResult
    {
        $candidate = $this->candidates($user)
            ->first(fn (array $item): bool => $item['entityId'] === $entityId);

        if ($candidate === null) {
            return null;
        }

        return $this->toResult($candidate);
    }

    /**
     * @param  array{title: string, url: string, subtitle: string, icon: string, entityId: string, haystack: list<string>, body?: string}  $candidate
     */
    private function toResult(array $candidate, ?float $score = null, ?string $query = null): SearchResult
    {
        $snippet = null;

        if ($query !== null && ($candidate['body'] ?? '') !== '') {
            $snippet = $this->excerpt($candidate['body'], $query, $candidate['title']);
        }

        return new SearchResult(
            title: $candidate['title'],
            url: $candidate['url'],
            appIdentifier: $this->appIdentifier(),
            appName: $this->appName(),
            icon: $candidate['icon'],
            favoriteKey: $this->key().':'.$candidate['entityId'],
            subtitle: $candidate['subtitle'],
            sourceKey: $this->key(),
            score: $score,
            snippet: $snippet,
        );
    }

    /**
     * @return Collection<int, array{title: string, url: string, subtitle: string, icon: string, entityId: string, haystack: list<string>, body?: string}>
     */
    private function candidates(Authenticatable $user): Collection
    {
        $items = collect($this->hubShortcuts());

        foreach ($this->setups->forUser($user) as $definition) {
            /** @var SetupDefinition $definition */
            $items->push([
                'title' => $definition->title,
                'url' => route('hilfe.setup.show', $definition->key),
                'subtitle' => 'Einrichtung · '.$definition->appName,
                'icon' => 'wrench-screwdriver',
                'entityId' => 'setup:'.$definition->key,
                'haystack' => [
                    $definition->title,
                    $definition->description,
                    $definition->appName,
                    $definition->key,
                ],
            ]);
        }

        foreach ($this->tours->forUser($user) as $definition) {
            /** @var TourDefinition $definition */
            $items->push([
                'title' => $definition->title,
                'url' => $this->tourUrl($definition),
                'subtitle' => 'Tour · '.$definition->appName,
                'icon' => 'map',
                'entityId' => 'tour:'.$definition->key,
                'haystack' => [
                    $definition->title,
                    $definition->description,
                    $definition->appName,
                    $definition->key,
                ],
            ]);
        }

        foreach ($this->manuals->forUser($user) as $definition) {
            /** @var ManualDefinition $definition */
            $body = $this->manualPlainText($definition);
            $items->push([
                'title' => $definition->title,
                'url' => $this->manualUrl($definition),
                'subtitle' => 'Anleitung · '.$definition->appName,
                'icon' => 'book-open',
                'entityId' => 'manual:'.$definition->key,
                'body' => $body,
                'haystack' => [
                    $definition->title,
                    $definition->description,
                    $definition->appName,
                    $definition->key,
                    $body,
                ],
            ]);
        }

        return $items;
    }

    /**
     * @return list<array{title: string, url: string, subtitle: string, icon: string, entityId: string, haystack: list<string>}>
     */
    private function hubShortcuts(): array
    {
        return [
            [
                'title' => 'Hilfe-Übersicht',
                'url' => route('hilfe.index'),
                'subtitle' => 'Hilfe',
                'icon' => 'question-mark-circle',
                'entityId' => 'hub:index',
                'haystack' => ['Hilfe', 'Hilfe-Übersicht', 'Help', 'Übersicht'],
            ],
            [
                'title' => 'Einrichtung',
                'url' => route('hilfe.setup'),
                'subtitle' => 'Hilfe',
                'icon' => 'wrench-screwdriver',
                'entityId' => 'hub:setup',
                'haystack' => ['Einrichtung', 'Setup', 'Ersteinrichtung', 'Wizard'],
            ],
            [
                'title' => 'Touren',
                'url' => route('hilfe.tours'),
                'subtitle' => 'Hilfe',
                'icon' => 'map',
                'entityId' => 'hub:tours',
                'haystack' => ['Touren', 'Tour', 'Product Tours', 'Geführte Tour'],
            ],
            [
                'title' => 'Anleitungen',
                'url' => route('hilfe.manuals'),
                'subtitle' => 'Hilfe',
                'icon' => 'book-open',
                'entityId' => 'hub:manuals',
                'haystack' => ['Anleitungen', 'Anleitung', 'Bedienungsanleitung', 'Manual', 'Handbuch'],
            ],
        ];
    }

    private function tourUrl(TourDefinition $definition): string
    {
        if (Route::has('hilfe.tours.start')) {
            return route('hilfe.tours.start', $definition->key);
        }

        return $definition->startUrl();
    }

    private function manualUrl(ManualDefinition $definition): string
    {
        if ($definition->group === 'base') {
            return route('hilfe.manuals.show', $definition->key);
        }

        $routeName = 'apps.'.$definition->appIdentifier.'.manual';

        if (Route::has($routeName)) {
            return route($routeName);
        }

        return route('hilfe.manuals.show', $definition->key);
    }

    private function manualPlainText(ManualDefinition $definition): string
    {
        $cacheKey = 'hilfe.manual-text.'.$definition->key.'.'.$definition->version.'.'.$this->manualViewRevision($definition);

        try {
            return Cache::remember($cacheKey, now()->addDay(), function () use ($definition): string {
                $html = view($definition->contentView, [
                    'manualKey' => $definition->key,
                ])->render();

                $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = str_replace("\u{00A0}", ' ', $text);
                $text = (string) preg_replace('/\s+/u', ' ', $text);

                return trim($text);
            });
        } catch (Throwable $e) {
            Log::warning('Hilfe manual text could not be rendered', [
                'manual' => $definition->key,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function manualViewRevision(ManualDefinition $definition): string
    {
        try {
            $path = app('view')->getFinder()->find($definition->contentView);
        } catch (Throwable) {
            return '0';
        }

        if (! is_file($path)) {
            return '0';
        }

        return (string) filemtime($path);
    }

    /**
     * @param  list<string>  $haystack
     */
    private function matchScore(string $query, array $haystack): float
    {
        $tokens = HilfeSearchText::tokens($query);

        if ($tokens === []) {
            return 0.0;
        }

        $fields = [];

        foreach ($haystack as $index => $field) {
            $words = HilfeSearchText::tokens($field);

            if ($words === []) {
                continue;
            }

            $fields[] = [
                'index' => $index,
                'words' => $words,
            ];
        }

        $titleWords = [];

        foreach ($fields as $field) {
            if ($field['index'] === 0) {
                $titleWords = $field['words'];

                break;
            }
        }

        $matchedInTitle = 0;

        foreach ($tokens as $token) {
            $inTitle = HilfeSearchText::matchesAny($token, $titleWords);

            if ($inTitle) {
                $matchedInTitle++;

                continue;
            }

            $matched = false;

            foreach ($fields as $field) {
                if ($field['index'] === 0) {
                    continue;
                }

                if (HilfeSearchText::matchesAny($token, $field['words'])) {
                    $matched = true;

                    break;
                }
            }

            if (! $matched) {
                return 0.0;
            }
        }

        return 0.45 + (0.55 * ($matchedInTitle / count($tokens)));
    }

    private function excerpt(string $body, string $query, string $title): ?string
    {
        $titleWords = HilfeSearchText::tokens($title);
        $bodyWords = HilfeSearchText::tokens($body);
        $needles = [];

        foreach (HilfeSearchText::tokens($query) as $token) {
            if (HilfeSearchText::matchesAny($token, $titleWords)) {
                continue;
            }

            if (HilfeSearchText::matchesAny($token, $bodyWords)) {
                $needles[] = $token;
            }
        }

        if ($needles === []) {
            return null;
        }

        usort($needles, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        $pattern = '/(?<![\p{L}\p{N}])(?:'.implode('|', array_map(
            fn (string $token): string => preg_quote($token, '/'),
            $needles,
        )).')/iu';

        if (! preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $charOffset = mb_strlen(substr($body, 0, $matches[0][1]));
        $start = max(0, $charOffset - 50);
        $excerpt = trim(mb_substr($body, $start, 160));

        if ($start > 0) {
            $excerpt = '…'.$excerpt;
        }

        if ($start + 160 < mb_strlen($body)) {
            $excerpt .= '…';
        }

        return $excerpt;
    }
}
