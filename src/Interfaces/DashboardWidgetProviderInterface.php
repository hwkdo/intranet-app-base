<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;

interface DashboardWidgetProviderInterface
{
    /**
     * @return array<DashboardWidgetDefinition>
     */
    public static function widgets(): array;
}
