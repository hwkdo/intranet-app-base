<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppBase\Contracts\UserSearchPreferencesSourceInterface;
use Hwkdo\IntranetAppBase\Data\SearchResponse;
use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Services\SearchFavoriteStore;
use Hwkdo\IntranetAppBase\Services\SearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

class GlobalSearch extends Component
{
    public string $searchQuery = '';

    public bool $showModal = false;

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    #[Computed]
    public function minChars(): int
    {
        return app(SearchService::class)->minChars();
    }

    #[Computed]
    public function previewLimit(): int
    {
        return app(SearchService::class)->previewLimit();
    }

    #[Computed]
    public function showFavoritesInEmptySearch(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return true;
        }

        return app(UserSearchPreferencesSourceInterface::class)->showFavoritesInEmptySearch($user);
    }

    #[Computed]
    public function previewResponse(): SearchResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return new SearchResponse(collect(), collect(), 0);
        }

        return app(SearchService::class)->searchPreview($user, $this->searchQuery);
    }

    #[Computed]
    public function modalResponse(): SearchResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return new SearchResponse(collect(), collect(), 0);
        }

        return app(SearchService::class)->searchModal($user, $this->searchQuery);
    }

    /**
     * @return Collection<int, SearchResult>
     */
    #[Computed]
    public function favorites(): Collection
    {
        $user = Auth::user();

        if ($user === null || ! $this->showFavoritesInEmptySearch) {
            return collect();
        }

        return app(SearchFavoriteStore::class)->list($user);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function favoritedKeys(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        return app(SearchFavoriteStore::class)->favoritedKeys($user);
    }

    public function toggleFavorite(
        string $favoriteKey,
        string $title,
        string $url,
        string $icon,
        string $appIdentifier,
        string $appName,
        ?string $subtitle = null,
        ?string $sourceKey = null,
        bool $download = false,
    ): void {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $result = new SearchResult(
            title: $title,
            url: $url,
            appIdentifier: $appIdentifier,
            appName: $appName,
            icon: $icon,
            favoriteKey: $favoriteKey,
            subtitle: $subtitle,
            sourceKey: $sourceKey,
            download: $download,
        );

        try {
            $favorited = app(SearchFavoriteStore::class)->toggle($user, $result);
        } catch (RuntimeException $exception) {
            Flux::toast(
                heading: 'Favoriten',
                text: $exception->getMessage(),
                variant: 'warning',
            );

            return;
        }

        unset($this->favorites, $this->favoritedKeys);

        $this->dispatch('search-favorites-updated');

        Flux::toast(
            heading: 'Favoriten',
            text: $favorited ? 'Als Favorit gespeichert.' : 'Favorit entfernt.',
            variant: 'success',
        );
    }

    #[On('search-favorites-updated')]
    #[On('search-preferences-updated')]
    public function refreshFavorites(): void
    {
        unset($this->favorites, $this->favoritedKeys, $this->showFavoritesInEmptySearch);
    }

    public function render()
    {
        return view('intranet-app-base::livewire.global-search');
    }
}
