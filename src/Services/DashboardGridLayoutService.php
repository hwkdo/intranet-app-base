<?php

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;

class DashboardGridLayoutService
{
    public const GRID_COLUMNS = 12;

    public const LAYOUT_MAX_W = 12;

    public const LAYOUT_MAX_H = 24;

    /**
     * @param  array<int, DashboardWidgetDefinition>  $definitions
     * @return array<string, array{minW: int, minH: int, defaultW: int, defaultH: int, mandatory: bool}>
     */
    public function widgetMetaByKeyFromDefinitions(array $definitions): array
    {
        $meta = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof DashboardWidgetDefinition) {
                continue;
            }

            $meta[$definition->key] = [
                'minW' => $definition->minW,
                'minH' => $definition->minH,
                'defaultW' => $definition->defaultW,
                'defaultH' => $definition->defaultH,
                'mandatory' => $definition->mandatory,
            ];
        }

        return $meta;
    }

    /**
     * @param  array<int, DashboardWidgetDefinition>  $definitions
     * @return list<string>
     */
    public function mandatoryKeysFromDefinitions(array $definitions): array
    {
        $keys = [];
        foreach ($definitions as $definition) {
            if ($definition instanceof DashboardWidgetDefinition && $definition->mandatory) {
                $keys[] = $definition->key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, true>  $allowedLookup
     * @param  list<string>  $mandatoryKeys
     * @param  array<int, string|mixed>  $widgetKeys
     * @return list<string>
     */
    public function sanitizeEnabledWidgets(array $widgetKeys, array $allowedLookup, array $mandatoryKeys): array
    {
        $sanitized = [];

        foreach ($mandatoryKeys as $mandatoryKey) {
            if (isset($allowedLookup[$mandatoryKey]) && ! in_array($mandatoryKey, $sanitized, true)) {
                $sanitized[] = $mandatoryKey;
            }
        }

        foreach ($widgetKeys as $widgetKey) {
            if (! is_string($widgetKey) || ! isset($allowedLookup[$widgetKey])) {
                continue;
            }

            if (in_array($widgetKey, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $widgetKey;
        }

        return $sanitized;
    }

    /**
     * @param  array<string, array{minW: int, minH: int, defaultW: int, defaultH: int, mandatory: bool}>  $widgetMetaByKey
     * @param  list<string>  $enabledWidgetKeys
     * @param  array<int, array<string, mixed>>  $candidateLayout
     * @return list<array{widgetKey: string, x: int, y: int, w: int, h: int}>
     */
    public function normalizeLayout(
        array $widgetMetaByKey,
        array $enabledWidgetKeys,
        array $candidateLayout,
    ): array {
        $enabledLookup = array_fill_keys($enabledWidgetKeys, true);
        $layoutByKey = [];

        foreach ($candidateLayout as $item) {
            $widgetKey = $item['widgetKey'] ?? null;
            if (! is_string($widgetKey) || ! isset($enabledLookup[$widgetKey]) || ! isset($widgetMetaByKey[$widgetKey])) {
                continue;
            }

            $meta = $widgetMetaByKey[$widgetKey];
            $w = max((int) $meta['minW'], min(self::LAYOUT_MAX_W, (int) ($item['w'] ?? $meta['defaultW'])));
            $h = max((int) $meta['minH'], min(self::LAYOUT_MAX_H, (int) ($item['h'] ?? $meta['defaultH'])));

            $layoutByKey[$widgetKey] = [
                'widgetKey' => $widgetKey,
                'x' => max(0, min(self::GRID_COLUMNS - $w, (int) ($item['x'] ?? 0))),
                'y' => max(0, (int) ($item['y'] ?? 0)),
                'w' => $w,
                'h' => $h,
            ];
        }

        $maxY = collect($layoutByKey)->max('y');
        $nextY = is_int($maxY) ? $maxY + 1 : 0;

        foreach ($enabledWidgetKeys as $widgetKey) {
            if (isset($layoutByKey[$widgetKey])) {
                continue;
            }

            if (! isset($widgetMetaByKey[$widgetKey])) {
                continue;
            }

            $meta = $widgetMetaByKey[$widgetKey];
            $layoutByKey[$widgetKey] = [
                'widgetKey' => $widgetKey,
                'x' => 0,
                'y' => $nextY,
                'w' => (int) $meta['defaultW'],
                'h' => (int) $meta['defaultH'],
            ];

            $nextY += (int) $meta['defaultH'];
        }

        return array_values($layoutByKey);
    }
}
