<?php

namespace Hwkdo\IntranetAppBase\Data;

class DashboardWidgetDefinition
{
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $component,
        public ?string $permission = null,
        public int $defaultW = 4,
        public int $defaultH = 3,
        public int $minW = 3,
        public int $minH = 2,
        public bool $defaultEnabled = true,
        public bool $mandatory = false,
        public ?string $sourceApp = null,
    ) {}
}
