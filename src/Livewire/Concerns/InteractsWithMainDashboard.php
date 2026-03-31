<?php

namespace Hwkdo\IntranetAppBase\Livewire\Concerns;

use Hwkdo\IntranetAppBase\Services\DashboardGridLayoutService;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

trait InteractsWithMainDashboard
{
    protected function handleMainDashboardToggleWidget(string $widgetKey): void
    {
        $widget = collect($this->availableWidgets)->firstWhere('key', $widgetKey);
        if (! $widget || ($widget['mandatory'] ?? false)) {
            return;
        }

        if (in_array($widgetKey, $this->enabledWidgets, true)) {
            $this->enabledWidgets = array_values(array_filter(
                $this->enabledWidgets,
                static fn (string $k): bool => $k !== $widgetKey,
            ));
        } else {
            $this->enabledWidgets[] = $widgetKey;
        }

        $this->recomputeLayoutFromService();
        $this->persistCurrentGridState();
        $this->dispatch('main-dashboard-sync', layout: $this->layout);
    }

    protected function handleMainDashboardResetToDefault(
        DashboardGridLayoutService $gridLayoutService,
        DashboardWidgetRegistry $registry,
    ): void {
        $definitions = $registry->widgetsForMainDashboard(auth()->user());
        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);
        $mandatoryKeys = $gridLayoutService->mandatoryKeysFromDefinitions($definitions);
        $allowedLookup = array_fill_keys(array_keys($widgetMeta), true);

        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $this->mainDashboardDefaultEnabledWidgetKeys(),
            $allowedLookup,
            $mandatoryKeys,
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $this->mainDashboardDefaultLayout(),
        );
        $this->widgetItemCounts = $this->defaultWidgetItemCounts($this->mainDashboardFallbackCount());

        $this->persistCurrentGridState();
        $this->dispatch('main-dashboard-sync', layout: $this->layout);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function handleMainDashboardSaveLayout(array $items): void
    {
        $validated = validator(
            ['items' => $items],
            [
                'items' => ['array'],
                'items.*.widgetKey' => ['required', 'string', Rule::in($this->enabledWidgets)],
                'items.*.x' => ['required', 'integer', 'min:0'],
                'items.*.y' => ['required', 'integer', 'min:0'],
                'items.*.w' => ['required', 'integer', 'min:1', 'max:12'],
                'items.*.h' => ['required', 'integer', 'min:1', 'max:24'],
            ],
        )->validate();

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $registry = app(DashboardWidgetRegistry::class);
        $definitions = $registry->widgetsForMainDashboard(auth()->user());
        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);

        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $validated['items'],
        );

        $this->persistCurrentGridState();
        $this->skipRender();
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool, mandatory: bool, sourceApp: ?string}>
     */
    protected function handleMainDashboardEnabledWidgetDefinitions(): array
    {
        $enabledLookup = array_fill_keys($this->enabledWidgets, true);

        return array_values(array_filter(
            $this->availableWidgets,
            static fn (array $widget): bool => isset($enabledLookup[$widget['key']]),
        ));
    }

    protected function handleMainDashboardSupportsWidgetItemCount(string $widgetKey): bool
    {
        return in_array($this->baseCountableWidgetKey($widgetKey), $this->countableWidgetKeys(), true);
    }

    protected function handleMainDashboardWidgetItemCountValue(string $widgetKey): int
    {
        $fallback = min(max((int) $this->mainDashboardFallbackCount(), self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);

        if (isset($this->widgetItemCounts[$widgetKey])) {
            return min(max((int) $this->widgetItemCounts[$widgetKey], self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);
        }

        $baseWidgetKey = $this->baseCountableWidgetKey($widgetKey);
        if (isset($this->widgetItemCounts[$baseWidgetKey])) {
            return min(max((int) $this->widgetItemCounts[$baseWidgetKey], self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    abstract protected function mainDashboardDefaultEnabledWidgetKeys(): array;

    /**
     * @return list<array{widgetKey: string, x: int, y: int, w: int, h: int}>
     */
    abstract protected function mainDashboardDefaultLayout(): array;

    abstract protected function mainDashboardFallbackCount(): int;
}
