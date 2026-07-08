<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

interface AppAiSettingsSourceInterface
{
    public function forApp(string $appIdentifier): ?HasAiSettings;
}
