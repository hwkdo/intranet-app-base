<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\SearchActionDefinition;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSearchActionsInterface;
use Hwkdo\IntranetAppBase\Interfaces\SearchActionProviderInterface;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SearchActionCatalog
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     * @param  \Closure(): list<class-string<SearchActionProviderInterface>>|null  $hostProvidersResolver
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
        private readonly ?\Closure $hostProvidersResolver = null,
    ) {}

    /**
     * @return Collection<string, SearchActionDefinition>
     */
    public function all(): Collection
    {
        $actions = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if ($appClass === null || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesSearchActionsInterface::class, true)) {
                continue;
            }

            foreach ($appClass::searchActions() as $definition) {
                $actions->put($definition->key, $definition);
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                Log::warning('SearchActionProvider class not found', ['class' => $providerClass]);

                continue;
            }

            try {
                /** @var SearchActionProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->searchActions() as $definition) {
                    $actions->put($definition->key, $definition);
                }
            } catch (\Throwable $exception) {
                Log::error('SearchActionProvider failed', [
                    'provider' => $providerClass,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $actions
            ->sortBy(fn (SearchActionDefinition $definition): int => $definition->sort)
            ->values()
            ->mapWithKeys(fn (SearchActionDefinition $definition): array => [$definition->key => $definition]);
    }

    /**
     * @return Collection<string, SearchActionDefinition>
     */
    public function forUser(Authenticatable $user): Collection
    {
        $actions = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if ($appClass === null || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesSearchActionsInterface::class, true)) {
                continue;
            }

            if (! is_a($appClass, IntranetAppInterface::class, true)) {
                continue;
            }

            if (! $this->userCanSeeApp($user, $appClass::identifier())) {
                continue;
            }

            foreach ($appClass::searchActions() as $definition) {
                if ($definition->isEligible($user)) {
                    $actions->put($definition->key, $definition);
                }
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            try {
                /** @var SearchActionProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->searchActions() as $definition) {
                    if ($definition->isEligible($user)) {
                        $actions->put($definition->key, $definition);
                    }
                }
            } catch (\Throwable $exception) {
                Log::error('SearchActionProvider failed', [
                    'provider' => $providerClass,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $actions
            ->sortBy(fn (SearchActionDefinition $definition): int => $definition->sort)
            ->values()
            ->mapWithKeys(fn (SearchActionDefinition $definition): array => [$definition->key => $definition]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePackages(): array
    {
        if ($this->packagesResolver !== null) {
            return ($this->packagesResolver)();
        }

        return IntranetAppBase::getIntranetAppPackages();
    }

    /**
     * @return list<class-string<SearchActionProviderInterface>>
     */
    private function hostProviders(): array
    {
        if ($this->hostProvidersResolver !== null) {
            return ($this->hostProvidersResolver)();
        }

        /** @var list<class-string<SearchActionProviderInterface>> $providers */
        $providers = config('intranet-app-base.search_action_providers', []);

        return $providers;
    }

    private function userCanSeeApp(Authenticatable $user, string $identifier): bool
    {
        if (! method_exists($user, 'can')) {
            return true;
        }

        return $user->can('see-app-'.$identifier);
    }
}
