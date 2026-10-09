<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;
use Spatie\LaravelData\Data;

class ResolvedDocumentParseConfig extends Data
{
    public function __construct(
        public DocumentParseEngine $engine,
        public string $llamaParseTier,
        public AiConfigSource $source,
    ) {}
}
