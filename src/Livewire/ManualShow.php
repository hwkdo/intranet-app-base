<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\Services\ManualCatalog;
use Hwkdo\IntranetAppBase\Services\TourCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ManualShow extends Component
{
    public ?string $manualKey = null;

    public ?string $appIdentifier = null;

    public function mount(ManualCatalog $catalog, ?string $manualKey = null, ?string $appIdentifier = null): void
    {
        $this->manualKey = $manualKey;
        $this->appIdentifier = $appIdentifier;

        if ($this->manualKey === null && $this->appIdentifier !== null) {
            $primary = $catalog->primaryForApp(Auth::user(), $this->appIdentifier);
            $this->manualKey = $primary?->key;
        }

        abort_if($this->manualKey === null, 404);

        abort_if($catalog->forUser(Auth::user())->get($this->manualKey) === null, 404);
    }

    public function startRelatedTour(): mixed
    {
        $definition = $this->definition();

        if ($definition?->relatedTourKey === null) {
            return null;
        }

        $tour = app(TourCatalog::class)->forUser(Auth::user())->get($definition->relatedTourKey);

        if ($tour === null) {
            return null;
        }

        session()->put('intranet_start_tour', $tour->key);

        return $this->redirect(route($tour->routeName), navigate: true);
    }

    public function definition(): ?ManualDefinition
    {
        if ($this->manualKey === null) {
            return null;
        }

        return app(ManualCatalog::class)->forUser(Auth::user())->get($this->manualKey);
    }

    public function render(): View
    {
        $definition = $this->definition();

        abort_if($definition === null, 404);

        return view('intranet-app-base::livewire.manual-show', [
            'definition' => $definition,
            'relatedTour' => $definition->relatedTourKey !== null
                ? app(TourCatalog::class)->forUser(Auth::user())->get($definition->relatedTourKey)
                : null,
        ]);
    }
}
