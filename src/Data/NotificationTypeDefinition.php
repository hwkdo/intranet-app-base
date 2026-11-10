<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Enums\NotificationChannelKey;

class NotificationTypeDefinition
{
    /**
     * @param  list<string>  $defaultChannels
     * @param  list<string>|null  $availableChannels
     * @param  list<string>  $requiredChannels  Kanäle, die bei aktivem Typ immer gesetzt bleiben
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly ?string $description = null,
        public readonly bool $mandatory = false,
        public readonly bool $defaultEnabled = true,
        public readonly array $defaultChannels = ['inbox'],
        public readonly ?array $availableChannels = null,
        public readonly array $requiredChannels = [],
    ) {}

    /**
     * @return list<string>
     */
    public function resolvedAvailableChannels(): array
    {
        return $this->availableChannels ?? NotificationChannelKey::defaultAvailable();
    }

    public function channelIsRequired(string $channelKey): bool
    {
        return in_array($channelKey, $this->requiredChannels, true)
            && in_array($channelKey, $this->resolvedAvailableChannels(), true);
    }
}
