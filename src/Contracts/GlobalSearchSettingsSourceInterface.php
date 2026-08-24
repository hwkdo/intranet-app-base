<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

interface GlobalSearchSettingsSourceInterface
{
    public function previewLimit(): int;

    public function modalLimit(): int;

    public function minChars(): int;
}
