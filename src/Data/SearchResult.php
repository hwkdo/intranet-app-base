<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

class SearchResult
{
    /**
     * @param  string  $favoriteKey  Stable identity: "{sourceKey}:{entityId}" (required for favorites).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly string $icon,
        public readonly string $favoriteKey,
        public readonly ?string $subtitle = null,
        public readonly ?string $sourceKey = null,
        public readonly ?float $score = null,
        public readonly bool $download = false,
    ) {}

    public function entityId(): string
    {
        $prefix = ($this->sourceKey ?? '').':';

        if ($this->sourceKey !== null && str_starts_with($this->favoriteKey, $prefix)) {
            return substr($this->favoriteKey, strlen($prefix));
        }

        $pos = strpos($this->favoriteKey, ':');

        return $pos === false ? $this->favoriteKey : substr($this->favoriteKey, $pos + 1);
    }
}
