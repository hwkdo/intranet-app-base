<?php

namespace Hwkdo\IntranetAppBase\Livewire\Concerns;

use App\Models\User;
use Hwkdo\IntranetAppBase\Services\DashboardGridLayoutService;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

trait InteractsWithAppDashboard
{
    private const WIDGET_ITEM_COUNT_MIN = 1;

    private const WIDGET_ITEM_COUNT_MAX = 30;

    /** @var array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool}> */
    public array $availableWidgets = [];

    /** @var list<string> */
    public array $enabledWidgets = [];

    /** @var array<int, array{widgetKey: string, x: int, y: int, w: int, h: int}> */
    public array $layout = [];

    /** @var array<string, int> */
    public array $widgetItemCounts = [];

    public int $widgetRenderVersion = 1;

    /** @var array<string, array{minW: int, minH: int, defaultW: int, defaultH: int, mandatory: bool}> */
    public array $widgetMetaByKey = [];

    /** @var list<string> */
    public array $mandatoryWidgetKeys = [];

    protected function initializeAppDashboard(string $appIdentifier, DashboardWidgetRegistry $registry): void
    {
        /** @var User $user */
        $user = auth()->user();

        $definitions = $registry->widgetsForApp($appIdentifier, $user);
        $gridLayoutService = app(DashboardGridLayoutService::class);

        $this->availableWidgets = array_map(static function ($definition): array {
            return [
                'key' => $definition->key,
                'title' => $definition->title,
                'description' => $definition->description,
                'component' => $definition->component,
                'defaultW' => $definition->defaultW,
                'defaultH' => $definition->defaultH,
                'minW' => $definition->minW,
                'minH' => $definition->minH,
                'defaultEnabled' => $definition->defaultEnabled,
            ];
        }, $definitions);

        $this->widgetMetaByKey = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);
        $this->mandatoryWidgetKeys = $gridLayoutService->mandatoryKeysFromDefinitions($definitions);
        $allowedLookup = array_fill_keys(array_keys($this->widgetMetaByKey), true);

        $settings = Arr::get($user->settings->app->toArray(), $appIdentifier.'.dashboard', []);
        $savedEnabled = Arr::wrap($settings['enabledWidgets'] ?? []);
        $savedLayout = Arr::wrap($settings['layout'] ?? []);
        $savedItemCounts = Arr::wrap($settings['widgetItemCounts'] ?? []);
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $savedEnabled !== [] ? $savedEnabled : $defaultEnabled,
            $allowedLookup,
            $this->mandatoryWidgetKeys,
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $this->widgetMetaByKey,
            $this->enabledWidgets,
            $savedLayout,
        );
        $this->widgetItemCounts = $this->sanitizeWidgetItemCounts($savedItemCounts);
        $this->persistDashboardSettings($appIdentifier);
    }

    public function toggleWidget(string $widgetKey): void
    {
        if (! in_array($widgetKey, $this->availableWidgetKeys(), true)) {
            return;
        }

        if (in_array($widgetKey, $this->mandatoryWidgetKeys, true)) {
            return;
        }

        if (in_array($widgetKey, $this->enabledWidgets, true)) {
            $this->enabledWidgets = array_values(array_filter(
                $this->enabledWidgets,
                static fn (string $enabledWidget): bool => $enabledWidget !== $widgetKey
            ));
        } else {
            $this->enabledWidgets[] = $widgetKey;
        }

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $allowedLookup = array_fill_keys(array_keys($this->widgetMetaByKey), true);
        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $this->enabledWidgets,
            $allowedLookup,
            $this->mandatoryWidgetKeys,
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $this->widgetMetaByKey,
            $this->enabledWidgets,
            $this->layout,
        );
        $this->persistDashboardSettings($this->dashboardAppIdentifier());

        $this->dispatch($this->dashboardSyncEventName(), layout: $this->layout);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveLayout(array $items): void
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
            ]
        )->validate();

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $this->layout = $gridLayoutService->normalizeLayout(
            $this->widgetMetaByKey,
            $this->enabledWidgets,
            $validated['items'],
        );
        $this->persistDashboardSettings($this->dashboardAppIdentifier());
        $this->skipRender();
    }

    public function saveWidgetItemCount(string $widgetKey, mixed $value): void
    {
        if (! $this->supportsWidgetItemCount($widgetKey)) {
            return;
        }

        $this->widgetItemCounts[$widgetKey] = is_numeric($value) ? (int) $value : $value;

        $validated = validator(
            ['value' => $this->widgetItemCounts[$widgetKey] ?? null],
            ['value' => ['required', 'integer', 'min:'.self::WIDGET_ITEM_COUNT_MIN, 'max:'.self::WIDGET_ITEM_COUNT_MAX]],
        )->validate();

        $this->widgetItemCounts[$widgetKey] = (int) $validated['value'];
        $this->persistDashboardSettings($this->dashboardAppIdentifier());
        $this->skipRender();
    }

    public function resetToDefault(): void
    {
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $allowedLookup = array_fill_keys(array_keys($this->widgetMetaByKey), true);
        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $defaultEnabled,
            $allowedLookup,
            $this->mandatoryWidgetKeys,
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $this->widgetMetaByKey,
            $this->enabledWidgets,
            [],
        );
        $this->widgetItemCounts = $this->defaultWidgetItemCounts();
        $this->widgetRenderVersion++;

        $this->persistDashboardSettings($this->dashboardAppIdentifier());
        $this->dispatch($this->dashboardSyncEventName(), layout: $this->layout);
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool}>
     */
    public function enabledWidgetDefinitions(): array
    {
        $enabledLookup = array_fill_keys($this->enabledWidgets, true);

        return array_values(array_filter(
            $this->availableWidgets,
            static fn (array $widget): bool => isset($enabledLookup[$widget['key']])
        ));
    }

    public function supportsWidgetItemCount(string $widgetKey): bool
    {
        return in_array($widgetKey, $this->availableWidgetKeys(), true);
    }

    public function widgetItemCountValue(string $widgetKey): int
    {
        $value = $this->widgetItemCounts[$widgetKey] ?? 5;

        return min(max((int) $value, self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);
    }

    protected function persistDashboardSettings(string $appIdentifier): void
    {
        /** @var User $user */
        $user = auth()->user();
        $user->settings = $user->settings->updateAppSettings($appIdentifier, [
            'dashboard' => [
                'version' => 1,
                'enabledWidgets' => $this->enabledWidgets,
                'layout' => $this->layout,
                'widgetItemCounts' => $this->widgetItemCounts,
            ],
        ]);
        $user->save();
    }

    /**
     * @return list<string>
     */
    protected function availableWidgetKeys(): array
    {
        return array_values(array_map(
            static fn (array $widget): string => $widget['key'],
            $this->availableWidgets
        ));
    }

    /**
     * @param  array<mixed>  $rawCounts
     * @return array<string, int>
     */
    protected function sanitizeWidgetItemCounts(array $rawCounts): array
    {
        $counts = [];

        foreach ($this->availableWidgetKeys() as $widgetKey) {
            $rawValue = $rawCounts[$widgetKey] ?? 5;
            $value = is_numeric($rawValue) ? (int) $rawValue : 5;
            $counts[$widgetKey] = min(max($value, self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    protected function defaultWidgetItemCounts(): array
    {
        return $this->sanitizeWidgetItemCounts([]);
    }

    abstract protected function dashboardAppIdentifier(): string;

    abstract protected function dashboardSyncEventName(): string;
}
