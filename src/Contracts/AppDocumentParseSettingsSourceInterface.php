<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

interface AppDocumentParseSettingsSourceInterface
{
    public function forApp(string $appIdentifier): ?HasDocumentParseSettings;
}
