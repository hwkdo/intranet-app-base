<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ManualProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesManualsInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ManualCatalog
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
    ) {}

    /**
     * @return Collection<string, ManualDefinition>
     */
    public function all(): Collection
    {
        $manuals = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesManualsInterface::class, true)) {
                continue;
            }

            foreach ($appClass::manuals() as $definition) {
                $manuals->put($definition->key, $definition);
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                Log::warning('ManualProvider class not found', ['class' => $providerClass]);

                continue;
            }

            try {
                /** @var ManualProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->manuals() as $definition) {
                    $manuals->put($definition->key, $definition);
                }
            } catch (\Throwable $e) {
                Log::error('ManualProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $manuals->sortBy(fn (ManualDefinition $definition): int => $definition->sort)->values()
            ->mapWithKeys(fn (ManualDefinition $definition): array => [$definition->key => $definition]);
    }

    public function find(string $manualKey): ?ManualDefinition
    {
        return $this->all()->get($manualKey);
    }

    /**
     * @return Collection<string, ManualDefinition>
     */
    public function forUser(Authenticatable $user): Collection
    {
        $manuals = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesManualsInterface::class, true)) {
                continue;
            }

            if (! $this->userHasAppAccess($user, $appClass)) {
                continue;
            }

            foreach ($appClass::manuals() as $definition) {
                if ($definition->isEligible($user)) {
                    $manuals->put($definition->key, $definition);
                }
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            try {
                /** @var ManualProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->manuals() as $definition) {
                    if ($definition->isEligible($user)) {
                        $manuals->put($definition->key, $definition);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('ManualProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $manuals->sortBy(fn (ManualDefinition $definition): int => $definition->sort)->values()
            ->mapWithKeys(fn (ManualDefinition $definition): array => [$definition->key => $definition]);
    }

    /**
     * @return Collection<string, Collection<int, ManualDefinition>>
     */
    public function groupedForUser(Authenticatable $user): Collection
    {
        return $this->forUser($user)
            ->groupBy(fn (ManualDefinition $definition): string => $definition->group);
    }

    public function primaryForApp(Authenticatable $user, string $appIdentifier): ?ManualDefinition
    {
        return $this->forUser($user)
            ->filter(fn (ManualDefinition $definition): bool => $definition->appIdentifier === $appIdentifier)
            ->sortBy(fn (ManualDefinition $definition): int => $definition->isPrimary ? 0 : 1)
            ->first();
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
     * @return list<class-string<ManualProviderInterface>>
     */
    private function hostProviders(): array
    {
        /** @var list<class-string<ManualProviderInterface>> $providers */
        $providers = config('intranet-app-base.manual_providers', []);

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
