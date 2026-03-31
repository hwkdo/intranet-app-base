@props([
    'modalName' => 'dashboard-widgets-flyout',
    'sections' => [],
    'enabledWidgets' => [],
    'descriptionText' => 'Widgets aktivieren/deaktivieren und das Dashboard auf den Standard zurücksetzen.',
    'toggleMethod' => 'toggleWidget',
    'supportsItemCountMethod' => 'supportsWidgetItemCount',
    'itemCountValueMethod' => 'widgetItemCountValue',
    'saveItemCountMethod' => 'saveWidgetItemCount',
    'resetMethod' => 'resetToDefault',
    'showMandatory' => false,
])

<flux:modal.trigger :name="$modalName">
    <flux:button variant="ghost" icon="squares-plus" icon-trailing="chevron-down">Widgets</flux:button>
</flux:modal.trigger>

<flux:modal :name="$modalName" variant="flyout" class="md:max-w-lg">
    <div class="space-y-5">
        <div class="space-y-1">
            <flux:heading size="lg">Widgets</flux:heading>
            <flux:text class="text-zinc-500">{{ $descriptionText }}</flux:text>
        </div>

        <div class="space-y-4">
            @foreach($sections as $section)
                <div class="space-y-2">
                    <flux:heading size="sm">{{ $section['label'] }}</flux:heading>
                    <div class="space-y-1">
                        @foreach($section['widgets'] as $widget)
                            <div class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-left hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-70"
                                    wire:click="{{ $toggleMethod }}('{{ $widget['key'] }}')"
                                    @disabled(($showMandatory === true) && ($widget['mandatory'] ?? false))
                                >
                                    <span class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $widget['title'] }}</span>
                                    @if(! empty($widget['description']))
                                        <span class="mt-0.5 block text-xs text-zinc-500 dark:text-white">{{ $widget['description'] }}</span>
                                    @endif
                                </button>

                                <span class="shrink-0 flex items-center gap-2">
                                    @if($this->{$supportsItemCountMethod}($widget['key']))
                                        <span class="w-24">
                                            <flux:input
                                                type="number"
                                                min="1"
                                                max="30"
                                                size="sm"
                                                :value="$this->{$itemCountValueMethod}($widget['key'])"
                                                wire:change="{{ $saveItemCountMethod }}('{{ $widget['key'] }}', $event.target.value)"
                                            />
                                        </span>
                                    @endif

                                    @if(($showMandatory === true) && ($widget['mandatory'] ?? false))
                                        <flux:badge size="sm" color="zinc">Pflicht</flux:badge>
                                    @elseif(in_array($widget['key'], $enabledWidgets, true))
                                        <flux:icon name="check-circle" class="size-5 text-green-600" />
                                    @else
                                        <flux:icon name="minus-circle" class="size-5 text-zinc-400" />
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap justify-between gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button
                variant="danger"
                icon="arrow-path"
                wire:click="{{ $resetMethod }}"
                wire:confirm="Dashboard wirklich auf Standard zurücksetzen?"
            >
                Zurücksetzen auf Standard
            </flux:button>
            <flux:modal.close>
                <flux:button variant="ghost">Schließen</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
