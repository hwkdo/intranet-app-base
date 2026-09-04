<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Hwkdo\IntranetAppBase\Contracts\UserSearchPreferencesSourceInterface;
use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Services\SearchFavoriteStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class SearchFavoritesDropdown extends Component
{
    #[Computed]
    public function isVisible(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return app(UserSearchPreferencesSourceInterface::class)->showFavoritesHeaderIcon($user);
    }

    /**
     * @return Collection<int, SearchResult>
     */
    #[Computed]
    public function favorites(): Collection
    {
        $user = Auth::user();

        if ($user === null || ! $this->isVisible) {
            return collect();
        }

        return app(SearchFavoriteStore::class)->list($user);
    }

    public function removeFavorite(string $favoriteKey): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        app(SearchFavoriteStore::class)->remove($user, $favoriteKey);

        unset($this->favorites);

        $this->dispatch('search-favorites-updated');
    }

    #[On('search-favorites-updated')]
    #[On('search-preferences-updated')]
    public function refreshFavorites(): void
    {
        unset($this->favorites, $this->isVisible);
    }

    public function render()
    {
        return view('intranet-app-base::livewire.search-favorites-dropdown');
    }
}
