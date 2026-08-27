<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\TourDefinition;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesToursInterface;
use Hwkdo\IntranetAppBase\Interfaces\TourProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TourCatalog
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
    ) {}

    /**
     * @return Collection<string, TourDefinition>
     */
    public function all(): Collection
    {
        $tours = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesToursInterface::class, true)) {
                continue;
            }

            foreach ($appClass::tours() as $definition) {
                $tours->put($definition->key, $definition);
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                Log::warning('TourProvider class not found', ['class' => $providerClass]);

                continue;
            }

            try {
                /** @var TourProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->tours() as $definition) {
                    $tours->put($definition->key, $definition);
                }
            } catch (\Throwable $e) {
                Log::error('TourProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $tours->sortBy(fn (TourDefinition $definition): int => $definition->sort)->values()
            ->mapWithKeys(fn (TourDefinition $definition): array => [$definition->key => $definition]);
    }

    public function find(string $tourKey): ?TourDefinition
    {
        return $this->all()->get($tourKey);
    }

    /**
     * @return Collection<string, TourDefinition>
     */
    public function forUser(Authenticatable $user): Collection
    {
        $tours = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesToursInterface::class, true)) {
                continue;
            }

            if (! $this->userHasAppAccess($user, $appClass)) {
                continue;
            }

            foreach ($appClass::tours() as $definition) {
                if ($definition->isEligible($user)) {
                    $tours->put($definition->key, $definition);
                }
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            try {
                /** @var TourProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->tours() as $definition) {
                    if ($definition->isEligible($user)) {
                        $tours->put($definition->key, $definition);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('TourProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $tours->sortBy(fn (TourDefinition $definition): int => $definition->sort)->values()
            ->mapWithKeys(fn (TourDefinition $definition): array => [$definition->key => $definition]);
    }

    /**
     * @return Collection<string, Collection<int, TourDefinition>>
     */
    public function groupedForUser(Authenticatable $user): Collection
    {
        return $this->forUser($user)
            ->groupBy(fn (TourDefinition $definition): string => $definition->group);
    }

    public function forRoute(Authenticatable $user, ?string $routeName): ?TourDefinition
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        return $this->forUser($user)
            ->first(fn (TourDefinition $definition): bool => $definition->matchesRoute($routeName));
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
     * @return list<class-string<TourProviderInterface>>
     */
    private function hostProviders(): array
    {
        /** @var list<class-string<TourProviderInterface>> $providers */
        $providers = config('intranet-app-base.tour_providers', []);

        return $providers;
    }

    /**
     * @param  class-string<IntranetAppInterface>  $appClass
     */
    private function userHasAppAccess(Authenticatable $user, string $appClass): bool
    {
        if (! is_a($appClass, IntranetAppInterface::class, true)) {
            return true;
        }

        $permissions = collect($appClass::roles_user()->get('permissions', []))
            ->merge($appClass::roles_admin()->get('permissions', []))
            ->unique()
            ->filter()
            ->values();

        if ($permissions->isNotEmpty() && method_exists($user, 'can')) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        }

        if (! method_exists($user, 'hasRole')) {
            return true;
        }

        $allowedRoleNames = collect([
            $appClass::roles_user()->get('name'),
            $appClass::roles_admin()->get('name'),
        ])->filter()->values();

        if ($allowedRoleNames->isEmpty()) {
            return true;
        }

        return $user->hasRole($allowedRoleNames->toArray());
    }
}
