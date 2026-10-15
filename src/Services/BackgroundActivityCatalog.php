<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityKind;
use Hwkdo\IntranetAppBase\Interfaces\BackgroundActivityProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesBackgroundActivitiesInterface;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackgroundActivityCatalog
{
    /** @var Collection<string, BackgroundActivityDefinition>|null */
    private ?Collection $cached = null;

    /**
     * @param  (\Closure(): array<string, mixed>)|null  $packagesResolver
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
    ) {}

    /**
     * @return Collection<string, BackgroundActivityDefinition>
     */
    public function all(): Collection
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $items = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesBackgroundActivitiesInterface::class, true)) {
                continue;
            }

            foreach ($appClass::backgroundActivities() as $definition) {
                $items->put($definition->key, $definition);
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                Log::warning('BackgroundActivityProvider class not found', ['class' => $providerClass]);

                continue;
            }

            try {
                /** @var BackgroundActivityProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->backgroundActivities() as $definition) {
                    $items->put($definition->key, $definition);
                }
            } catch (Throwable $e) {
                Log::error('BackgroundActivityProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->notificationDefinitions() as $definition) {
            if (! $items->has($definition->key)) {
                $items->put($definition->key, $definition);
            }
        }

        return $this->cached = $items->sortBy([
            ['appName', 'asc'],
            ['sort', 'asc'],
            ['label', 'asc'],
        ]);
    }

    public function find(string $key): ?BackgroundActivityDefinition
    {
        return $this->all()->get($key);
    }

    public function findByCommandName(string $commandName): ?BackgroundActivityDefinition
    {
        return $this->all()->first(
            fn (BackgroundActivityDefinition $definition): bool => $definition->commandName() === $commandName,
        );
    }

    public function findByJob(string $jobClass): ?BackgroundActivityDefinition
    {
        return $this->all()->first(
            fn (BackgroundActivityDefinition $definition): bool => $definition->job === $jobClass,
        );
    }

    public function findByMailable(string $mailableClass): ?BackgroundActivityDefinition
    {
        return $this->all()->first(
            fn (BackgroundActivityDefinition $definition): bool => $definition->mailable === $mailableClass,
        );
    }

    public function findByNotificationTypeKey(string $typeKey): ?BackgroundActivityDefinition
    {
        $definition = $this->find($typeKey);

        if ($definition?->kind !== BackgroundActivityKind::Notification) {
            return null;
        }

        return $definition;
    }

    /**
     * @return Collection<string, Collection<int, BackgroundActivityDefinition>>
     */
    public function groupedByApp(?string $appIdentifier = null): Collection
    {
        return $this->all()
            ->when(
                $appIdentifier !== null && $appIdentifier !== '',
                fn (Collection $items): Collection => $items->filter(
                    fn (BackgroundActivityDefinition $definition): bool => $definition->appIdentifier === $appIdentifier,
                ),
            )
            ->groupBy(fn (BackgroundActivityDefinition $definition): string => $definition->appIdentifier);
    }

    /**
     * @return list<class-string<BackgroundActivityProviderInterface>>
     */
    private function hostProviders(): array
    {
        /** @var list<class-string<BackgroundActivityProviderInterface>> $providers */
        $providers = config('intranet-app-base.background_activity_providers', []);

        return $providers;
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
     * @return list<BackgroundActivityDefinition>
     */
    private function notificationDefinitions(): array
    {
        try {
            $types = app(NotificationTypeCatalog::class)->all();
        } catch (Throwable $e) {
            Log::warning('Background activity notification import failed', ['error' => $e->getMessage()]);

            return [];
        }

        $definitions = [];

        foreach ($types as $type) {
            $definitions[] = new BackgroundActivityDefinition(
                key: $type->key,
                label: $type->label,
                appIdentifier: $type->appIdentifier,
                appName: $type->appName,
                kind: BackgroundActivityKind::Notification,
                description: $type->description,
                availableChannels: $type->resolvedAvailableChannels(),
                sort: 500,
            );
        }

        return $definitions;
    }
}
