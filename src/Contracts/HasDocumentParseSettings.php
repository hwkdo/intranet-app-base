<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;

interface HasDocumentParseSettings
{
    public function documentParseEngine(): ?DocumentParseEngine;

    public function documentParseTier(): ?string;
}
