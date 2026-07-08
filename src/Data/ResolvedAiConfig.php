<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Spatie\LaravelData\Data;

class ResolvedAiConfig extends Data
{
    public function __construct(
        public AiProvider $provider,
        public ?string $model,
        public AiConfigSource $source,
        public AiCapability $capability,
    ) {}

    public function providerKey(): string
    {
        return $this->provider->value;
    }
}
