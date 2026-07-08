<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Spatie\LaravelData\Data;

class AiChatResult extends Data
{
    public function __construct(
        public string $content,
        public ?string $rawJson = null,
    ) {}
}
