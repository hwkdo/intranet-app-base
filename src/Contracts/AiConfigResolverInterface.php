<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Data\ResolvedAiConfig;
use Hwkdo\IntranetAppBase\Enums\AiCapability;

interface AiConfigResolverInterface
{
    public function resolve(string $appIdentifier, AiCapability $capability): ResolvedAiConfig;

    public function resolveWithContext(
        string $appIdentifier,
        AiCapability $capability,
        ?HasAiSettings $appSettings = null,
    ): ResolvedAiConfig;
}
