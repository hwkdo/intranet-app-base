<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Contracts\LightRagCitationLinker;

class LightRagCitationRegistry
{
    /** @var array<string, LightRagCitationLinker> */
    private array $linkers = [];

    public function register(LightRagCitationLinker $linker): void
    {
        $this->linkers[$linker->instance()] = $linker;
    }

    public function for(string $instance): ?LightRagCitationLinker
    {
        return $this->linkers[$instance] ?? null;
    }
}
