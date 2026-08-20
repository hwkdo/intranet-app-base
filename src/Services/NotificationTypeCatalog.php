<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Enums\NotificationChannelKey;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\NotificationTypeProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Hwkdo\IntranetAppBase\Models\IntranetNotificationPreference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationTypeCatalog
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
    ) {}

    /**
     * @return Collection<string, NotificationTypeDefinition>
     */
    public function all(): Collection
    {
        $types = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = $this->resolveAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesNotificationsInterface::class, true)) {
                continue;
            }

            foreach ($appClass::notificationTypes() as $definition) {
                $types->put($definition->key, $definition);
            }
        }

        foreach ($this->hostProviders() as $providerClass) {
            if (! class_exists($providerClass)) {
                Log::warning('NotificationTypeProvider class not found', ['class' => $providerClass]);

                continue;
            }

            try {
                /** @var NotificationTypeProviderInterface $provider */
                $provider = app($providerClass);

                foreach ($provider->notificationTypes() as $definition) {
                    $types->put($definition->key, $definition);
                }
            } catch (\Throwable $e) {
                Log::error('NotificationTypeProvider failed', [
                    'provider' => $providerClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $types;
    }

    public function find(string $typeKey): ?NotificationTypeDefinition
    {
        return $this->all()->get($typeKey);
    }

    /**
     * @return Collection<string, Collection<int, NotificationTypeDefinition>>
     */
    public function groupedByApp(): Collection
    {
        return $this->all()
            ->groupBy(fn (NotificationTypeDefinition $definition): string => $definition->appIdentifier);
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

    private function resolveAppClass(string $packageName, array $packageData): ?string
    {
        return IntranetAppBase::getAppClass($packageName, $packageData);
    }

    /**
     * @return list<class-string<NotificationTypeProviderInterface>>
     */
    private function hostProviders(): array
    {
        /** @var list<class-string<NotificationTypeProviderInterface>> $providers */
        $providers = config('intranet-app-base.notification_type_providers', []);

        return $providers;
    }
}
