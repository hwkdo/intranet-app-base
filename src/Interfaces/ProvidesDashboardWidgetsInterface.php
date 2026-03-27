<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

interface ProvidesDashboardWidgetsInterface
{
    /**
     * @return array<class-string<DashboardWidgetProviderInterface>>
     */
    public static function dashboardWidgetProviders(): array;
}
