<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Hwkdo\IntranetAppBase\Data\SearchResponse;
use Hwkdo\IntranetAppBase\Services\SearchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

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

    public function render()
    {
        return view('intranet-app-base::livewire.global-search');
    }
}
