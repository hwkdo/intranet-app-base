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
     * Kern-Widgets (Konfiguration) plus Widgets aller Intranet-Apps, für die der Nutzer App-Zugriff hat;
     * App-Widget-Keys werden mit "{identifier}." prefixiert.
     *
     * @return array<int, DashboardWidgetDefinition>
     */
    public function widgetsForMainDashboard(User $user): array
    {
        $definitions = [];

        foreach (config('intranet-app-base.main_dashboard_core_widget_providers', []) as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass) || ! method_exists($providerClass, 'widgets')) {
                continue;
            }

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

        foreach (IntranetAppBase::getIntranetAppPackages() as $packageName => $packageData) {
            $appClass = IntranetAppBase::getAppClass($packageName, $packageData);

            if ($appClass === null || ! class_exists($appClass)) {
                continue;
            }

            if (! is_subclass_of($appClass, IntranetAppInterface::class)) {
                continue;
            }

            $identifier = $appClass::identifier();

            if (! $user->can('see-app-'.$identifier)) {
                continue;
            }

            if (! is_subclass_of($appClass, ProvidesDashboardWidgetsInterface::class)) {
                continue;
            }

            foreach ($this->widgetsForApp($identifier, $user) as $widgetDefinition) {
                $definitions[] = $this->cloneDefinitionWithAppPrefix($widgetDefinition, $identifier);
            }
        }

        return $definitions;
    }

    private function cloneDefinitionWithAppPrefix(DashboardWidgetDefinition $definition, string $appIdentifier): DashboardWidgetDefinition
    {
        return new DashboardWidgetDefinition(
            key: $appIdentifier.'.'.$definition->key,
            title: $definition->title,
            description: $definition->description,
            component: $definition->component,
            permission: $definition->permission,
            defaultW: $definition->defaultW,
            defaultH: $definition->defaultH,
            minW: $definition->minW,
            minH: $definition->minH,
            defaultEnabled: $definition->defaultEnabled,
            mandatory: false,
            sourceApp: $appIdentifier,
        );
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
