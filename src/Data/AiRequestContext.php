<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Spatie\LaravelData\Data;

class AiRequestContext extends Data
{
    public function __construct(
        public string $appIdentifier,
        public AiCapability $capability,
        public ?int $userId = null,
        public ?AiProvider $providerOverride = null,
        public ?string $modelOverride = null,
    ) {}
}
