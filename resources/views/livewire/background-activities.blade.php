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
        </flux:tab.group>
    </div>

    @if ($kindFilter === 'notification' && $failedNotifications !== [])
        <flux:callout variant="danger" icon="exclamation-triangle" heading="Fehlgeschlagene Zustellungen">
            <ul class="space-y-1 text-sm">
                @foreach ($failedNotifications as $failed)
                    <li wire:key="failed-{{ $failed['key'] }}-{{ $failed['channel'] }}">
                        {{ $failed['key'] }} · {{ $failed['channel'] }}
                        @if ($failed['at']) · {{ $failed['at'] }} @endif
                        @if ($failed['exception']) — {{ $failed['exception'] }} @endif
                    </li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    @if ($groups === [])
        <flux:text>Keine Einträge für diese Auswahl.</flux:text>
    @elseif ($showAppGroups)
        <flux:accordion transition>
            @foreach ($groups as $appIdentifier => $group)
                <flux:accordion.item wire:key="app-{{ $appIdentifier }}-{{ $kindFilter }}">
                    <flux:accordion.heading>
                        <span class="flex w-full flex-wrap items-center gap-2">
                            <span>{{ $group['name'] }}</span>
                            <flux:badge size="sm" color="zinc">{{ count($group['rows']) }}</flux:badge>
                        </span>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @include('intranet-app-base::livewire.partials.background-activity-items', ['rows' => $group['rows'], 'weekdays' => $weekdays])
                    </flux:accordion.content>
                </flux:accordion.item>
            @endforeach
        </flux:accordion>
    @else
        @include('intranet-app-base::livewire.partials.background-activity-items', ['rows' => reset($groups)['rows'], 'weekdays' => $weekdays])
    @endif

    @if ($kindFilter === 'mail' && $uncataloguedMails !== [])
        <flux:accordion transition>
            <flux:accordion.item>
                <flux:accordion.heading>
                    <span class="flex w-full items-center gap-2">
                        <span>Sonstige Mail</span>
                        <flux:badge size="sm" color="zinc">{{ count($uncataloguedMails) }}</flux:badge>
                    </span>
                </flux:accordion.heading>
                <flux:accordion.content>
                    <ul class="space-y-2">
                        @foreach ($uncataloguedMails as $mail)
                            <li wire:key="uncatalogued-{{ $mail['key'] }}" class="text-sm">
                                <span class="font-mono text-xs">{{ $mail['key'] }}</span>
                                · {{ $mail['status'] === 'failed' ? 'Fehler' : 'Erfolg' }}
                                @if ($mail['at']) · {{ $mail['at'] }} @endif
                            </li>
                        @endforeach
                    </ul>
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    @endif
</div>
