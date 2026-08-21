<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Enums\NotificationChannelKey;
use Hwkdo\IntranetAppBase\Models\IntranetNotificationPreference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use NotificationChannels\WebPush\WebPushChannel;

class NotificationPreferenceResolver
{
    public function __construct(
        private readonly NotificationTypeCatalog $catalog,
    ) {}

    /**
     * @return list<string>
     */
    public function viaChannels(Authenticatable $notifiable, string $typeKey): array
    {
        $definition = $this->catalog->find($typeKey);

        if ($definition === null) {
            return ['database'];
        }

        $preference = $this->resolvePreference($notifiable, $definition);

        if (! $preference['enabled']) {
            return [];
        }

        $channels = [];

        foreach ($preference['channels'] as $channelKey) {
            $laravelChannel = $this->mapToLaravelChannel($channelKey);

            if ($laravelChannel === null) {
                continue;
            }

            if (! $this->canUseChannel($notifiable, $channelKey)) {
                continue;
            }

            $channels[] = $laravelChannel;
        }

        return array_values(array_unique($channels));
    }

    public function shouldSendOnChannel(Authenticatable $notifiable, string $typeKey, string $channel): bool
    {
        $channelKey = $this->mapFromLaravelChannel($channel);

        if ($channelKey === null) {
            return true;
        }

        $definition = $this->catalog->find($typeKey);

        if ($definition === null) {
            return true;
        }

        $preference = $this->resolvePreference($notifiable, $definition);

        if (! $preference['enabled']) {
            return false;
        }

        if (! in_array($channelKey, $preference['channels'], true)) {
            return false;
        }

        return $this->canUseChannel($notifiable, $channelKey);
    }

    /**
     * @return array{enabled: bool, channels: list<string>}
     */
    public function resolvePreference(Authenticatable $notifiable, NotificationTypeDefinition $definition): array
    {
        $defaultChannels = $this->normalizeChannels(
            $definition->defaultChannels,
            $definition->resolvedAvailableChannels(),
        );

        if ($definition->mandatory) {
            $defaultEnabled = true;
            $defaultChannels = $this->ensureAtLeastOneChannel($defaultChannels, $definition->resolvedAvailableChannels());
        } else {
            $defaultEnabled = $definition->defaultEnabled;
        }

        $stored = $this->loadStoredPreference($notifiable, $definition->key);

        if ($stored === null) {
            return [
                'enabled' => $defaultEnabled,
                'channels' => $defaultChannels,
            ];
        }

        $channels = $stored->channels !== null
            ? $this->normalizeChannels($stored->channels, $definition->resolvedAvailableChannels())
            : $defaultChannels;

        if ($definition->mandatory) {
            $channels = $this->ensureAtLeastOneChannel($channels, $definition->resolvedAvailableChannels());

            return [
                'enabled' => true,
                'channels' => $channels,
            ];
        }

        return [
            'enabled' => (bool) $stored->enabled,
            'channels' => $channels,
        ];
    }

    public function savePreference(
        Authenticatable $notifiable,
        NotificationTypeDefinition $definition,
        bool $enabled,
        array $channels,
    ): void {
        $userId = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;

        if (! $userId) {
            return;
        }

        $normalizedChannels = $this->normalizeChannels(
            $channels,
            $definition->resolvedAvailableChannels(),
        );

        if ($definition->mandatory) {
            $enabled = true;
            $normalizedChannels = $this->ensureAtLeastOneChannel(
                $normalizedChannels,
                $definition->resolvedAvailableChannels(),
            );
        }

        IntranetNotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'type_key' => $definition->key,
            ],
            [
                'enabled' => $enabled,
                'channels' => $normalizedChannels,
            ],
        );
    }

    public function canUseChannel(Authenticatable $notifiable, string $channelKey): bool
    {
        return match ($channelKey) {
            NotificationChannelKey::Teams->value => $this->teamsAvailableFor($notifiable),
            NotificationChannelKey::WebPush->value => $this->webPushAvailableFor($notifiable),
            default => true,
        };
    }

    public function teamsAvailableFor(Authenticatable $notifiable): bool
    {
        if (! class_exists(\Hwkdo\IntranetAppTeamsBot\Channels\TeamsChannel::class)) {
            return false;
        }

        if (! filled($notifiable->socialite_id ?? null)) {
            return false;
        }

        if (! interface_exists(\Hwkdo\IntranetAppTeamsBot\Interfaces\TeamsActivityFeedServiceInterface::class)) {
            return false;
        }

        return app(\Hwkdo\IntranetAppTeamsBot\Interfaces\TeamsActivityFeedServiceInterface::class)->isEnabled();
    }

    public function webPushAvailableFor(Authenticatable $notifiable): bool
    {
        if (! class_exists(WebPushChannel::class)) {
            return false;
        }

        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return false;
        }

        return $notifiable->pushSubscriptions()->exists();
    }

    private function loadStoredPreference(Authenticatable $notifiable, string $typeKey): ?IntranetNotificationPreference
    {
        if (! Schema::hasTable('intranet_notification_preferences')) {
            return null;
        }

        $userId = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;

        if (! $userId) {
            return null;
        }

        return IntranetNotificationPreference::query()
            ->where('user_id', $userId)
            ->where('type_key', $typeKey)
            ->first();
    }

    /**
     * @param  list<string>  $channels
     * @param  list<string>  $available
     * @return list<string>
     */
    private function normalizeChannels(array $channels, array $available): array
    {
        $normalized = array_values(array_unique(array_filter(
            $channels,
            fn (string $channel): bool => in_array($channel, $available, true),
        )));

        return $normalized;
    }

    /**
     * @param  list<string>  $channels
     * @param  list<string>  $available
     * @return list<string>
     */
    private function ensureAtLeastOneChannel(array $channels, array $available): array
    {
        if ($channels !== []) {
            return $channels;
        }

        foreach ([NotificationChannelKey::Inbox->value, NotificationChannelKey::Mail->value] as $fallback) {
            if (in_array($fallback, $available, true)) {
                return [$fallback];
            }
        }

        return [$available[0] ?? NotificationChannelKey::Inbox->value];
    }

    public function mapToLaravelChannel(string $channelKey): ?string
    {
        return match ($channelKey) {
            NotificationChannelKey::Inbox->value => 'database',
            NotificationChannelKey::Mail->value => 'mail',
            NotificationChannelKey::WebPush->value => class_exists(WebPushChannel::class)
                ? WebPushChannel::class
                : null,
            NotificationChannelKey::Teams->value => class_exists(\Hwkdo\IntranetAppTeamsBot\Channels\TeamsChannel::class)
                ? \Hwkdo\IntranetAppTeamsBot\Channels\TeamsChannel::class
                : null,
            default => null,
        };
    }

    private function mapFromLaravelChannel(string $channel): ?string
    {
        return match ($channel) {
            'database' => NotificationChannelKey::Inbox->value,
            'mail' => NotificationChannelKey::Mail->value,
            WebPushChannel::class => NotificationChannelKey::WebPush->value,
            \Hwkdo\IntranetAppTeamsBot\Channels\TeamsChannel::class => NotificationChannelKey::Teams->value,
            default => null,
        };
    }
}
