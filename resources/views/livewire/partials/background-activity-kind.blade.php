<div class="space-y-6">
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
