@props([
    'gridElementId' => 'dashboard-grid',
    'gridWireKeyPrefix' => 'dashboard-grid',
    'itemWireKeyPrefix' => 'dashboard-item',
    'widgetKeyPrefix' => 'dashboard-widget',
    'enabledWidgets' => [],
    'layout' => [],
    'widgets' => [],
    'widgetRenderVersion' => 1,
])

<div
    class="grid-stack"
    id="{{ $gridElementId }}"
    wire:key="{{ $gridWireKeyPrefix }}-{{ md5(json_encode($enabledWidgets)) }}"
>
    @foreach($widgets as $widget)
        @php
            $layoutItem = collect($layout)->firstWhere('widgetKey', $widget['key']) ?? [
                'x' => 0,
                'y' => 0,
                'w' => $widget['defaultW'],
                'h' => $widget['defaultH'],
            ];
        @endphp
        <div
            class="grid-stack-item"
            gs-id="{{ $widget['key'] }}"
            gs-x="{{ $layoutItem['x'] }}"
            gs-y="{{ $layoutItem['y'] }}"
            gs-w="{{ $layoutItem['w'] }}"
            gs-h="{{ $layoutItem['h'] }}"
            gs-min-w="{{ $widget['minW'] }}"
            gs-min-h="{{ $widget['minH'] }}"
            wire:key="{{ $itemWireKeyPrefix }}-{{ $widget['key'] }}"
        >
            <div class="grid-stack-item-content h-full min-h-0 p-1">
                <livewire:dynamic-component
                    :is="$widget['component']"
                    :key="$widgetKeyPrefix.'-'.$widget['key'].'-'.$widgetRenderVersion"
                    lazy
                />
            </div>
        </div>
    @endforeach
</div>
