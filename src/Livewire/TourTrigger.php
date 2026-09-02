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

    public bool $mandatory = false;

    public bool $showButton = false;

    public bool $showNudge = false;

    public function mount(TourCatalog $catalog, TourProgressStore $store): void
    {
        $this->resolveForPage(
            $catalog,
            $store,
            request()->route()?->getName(),
            request()->path(),
        );
    }

    public function refreshForRoute(?string $path = null): void
    {
        $resolvedPath = $path ?? request()->path();

        if (str_starts_with($resolvedPath, '/')) {
            $resolvedPath = TourDefinition::normalizePath($resolvedPath);
        }

        $this->resolveForPage(
            app(TourCatalog::class),
            app(TourProgressStore::class),
            $this->resolveRouteName($resolvedPath !== '' ? '/'.$resolvedPath : null),
            $resolvedPath,
        );
    }

    public function startTour(): void
    {
        if ($this->tourKey === null || $this->stepsModule === null) {
            return;
        }

        $this->prepareTourStart();

        $this->js($this->startTourJavaScript($this->tourKey, $this->stepsModule, $this->mandatory));
    }

    public function prepareTourStart(): void
    {
        if ($this->tourKey === null) {
            return;
        }

        $this->showNudge = false;
        session()->put('tour_nudge:'.$this->tourKey, true);
    }

    private function startTourJavaScript(string $tourKey, string $stepsModule, bool $mandatory = false): string
    {
        return sprintf(
            <<<'JS'
            (() => {
                if (! window.IntranetTours?.start) {
                    console.error('[tours] IntranetTours.start is not available');
                    window.Flux?.toast?.({
                        heading: 'Tour',
                        text: 'Tour-Modul nicht geladen. Bitte Seite neu laden (Strg+Shift+R).',
                        variant: 'danger',
                    });
                    return;
                }

                window.IntranetTours.start({
                    tourKey: %s,
                    stepsModule: %s,
                    mandatory: %s,
                }).catch((error) => {
                    console.error('[tours] start failed', error);
                    window.Flux?.toast?.({
                        heading: 'Tour',
                        text: 'Die Tour konnte nicht gestartet werden.',
                        variant: 'danger',
                    });
                });
            })();
            JS,
            json_encode($tourKey, JSON_THROW_ON_ERROR),
            json_encode($stepsModule, JSON_THROW_ON_ERROR),
            $mandatory ? 'true' : 'false',
        );
    }

    public function remindLater(): void
    {
        $definition = $this->currentDefinition();

        if ($definition === null) {
            return;
        }

        if ($definition->mandatory) {
            Flux::toast(
                text: 'Diese Tour ist verpflichtend und kann nicht verschoben werden.',
                variant: 'warning',
            );

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

        if ($definition->mandatory) {
            Flux::toast(
                text: 'Diese Tour ist verpflichtend und kann nicht übersprungen werden.',
                variant: 'warning',
            );

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
        if ($this->tourKey === null || $this->mandatory) {
            return;
        }

        $this->showNudge = false;
        session()->put('tour_nudge:'.$this->tourKey, true);
    }

    private function resolveForPage(
        TourCatalog $catalog,
        TourProgressStore $store,
        ?string $routeName,
        ?string $path,
    ): void {
        $user = Auth::user();

        if ($user === null) {
            $this->resetTourState();

            return;
        }

        $definition = $catalog->forPage($user, $routeName, $path);

        if ($definition === null) {
            $this->resetTourState();

            return;
        }

        $this->tourKey = $definition->key;
        $this->tourTitle = $definition->title;
        $this->stepsModule = $definition->stepsModule;
        $this->mandatory = $definition->mandatory;
        $this->showButton = true;
        $this->showNudge = false;

        $pendingStart = session()->pull('intranet_start_tour');

        if ($pendingStart === $definition->key) {
            $this->js($this->startTourJavaScript($definition->key, $definition->stepsModule, $definition->mandatory));

            return;
        }

        if ($store->requiresMandatoryCompletion($user, $definition)) {
            $this->js($this->startTourJavaScript($definition->key, $definition->stepsModule, true));

            return;
        }

        if ($definition->mandatory) {
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
        $this->mandatory = false;
        $this->showButton = false;
        $this->showNudge = false;
    }

    public function render(): View
    {
        return view('intranet-app-base::livewire.tour-trigger');
    }
}
