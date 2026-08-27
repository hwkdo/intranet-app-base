<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppBase\Data\TourDefinition;
use Hwkdo\IntranetAppBase\Services\TourCatalog;
use Hwkdo\IntranetAppBase\Services\TourProgressStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TourTrigger extends Component
{
    public ?string $tourKey = null;

    public ?string $tourTitle = null;

    public ?string $stepsModule = null;

    public bool $showButton = false;

    public bool $showNudge = false;

    public function mount(TourCatalog $catalog, TourProgressStore $store): void
    {
        $this->resolveForRoute(
            $catalog,
            $store,
            request()->route()?->getName(),
        );
    }

    public function refreshForRoute(?string $path = null): void
    {
        $this->resolveForRoute(
            app(TourCatalog::class),
            app(TourProgressStore::class),
            $this->resolveRouteName($path),
        );
    }

    public function startTour(): void
    {
        if ($this->tourKey === null || $this->stepsModule === null) {
            return;
        }

        $this->showNudge = false;
        session()->put('tour_nudge:'.$this->tourKey, true);

        $this->dispatch(
            'intranet-tour-start',
            tourKey: $this->tourKey,
            stepsModule: $this->stepsModule,
        );
    }

    public function remindLater(): void
    {
        $definition = $this->currentDefinition();

        if ($definition === null) {
            return;
        }

        app(TourProgressStore::class)->markRemindLater(Auth::user(), $definition);
        $this->showNudge = false;
        session()->put('tour_nudge:'.$definition->key, true);

        Flux::toast(
            text: 'Wir erinnern Sie später an diese Tour.',
            variant: 'success',
        );
    }

    public function dismissNudge(): void
    {
        $definition = $this->currentDefinition();

        if ($definition === null) {
            return;
        }

        app(TourProgressStore::class)->markDismissed(Auth::user(), $definition);
        $this->showNudge = false;
        session()->put('tour_nudge:'.$definition->key, true);

        Flux::toast(
            text: 'Tour übersprungen.',
            variant: 'success',
        );
    }

    public function dismissNudgeOnly(): void
    {
        if ($this->tourKey === null) {
            return;
        }

        $this->showNudge = false;
        session()->put('tour_nudge:'.$this->tourKey, true);
    }

    private function resolveForRoute(
        TourCatalog $catalog,
        TourProgressStore $store,
        ?string $routeName,
    ): void {
        $user = Auth::user();

        if ($user === null) {
            $this->resetTourState();

            return;
        }

        $definition = $catalog->forRoute($user, $routeName);

        if ($definition === null) {
            $this->resetTourState();

            return;
        }

        $this->tourKey = $definition->key;
        $this->tourTitle = $definition->title;
        $this->stepsModule = $definition->stepsModule;
        $this->showButton = true;

        $pendingStart = session()->pull('intranet_start_tour');

        if ($pendingStart === $definition->key) {
            $this->showNudge = false;
            $this->dispatch(
                'intranet-tour-start',
                tourKey: $definition->key,
                stepsModule: $definition->stepsModule,
            );

            return;
        }

        $nudgeable = $store->nudgeableFor($user, collect([$definition->key => $definition]));
        $sessionKey = 'tour_nudge:'.$definition->key;

        $this->showNudge = $nudgeable->isNotEmpty() && ! session()->get($sessionKey);

        if ($this->showNudge) {
            session()->put($sessionKey, true);
        }
    }

    private function resolveRouteName(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return request()->route()?->getName();
        }

        try {
            $request = Request::create($path, 'GET');

            return app('router')->getRoutes()->match($request)->getName();
        } catch (\Throwable) {
            return null;
        }
    }

    private function currentDefinition(): ?TourDefinition
    {
        if ($this->tourKey === null) {
            return null;
        }

        return app(TourCatalog::class)->forUser(Auth::user())->get($this->tourKey);
    }

    private function resetTourState(): void
    {
        $this->tourKey = null;
        $this->tourTitle = null;
        $this->stepsModule = null;
        $this->showButton = false;
        $this->showNudge = false;
    }

    public function render(): View
    {
        return view('intranet-app-base::livewire.tour-trigger');
    }
}
