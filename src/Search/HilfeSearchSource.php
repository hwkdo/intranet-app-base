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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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

                return new SearchResult(
                    title: $candidate['title'],
                    url: $candidate['url'],
                    appIdentifier: $this->appIdentifier(),
                    appName: $this->appName(),
                    icon: $candidate['icon'],
                    subtitle: $candidate['subtitle'],
                    sourceKey: $this->key(),
                    score: $score,
                );
            })
            ->filter()
            ->sortByDesc(fn (SearchResult $result): float => $result->score ?? 0.0)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{title: string, url: string, subtitle: string, icon: string, haystack: list<string>}>
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
            $items->push([
                'title' => $definition->title,
                'url' => $this->manualUrl($definition),
                'subtitle' => 'Anleitung · '.$definition->appName,
                'icon' => 'book-open',
                'haystack' => [
                    $definition->title,
                    $definition->description,
                    $definition->appName,
                    $definition->key,
                ],
            ]);
        }

        return $items;
    }

    /**
     * @return list<array{title: string, url: string, subtitle: string, icon: string, haystack: list<string>}>
     */
    private function hubShortcuts(): array
    {
        return [
            [
                'title' => 'Hilfe-Übersicht',
                'url' => route('hilfe.index'),
                'subtitle' => 'Hilfe',
                'icon' => 'question-mark-circle',
                'haystack' => ['Hilfe', 'Hilfe-Übersicht', 'Help', 'Übersicht'],
            ],
            [
                'title' => 'Einrichtung',
                'url' => route('hilfe.setup'),
                'subtitle' => 'Hilfe',
                'icon' => 'wrench-screwdriver',
                'haystack' => ['Einrichtung', 'Setup', 'Ersteinrichtung', 'Wizard'],
            ],
            [
                'title' => 'Touren',
                'url' => route('hilfe.tours'),
                'subtitle' => 'Hilfe',
                'icon' => 'map',
                'haystack' => ['Touren', 'Tour', 'Product Tours', 'Geführte Tour'],
            ],
            [
                'title' => 'Anleitungen',
                'url' => route('hilfe.manuals'),
                'subtitle' => 'Hilfe',
                'icon' => 'book-open',
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

    /**
     * @param  list<string>  $haystack
     */
    private function matchScore(string $query, array $haystack): float
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return 0.0;
        }

        $best = 0.0;

        foreach ($haystack as $index => $field) {
            $normalizedField = $this->normalize($field);

            if ($normalizedField === '') {
                continue;
            }

            $exact = $index === 0 ? 1.0 : 0.85;
            $prefix = $index === 0 ? 0.8 : 0.65;
            $contains = $index === 0 ? 0.5 : 0.4;

            if ($normalizedField === $normalizedQuery) {
                $best = max($best, $exact);

                continue;
            }

            if (Str::startsWith($normalizedField, $normalizedQuery)) {
                $best = max($best, $prefix);

                continue;
            }

            if (Str::contains($normalizedField, $normalizedQuery)) {
                $best = max($best, $contains);
            }
        }

        return $best;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return $value;
    }
}
