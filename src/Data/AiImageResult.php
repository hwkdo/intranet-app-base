<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Spatie\LaravelData\Data;

class AiImageResult extends Data
{
    public function __construct(
        public string $binary,
        public string $mimeType = 'image/png',
    ) {}
}
