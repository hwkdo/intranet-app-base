<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Hwkdo\IntranetAppBase\Contracts\GlobalSearchSettingsSourceInterface;

class DefaultGlobalSearchSettingsSource implements GlobalSearchSettingsSourceInterface
{
    public function previewLimit(): int
    {
        return 5;
    }

    public function modalLimit(): int
    {
        return 50;
    }

    public function minChars(): int
    {
        return 2;
    }
}
