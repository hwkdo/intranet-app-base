<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

final class BackgroundActivityParameter
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly mixed $default,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $description = null,
    ) {}
}
