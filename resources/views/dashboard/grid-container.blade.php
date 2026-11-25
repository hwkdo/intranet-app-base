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
            @if (($widget['resizable'] ?? true) === false)
                gs-no-resize="true"
            @endif
            wire:key="{{ $itemWireKeyPrefix }}-{{ $widget['key'] }}"
        >
            <div class="grid-stack-item-content h-full min-h-0 p-1">
                <livewire:dynamic-component
                    :is="$widget['component']"
                    :key="$widgetKeyPrefix.'-'.$widget['key'].'-'.$widgetRenderVersion"
                    lazy
                />
            </div>
            @unless($widget['mandatory'] ?? false)
                <div class="dashboard-widget-remove absolute end-2 top-2 z-30">
                    <flux:button
                        type="button"
                        icon="x-mark"
                        size="xs"
                        variant="ghost"
                        square
                        tooltip="Entfernen"
                        aria-label="{{ ($widget['title'] ?? '') !== '' ? $widget['title'].' entfernen' : 'Widget entfernen' }}"
                        class="text-slate-400! shadow-none! hover:bg-white/70! hover:text-[#073070]! dark:text-white/35! dark:hover:bg-white/10! dark:hover:text-white!"
                        wire:click.stop="toggleWidget('{{ $widget['key'] }}')"
                        x-on:pointerdown.stop
                    />
                </div>
            @endunless
        </div>
    @endforeach
</div>

@once
    <style>
        .dashboard-widget-remove {
            pointer-events: none;
            opacity: 0;
            transition: opacity 180ms ease;
        }

        .grid-stack-item:hover > .dashboard-widget-remove,
        .dashboard-widget-remove:focus-within {
            pointer-events: auto;
            opacity: 0.55;
        }

        .dashboard-widget-remove:focus-within,
        .grid-stack-item:hover > .dashboard-widget-remove:hover {
            opacity: 1;
        }

        @media (hover: none) {
            .dashboard-widget-remove {
                pointer-events: auto;
                opacity: 0.35;
            }
        }
    </style>
@endonce
