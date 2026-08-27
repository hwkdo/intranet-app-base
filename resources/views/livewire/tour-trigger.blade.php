<div
    wire:ignore.self
    @if($showButton)
        data-tour-trigger
        data-tour-key="{{ $tourKey }}"
        data-steps-module="{{ $stepsModule }}"
    @endif
>
    @if($showNudge && $tourTitle)
        <div class="fixed bottom-20 right-4 z-[9990] max-w-sm" wire:key="tour-nudge-{{ $tourKey }}">
            <flux:card class="glass-card p-4 shadow-lg">
                <flux:callout variant="secondary" icon="map">
                    <flux:callout.heading>Tour verfügbar</flux:callout.heading>
                    <flux:callout.text>
                        {{ $tourTitle }} — möchten Sie eine kurze Einführung starten?
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button size="sm" variant="primary" wire:click="startTour">
                            Tour starten
                        </flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="remindLater">
                            Später
                        </flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="dismissNudge">
                            Nicht mehr anzeigen
                        </flux:button>
                    </x-slot>
                </flux:callout>
            </flux:card>
        </div>
    @endif

    @if($showButton)
        <div class="fixed bottom-4 right-4 z-[9990]">
            <flux:button
                variant="primary"
                icon="question-mark-circle"
                wire:click="startTour"
                data-tour="tour-context-start"
            >
                Tour starten
            </flux:button>
        </div>
    @endif
</div>

@script
<script>
    window.__intranetTourTriggerWire = $wire;

    $wire.on('intranet-tour-start', (payload) => {
        const detail = Array.isArray(payload) ? payload[0] : payload;
        // Kurze Verzögerung, damit wire:navigate / Morph das Ziel-DOM bereitstellen kann
        setTimeout(() => {
            document.dispatchEvent(new CustomEvent('intranet-tour:start', {
                detail: {
                    tourKey: detail.tourKey,
                    stepsModule: detail.stepsModule,
                },
            }));
        }, 150);
    });

    if (! window.__intranetTourTriggerNavBound) {
        window.__intranetTourTriggerNavBound = true;
        document.addEventListener('livewire:navigated', () => {
            window.__intranetTourTriggerWire?.refreshForRoute?.(
                window.location.pathname + window.location.search,
            );
        });
    }
</script>
@endscript
