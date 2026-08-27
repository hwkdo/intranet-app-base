<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\SetupDefinition;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSetupInterface;
use Hwkdo\IntranetAppBase\Interfaces\SetupProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SetupCatalog
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
    ) {}

    /**
     * @return Collection<string, SetupDefinition>
     */
    public function all(): Collection
    {
        $setups = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesSetupInterface::class, true)) {
                continue;
            }

            foreach ($appClass::setups() as $definition) {
                $setups->put($definition->key, $definition);
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                Log::warning('SetupProvider class not found', ['class' => $providerClass]);

                continue;
            }

            try {
                /** @var SetupProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->setups() as $definition) {
                    $setups->put($definition->key, $definition);
                }
            } catch (\Throwable $e) {
                Log::error('SetupProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $setups->sortBy(fn (SetupDefinition $definition): int => $definition->sort)->values()
            ->mapWithKeys(fn (SetupDefinition $definition): array => [$definition->key => $definition]);
    }

    public function find(string $setupKey): ?SetupDefinition
    {
        return $this->all()->get($setupKey);
    }

    /**
     * @return Collection<string, SetupDefinition>
     */
    public function forUser(Authenticatable $user): Collection
    {
        $setups = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesSetupInterface::class, true)) {
                continue;
            }

            if (! $this->userHasAppAccess($user, $appClass)) {
                continue;
            }

            foreach ($appClass::setups() as $definition) {
                if ($definition->isEligible($user)) {
                    $setups->put($definition->key, $definition);
                }
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            try {
                /** @var SetupProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->setups() as $definition) {
                    if ($definition->isEligible($user)) {
                        $setups->put($definition->key, $definition);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('SetupProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $setups->sortBy(fn (SetupDefinition $definition): int => $definition->sort)->values()
            ->mapWithKeys(fn (SetupDefinition $definition): array => [$definition->key => $definition]);
    }

    /**
     * @return Collection<string, Collection<int, SetupDefinition>>
     */
    public function groupedForUser(Authenticatable $user): Collection
    {
        return $this->forUser($user)
            ->groupBy(fn (SetupDefinition $definition): string => $definition->group);
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
     * @return list<class-string<SetupProviderInterface>>
     */
    private function hostProviders(): array
    {
        /** @var list<class-string<SetupProviderInterface>> $providers */
        $providers = config('intranet-app-base.setup_providers', []);

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
