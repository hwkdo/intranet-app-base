<div class="space-y-6">
    @if($definition)
        <div class="prose prose-sm dark:prose-invert max-w-none">
            @include($definition->contentView, ['manualKey' => $definition->key])
        </div>

        @if($relatedTour)
            <flux:separator />
            <flux:callout variant="secondary" icon="map">
                <flux:callout.heading>Interaktive Tour</flux:callout.heading>
                <flux:callout.text>
                    Möchten Sie die Funktionen live ausprobieren? Starten Sie die Tour „{{ $relatedTour->title }}“.
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" variant="primary" wire:click="startRelatedTour">
                        Tour starten
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        href="{{ route('hilfe.tours') }}"
                        wire:navigate
                    >
                        Alle Touren
                    </flux:button>
                </x-slot>
            </flux:callout>
        @endif
    @endif
</div>
