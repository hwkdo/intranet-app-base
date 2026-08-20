<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

class NotificationTypeDefinition
{
    /**
     * @param  list<string>  $defaultChannels
     * @param  list<string>|null  $availableChannels
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly ?string $description = null,
        public readonly bool $mandatory = false,
        public readonly bool $defaultEnabled = true,
        public readonly array $defaultChannels = ['inbox', 'mail'],
        public readonly ?array $availableChannels = null,
    ) {}

    /**
     * @return list<string>
     */
    public function resolvedAvailableChannels(): array
    {
        return $this->availableChannels ?? \Hwkdo\IntranetAppBase\Enums\NotificationChannelKey::defaultAvailable();
    }
}
