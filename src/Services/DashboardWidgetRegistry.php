<?php

namespace Hwkdo\IntranetAppBase\Services;

use App\Models\User;
use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;

class DashboardWidgetRegistry
{
    /**
     * @return array<DashboardWidgetDefinition>
     */
    public function widgetsForApp(string $appIdentifier, User $user): array
    {
        $appClass = $this->resolveAppClassByIdentifier($appIdentifier);

        if ($appClass === null || ! is_subclass_of($appClass, ProvidesDashboardWidgetsInterface::class)) {
            return [];
        }

        $definitions = [];
        foreach ($appClass::dashboardWidgetProviders() as $providerClass) {
            foreach ($providerClass::widgets() as $widgetDefinition) {
                if (! ($widgetDefinition instanceof DashboardWidgetDefinition)) {
                    continue;
                }

                if ($widgetDefinition->permission !== null && ! $user->can($widgetDefinition->permission)) {
                    continue;
                }

                $definitions[] = $widgetDefinition;
            }
        }

        return $definitions;
    }

    /**
     * @return class-string<IntranetAppInterface>|null
     */
    private function resolveAppClassByIdentifier(string $appIdentifier): ?string
    {
        foreach (IntranetAppBase::getIntranetAppPackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if ($appClass === null || ! class_exists($appClass)) {
                continue;
            }

            if (! is_subclass_of($appClass, IntranetAppInterface::class)) {
                continue;
            }

            if ($appClass::identifier() === $appIdentifier) {
                return $appClass;
            }
        }

        return null;
    }
}
