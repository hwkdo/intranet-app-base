<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Enums\AiProvider;

interface HasAiSettings
{
    public function textProviderOverride(): ?AiProvider;

    public function textModelOverride(): ?string;

    public function imageProviderOverride(): ?AiProvider;

    public function imageModelOverride(): ?string;
}
