<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExternalAppConfigSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $configKey,
        public readonly array $config
    ) {}
}
