<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Data\ResolvedDocumentParseConfig;

interface DocumentParseConfigResolverInterface
{
    public function resolve(string $appIdentifier): ResolvedDocumentParseConfig;
}
