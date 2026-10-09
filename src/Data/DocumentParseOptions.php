<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Spatie\LaravelData\Data;

class DocumentParseOptions extends Data
{
    public function __construct(
        public ?string $llamaParseTier = null,
        public ?string $visionPrompt = null,
        public int $maxVisionPages = 30,
    ) {}
}
