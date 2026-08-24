<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

class SearchResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly string $icon,
        public readonly ?string $subtitle = null,
        public readonly ?string $sourceKey = null,
        public readonly ?float $score = null,
    ) {}
}
