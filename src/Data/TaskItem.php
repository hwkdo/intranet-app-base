<?php

namespace Hwkdo\IntranetAppBase\Data;

class TaskItem
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly string $appIcon,
        public readonly ?string $description = null,
        public readonly ?string $badge = null,
        public readonly int $priority = 0,
    ) {}
}
