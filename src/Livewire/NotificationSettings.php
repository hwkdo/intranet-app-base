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
use Livewire\Attributes\Url;
use Livewire\Component;
use NotificationChannels\WebPush\PushSubscription;

class NotificationSettings extends Component
{
    /** @var list<string> */
    private const ALLOWED_TABS = ['apps', 'news', 'settings'];

    /**
     * Placeholder for dots in type keys so Livewire wire:model paths stay flat.
     */
    private const TYPE_KEY_DOT_PLACEHOLDER = '__';

    /**
     * Preferences keyed by wire-safe type keys (dots replaced).
     *
     * @var array<string, array{enabled: bool, channels: list<string>}>
     */
    public array $preferences = [];

    public ?string $appIdentifier = null;

    #[Url(as: 'q', except: '', history: true)]
    public string $searchTerm = '';

    #[Url(as: 'tab', except: 'apps', history: true)]
    public string $activeTab = 'apps';

    public static function toWireKey(string $typeKey): string
    {
        return str_replace('.', self::TYPE_KEY_DOT_PLACEHOLDER, $typeKey);
    }

    public static function fromWireKey(string $wireKey): string
    {
        return str_replace(self::TYPE_KEY_DOT_PLACEHOLDER, '.', $wireKey);
    }

    public function mount(
        NotificationTypeCatalog $catalog,
        NotificationPreferenceResolver $resolver,
        ?string $appIdentifier = null,
    ): void {
        $this->appIdentifier = $appIdentifier !== null && $appIdentifier !== ''
            ? $appIdentifier
            : null;

        $this->normalizeActiveTab();

        $user = Auth::user();

        if (! $user) {
            return;
        }

        foreach ($this->definitionsForScope($catalog) as $definition) {
            $resolved = $resolver->resolvePreference($user, $definition);
            $this->preferences[self::toWireKey($definition->key)] = [
                'enabled' => $resolved['enabled'],
                'channels' => $resolved['channels'],
            ];
        }
    }

    public function isAppScoped(): bool
    {
        return $this->appIdentifier !== null;
    }

    public function updatedActiveTab(string $value): void
    {
        if (! in_array($value, self::ALLOWED_TABS, true)) {
            $this->activeTab = 'apps';
        }
    }

    private function normalizeActiveTab(): void
    {
        if (! in_array($this->activeTab, self::ALLOWED_TABS, true)) {
            $this->activeTab = 'apps';
        }
    }

    #[Computed]
    public function groupedTypes(): \Illuminate\Support\Collection
    {
        $grouped = app(NotificationTypeCatalog::class)->groupedByApp();

        if ($this->isAppScoped()) {
            return $grouped->only([$this->appIdentifier]);
        }

        return $grouped;
    }

    #[Computed]
    public function scopedAppName(): ?string
    {
        if (! $this->isAppScoped()) {
            return null;
        }

        $types = $this->groupedTypes->get($this->appIdentifier);

        return $types?->first()?->appName;
    }

    /**
     * @return \Illuminate\Support\Collection<string, NotificationTypeDefinition>
     */
    private function definitionsForScope(NotificationTypeCatalog $catalog): \Illuminate\Support\Collection
    {
        $definitions = $catalog->all();

        if (! $this->isAppScoped()) {
            return $definitions;
        }

        return $definitions
            ->filter(
                fn (NotificationTypeDefinition $definition): bool => $definition->appIdentifier === $this->appIdentifier,
            )
            ->values()
            ->keyBy(fn (NotificationTypeDefinition $definition): string => $definition->key);
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
    public function teamsBotEnabled(): bool
    {
        return (bool) config('intranet-app-teams-bot.bot.enabled', false)
            && class_exists(\Hwkdo\IntranetAppTeamsBot\Services\TeamsBotInstallationService::class);
    }

    #[Computed]
    public function teamsHasMicrosoftLogin(): bool
    {
        $user = Auth::user();

        return $user && filled($user->socialite_id ?? null);
    }

    #[Computed]
    public function teamsNeedsSetup(): bool
    {
        return $this->teamsBotEnabled
            && $this->teamsHasMicrosoftLogin
            && ! $this->teamsAvailable;
    }

    public function setupTeamsBot(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if (! $this->teamsBotEnabled) {
            Flux::toast(
                heading: 'Teams nicht verfügbar',
                text: 'Der Teams-Bot ist serverseitig deaktiviert.',
                variant: 'warning',
            );

            return;
        }

        $azureUserId = $user->socialite_id ?? null;

        if (! is_string($azureUserId) || $azureUserId === '') {
            Flux::toast(
                heading: 'Microsoft-Anmeldung fehlt',
                text: 'Bitte melden Sie sich einmal mit Microsoft an, bevor Teams eingerichtet werden kann.',
                variant: 'warning',
            );

            return;
        }

        $upn = method_exists($user, 'getAttribute') && filled($user->upn ?? null)
            ? (string) $user->upn
            : (string) ($user->email ?? '');

        if ($upn === '') {
            Flux::toast(
                heading: 'UPN fehlt',
                text: 'Für Ihr Konto konnte keine E-Mail/UPN ermittelt werden.',
                variant: 'warning',
            );

            return;
        }

        try {
            app(\Hwkdo\IntranetAppTeamsBot\Services\TeamsBotInstallationService::class)
                ->installForUserSync(
                    strtolower($azureUserId),
                    $upn,
                    is_string($user->name ?? null) ? $user->name : null,
                );

            unset($this->teamsAvailable, $this->teamsNeedsSetup);

            if (app(NotificationPreferenceResolver::class)->teamsAvailableFor($user)) {
                Flux::toast(
                    heading: 'Teams eingerichtet',
                    text: 'Der Teams-Bot ist aktiv. Sie können den Kanal Teams jetzt in den Benachrichtigungen aktivieren.',
                    variant: 'success',
                );

                return;
            }

            Flux::toast(
                heading: 'Teams-Installation gestartet',
                text: 'Bitte öffnen Sie kurz den Bot-Chat in Teams und kehren Sie danach hierher zurück.',
                variant: 'success',
            );
        } catch (\Throwable $exception) {
            Flux::toast(
                heading: 'Teams-Einrichtung fehlgeschlagen',
                text: $exception->getMessage(),
                variant: 'danger',
            );
        }
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

        foreach ($this->preferences as $wireKey => $preference) {
            $typeKey = self::fromWireKey($wireKey);
            $definition = $catalog->find($typeKey);

            if ($definition === null) {
                continue;
            }

            if ($this->isAppScoped() && $definition->appIdentifier !== $this->appIdentifier) {
                continue;
            }

            $resolver->savePreference(
                $user,
                $definition,
                (bool) ($preference['enabled'] ?? $definition->defaultEnabled),
                $preference['channels'] ?? [],
            );
        }

        Flux::toast(
            heading: 'Benachrichtigungen gespeichert',
            text: 'Ihre Benachrichtigungseinstellungen wurden aktualisiert.',
            variant: 'success',
        );
    }

    public function toggleEnabled(string $typeKey): void
    {
        $definition = app(NotificationTypeCatalog::class)->find($typeKey);

        if ($definition === null || $definition->mandatory) {
            return;
        }

        if ($this->isAppScoped() && $definition->appIdentifier !== $this->appIdentifier) {
            return;
        }

        $wireKey = self::toWireKey($typeKey);

        if (! isset($this->preferences[$wireKey])) {
            return;
        }

        $enabled = ! (bool) ($this->preferences[$wireKey]['enabled'] ?? false);
        $this->preferences[$wireKey]['enabled'] = $enabled;

        if ($enabled && ($this->preferences[$wireKey]['channels'] ?? []) === []) {
            $this->preferences[$wireKey]['channels'] = $definition->defaultChannels !== []
                ? $definition->defaultChannels
                : ['inbox'];
        }
    }

    public function toggleChannel(string $typeKey, string $channelKey): void
    {
        $definition = app(NotificationTypeCatalog::class)->find($typeKey);

        if ($definition === null) {
            return;
        }

        if ($this->isAppScoped() && $definition->appIdentifier !== $this->appIdentifier) {
            return;
        }

        $wireKey = self::toWireKey($typeKey);
        $channels = $this->preferences[$wireKey]['channels'] ?? [];
        $available = $definition->resolvedAvailableChannels();

        if (! in_array($channelKey, $available, true)) {
            return;
        }

        $isSelected = in_array($channelKey, $channels, true);

        if (! $isSelected && $this->channelDisabledForUser($channelKey)) {
            return;
        }

        if ($isSelected) {
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

        $this->preferences[$wireKey]['channels'] = $channels;
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
            NotificationChannelKey::WebPush->value => ! $resolver->webPushAvailableFor($user),
            default => false,
        };
    }

    public function channelDisabledReason(string $channelKey): ?string
    {
        if (! $this->channelDisabledForUser($channelKey)) {
            return null;
        }

        return match ($channelKey) {
            NotificationChannelKey::Teams->value => $this->teamsDisabledReason(),
            NotificationChannelKey::WebPush->value => $this->webPushDisabledReason(),
            default => 'Dieser Kanal ist für Ihr Konto nicht verfügbar.',
        };
    }

    private function teamsDisabledReason(): string
    {
        if (! $this->teamsBotEnabled) {
            return 'Der Teams-Bot ist serverseitig deaktiviert.';
        }

        if (! $this->teamsHasMicrosoftLogin) {
            return 'Bitte melden Sie sich einmal mit Microsoft an, bevor Teams aktiviert werden kann.';
        }

        return 'Teams ist noch nicht eingerichtet. Bitte unter „Einstellungen“ den Punkt „Teams einrichten“ ausführen.';
    }

    private function webPushDisabledReason(): string
    {
        if (! $this->webPushConfigured) {
            return 'Web-Push ist serverseitig noch nicht konfiguriert (VAPID-Schlüssel fehlen).';
        }

        return 'Für dieses Konto ist kein Browser registriert. Bitte unter „Einstellungen“ einen Browser für Web-Push aktivieren.';
    }

    public function render()
    {
        return view('intranet-app-base::livewire.notification-settings');
    }
}
