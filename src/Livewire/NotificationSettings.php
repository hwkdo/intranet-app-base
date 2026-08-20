<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Enums\NotificationChannelKey;
use Hwkdo\IntranetAppBase\Notifications\TestNotification;
use Hwkdo\IntranetAppBase\Services\NotificationPreferenceResolver;
use Hwkdo\IntranetAppBase\Services\NotificationTypeCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use NotificationChannels\WebPush\PushSubscription;

class NotificationSettings extends Component
{
    /** @var array<string, array{enabled: bool, channels: list<string>}> */
    public array $preferences = [];

    public string $searchTerm = '';

    public string $activeTab = 'apps';

    public function mount(NotificationTypeCatalog $catalog, NotificationPreferenceResolver $resolver): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        foreach ($catalog->all() as $definition) {
            $resolved = $resolver->resolvePreference($user, $definition);
            $this->preferences[$definition->key] = [
                'enabled' => $resolved['enabled'],
                'channels' => $resolved['channels'],
            ];
        }
    }

    #[Computed]
    public function groupedTypes(): \Illuminate\Support\Collection
    {
        return app(NotificationTypeCatalog::class)->groupedByApp();
    }

    #[Computed]
    public function filteredGroupedTypes(): \Illuminate\Support\Collection
    {
        $term = trim(mb_strtolower($this->searchTerm));

        if ($term === '') {
            return $this->groupedTypes;
        }

        $result = collect();

        foreach ($this->groupedTypes as $appIdentifier => $types) {
            $appName = $types->first()?->appName ?? '';

            $filtered = $types->filter(function (NotificationTypeDefinition $definition) use ($term, $appIdentifier, $appName): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $definition->key,
                    $definition->label,
                    $definition->description ?? '',
                    $appIdentifier,
                    $appName,
                ]));

                return str_contains($haystack, $term);
            })->values();

            if ($filtered->isNotEmpty()) {
                $result->put($appIdentifier, $filtered);
            }
        }

        return $result;
    }

    #[Computed]
    public function appsGroupedTypes(): \Illuminate\Support\Collection
    {
        $result = collect();

        foreach ($this->filteredGroupedTypes as $appIdentifier => $types) {
            $filtered = $types->filter(
                static fn (NotificationTypeDefinition $definition): bool => ! str_starts_with($definition->key, 'news.category.'),
            )->values();

            if ($filtered->isNotEmpty()) {
                $result->put($appIdentifier, $filtered);
            }
        }

        return $result;
    }

    #[Computed]
    public function newsGroupedTypes(): \Illuminate\Support\Collection
    {
        $result = collect();

        foreach ($this->filteredGroupedTypes as $appIdentifier => $types) {
            $filtered = $types->filter(
                static fn (NotificationTypeDefinition $definition): bool => str_starts_with($definition->key, 'news.category.'),
            )->values();

            if ($filtered->isNotEmpty()) {
                $result->put($appIdentifier, $filtered);
            }
        }

        return $result;
    }

    #[Computed]
    public function newsDefinitions(): \Illuminate\Support\Collection
    {
        return $this->newsGroupedTypes
            ->flatten(1)
            ->values();
    }

    #[Computed]
    public function channelOptions(): array
    {
        return collect(NotificationChannelKey::cases())
            ->mapWithKeys(fn (NotificationChannelKey $channel): array => [
                $channel->value => $channel->label(),
            ])
            ->all();
    }

    #[Computed]
    public function pushSubscriptions(): \Illuminate\Support\Collection
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'pushSubscriptions')) {
            return collect();
        }

        return $user->pushSubscriptions()->latest()->get();
    }

    #[Computed]
    public function teamsAvailable(): bool
    {
        $user = Auth::user();

        return $user
            ? app(NotificationPreferenceResolver::class)->teamsAvailableFor($user)
            : false;
    }

    #[Computed]
    public function webPushConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key'));
    }

    #[Computed]
    public function vapidPublicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function save(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $catalog = app(NotificationTypeCatalog::class);
        $resolver = app(NotificationPreferenceResolver::class);

        foreach ($this->preferences as $typeKey => $preference) {
            $definition = $catalog->find($typeKey);

            if ($definition === null) {
                continue;
            }

            $resolver->savePreference(
                $user,
                $definition,
                (bool) ($preference['enabled'] ?? true),
                $preference['channels'] ?? [],
            );
        }

        Flux::toast(
            heading: 'Benachrichtigungen gespeichert',
            text: 'Ihre Benachrichtigungseinstellungen wurden aktualisiert.',
            variant: 'success',
        );
    }

    public function toggleChannel(string $typeKey, string $channelKey): void
    {
        $definition = app(NotificationTypeCatalog::class)->find($typeKey);

        if ($definition === null) {
            return;
        }

        $channels = $this->preferences[$typeKey]['channels'] ?? [];
        $available = $definition->resolvedAvailableChannels();

        if (! in_array($channelKey, $available, true)) {
            return;
        }

        if (in_array($channelKey, $channels, true)) {
            $channels = array_values(array_filter(
                $channels,
                fn (string $channel): bool => $channel !== $channelKey,
            ));

            if ($definition->mandatory && $channels === []) {
                Flux::toast(
                    heading: 'Pflicht-Benachrichtigung',
                    text: 'Bei Pflicht-Benachrichtigungen muss mindestens ein Kanal aktiv bleiben.',
                    variant: 'warning',
                );

                return;
            }
        } else {
            $channels[] = $channelKey;
        }

        $this->preferences[$typeKey]['channels'] = $channels;
    }

    public function registerPushSubscription(
        string $endpoint,
        ?string $key = null,
        ?string $token = null,
        ?string $contentEncoding = null,
    ): void {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'updatePushSubscription')) {
            return;
        }

        $user->updatePushSubscription($endpoint, $key, $token, $contentEncoding);

        unset($this->pushSubscriptions);

        Flux::toast(
            heading: 'Web-Push aktiviert',
            text: 'Dieser Browser empfängt ab sofort Web-Push-Benachrichtigungen.',
            variant: 'success',
        );
    }

    public function deletePushSubscription(string $endpoint): void
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'deletePushSubscription')) {
            return;
        }

        $user->deletePushSubscription($endpoint);

        unset($this->pushSubscriptions);

        Flux::toast(
            heading: 'Gerät entfernt',
            text: 'Die Web-Push-Registrierung wurde gelöscht.',
            variant: 'success',
        );
    }

    public function deleteAllPushSubscriptions(): void
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'pushSubscriptions')) {
            return;
        }

        $user->pushSubscriptions()->each(function (PushSubscription $subscription): void {
            if (method_exists($user = Auth::user(), 'deletePushSubscription')) {
                $user->deletePushSubscription($subscription->endpoint);
            }
        });

        unset($this->pushSubscriptions);

        Flux::toast(
            heading: 'Alle Geräte entfernt',
            text: 'Alle Web-Push-Registrierungen wurden gelöscht.',
            variant: 'success',
        );
    }

    public function sendTestNotification(string $channelKey): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $label = NotificationChannelKey::tryFrom($channelKey)?->label() ?? $channelKey;

        if ($this->channelDisabledForUser($channelKey)) {
            Flux::toast(
                heading: 'Kanal nicht verfügbar',
                text: "{$label} ist für Ihr Konto nicht verfügbar.",
                variant: 'warning',
            );

            return;
        }

        try {
            $user->notifyNow(new TestNotification([$channelKey]));

            Flux::toast(
                heading: 'Testbenachrichtigung gesendet',
                text: "Eine Testbenachrichtigung wurde über {$label} versendet.",
                variant: 'success',
            );
        } catch (\Throwable $e) {
            report($e);

            Flux::toast(
                heading: 'Fehler',
                text: "Testbenachrichtigung über {$label} fehlgeschlagen: {$e->getMessage()}",
                variant: 'danger',
            );
        }
    }

    public function channelDisabledForUser(string $channelKey): bool
    {
        $user = Auth::user();
        $resolver = app(NotificationPreferenceResolver::class);

        if (! $user) {
            return true;
        }

        return match ($channelKey) {
            NotificationChannelKey::Teams->value => ! $resolver->teamsAvailableFor($user),
            NotificationChannelKey::WebPush->value => ! $this->webPushConfigured,
            default => false,
        };
    }

    public function render()
    {
        return view('intranet-app-base::livewire.notification-settings');
    }
}
