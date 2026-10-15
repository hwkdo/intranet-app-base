<div class="space-y-6">
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Name, Schlüssel oder Befehl suchen…"
                icon="magnifying-glass"
                clearable
                class="w-full max-w-md"
            />
            <flux:button href="{{ url('/horizon') }}" variant="ghost" icon="queue-list" size="sm">Horizon</flux:button>
        </div>

        <flux:tab.group>
            <flux:tabs wire:model.live="kindFilter" variant="segmented" scrollable>
                <flux:tab name="scheduled" icon="clock">Geplant ({{ $counts['scheduled'] }})</flux:tab>
                <flux:tab name="queued" icon="queue-list">Queue ({{ $counts['queued'] }})</flux:tab>
                <flux:tab name="mail" icon="envelope">Mail ({{ $counts['mail'] }})</flux:tab>
                <flux:tab name="notification" icon="bell">Benachrichtigungen ({{ $counts['notification'] }})</flux:tab>
            </flux:tabs>

            @foreach (['scheduled', 'queued', 'mail', 'notification'] as $kind)
                <flux:tab.panel :name="$kind" :selected="$kindFilter === $kind">
                    @if ($kindFilter === $kind)
                        @include('intranet-app-base::livewire.partials.background-activity-kind')
                    @endif
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    </div>
</div>
