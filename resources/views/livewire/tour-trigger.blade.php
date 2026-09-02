<div
    wire:ignore.self
    @if($showButton)
        data-tour-trigger
        data-tour-key="{{ $tourKey }}"
        data-steps-module="{{ $stepsModule }}"
        data-tour-mandatory="{{ $mandatory ? '1' : '0' }}"
    @endif
>
    @if($showNudge && $tourTitle && ! $mandatory)
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
        const detail = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
        const tourKey = detail.tourKey ?? document.querySelector('[data-tour-trigger]')?.dataset?.tourKey;
        const stepsModule = detail.stepsModule ?? document.querySelector('[data-tour-trigger]')?.dataset?.stepsModule;

        if (! tourKey || ! stepsModule) {
            console.error('[tours] intranet-tour-start missing tourKey or stepsModule', payload);

            return;
        }

        setTimeout(() => {
            window.IntranetTours?.start({
                tourKey,
                stepsModule,
                mandatory: detail.mandatory === true || detail.mandatory === 1 || detail.mandatory === '1',
            })?.catch?.((error) => {
                console.error('[tours] start failed', error);
            });
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
