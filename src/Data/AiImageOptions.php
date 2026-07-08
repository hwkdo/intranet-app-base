<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Spatie\LaravelData\Data;

class AiImageOptions extends Data
{
    /**
     * @param  array<mixed>  $attachments
     */
    public function __construct(
        public ?string $size = null,
        public ?string $quality = null,
        public array $attachments = [],
        public ?int $timeout = null,
    ) {}
}
