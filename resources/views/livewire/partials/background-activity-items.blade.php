<flux:accordion transition>
    @foreach ($rows as $row)
        @php($definition = $row['definition'])
        @php($cadence = $drafts[$row['wireKey']]['cadence'] ?? '')
        <flux:accordion.item wire:key="activity-{{ $row['wireKey'] }}">
            <flux:accordion.heading>
                <span class="flex w-full flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="min-w-0">{{ $definition->label }}</span>
                    @if ($definition->kind->value === 'scheduled')
                        <span class="text-sm font-normal text-zinc-500">{{ $row['frequencyLabel'] }}</span>
                        @if ($row['enabled'] === false)
                            <flux:badge size="sm" color="zinc">Aus</flux:badge>
                        @endif
                        @if ($row['overdue'])
                            <flux:badge size="sm" color="red">Überfällig</flux:badge>
                        @endif
                    @endif
                    @if ($row['lastStatus'])
                        <flux:badge size="sm" :color="$row['lastStatus'] === 'failed' ? 'red' : 'green'">
                            {{ $row['lastStatus'] === 'failed' ? 'Fehler' : 'Erfolg' }}
                            @if ($row['lastAt']) · {{ $row['lastAt'] }} @endif
                        </flux:badge>
                    @elseif ($definition->kind->value !== 'notification')
                        <flux:badge size="sm" color="zinc">Noch kein Lauf</flux:badge>
                    @endif
                    @foreach ($row['channels'] as $channel)
                        <flux:badge size="sm" :color="$channel['status'] === 'failed' ? 'red' : ($channel['status'] === 'success' ? 'green' : 'zinc')">
                            {{ match ($channel['key']) {
                                'inbox' => 'In-App',
                                'mail' => 'E-Mail',
                                'web_push' => 'Web-Push',
                                'teams' => 'Teams',
                                default => $channel['key'],
                            } }}
                            @if ($channel['status'])
                                · {{ $channel['count'] }}
                                @if ($channel['at']) · {{ $channel['at'] }} @endif
                            @else
                                · keine
                            @endif
                        </flux:badge>
                    @endforeach
                </span>
            </flux:accordion.heading>
            <flux:accordion.content>
                <div class="space-y-4 pt-3">
                    @if ($definition->description)
                        <flux:text>{{ $definition->description }}</flux:text>
                    @endif
                    <flux:text class="font-mono text-xs text-zinc-500">{{ $definition->key }}</flux:text>

                    @if ($definition->kind->value === 'scheduled')
                        <div class="flex flex-col gap-1 text-sm">
                            <span>Nächster geplanter Lauf: {{ $row['nextRun'] ?? '–' }}</span>
                            <span>Letzter Lauf: {{ $row['lastAt'] ? ($row['lastStatus'] === 'failed' ? 'Fehler' : 'Erfolg').' · '.$row['lastAt'] : 'noch keiner — der Scheduler hat den Task seit dem Monitoring nicht ausgeführt' }}</span>
                            <span class="font-mono text-zinc-500">Cron {{ $row['cron'] }}</span>
                            @if ($row['durationMs'])
                                <span>Letzte Dauer: {{ $row['durationMs'] }} ms</span>
                            @endif
                        </div>
                        @if ($row['editable'])
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <flux:switch wire:model="drafts.{{ $row['wireKey'] }}.enabled" label="Aktiv" />
                                <flux:select wire:model.live="drafts.{{ $row['wireKey'] }}.cadence" label="Kadenz">
                                    @foreach ($definition->resolvedAllowedCadences() as $option)
                                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @if ($cadence === 'every_n_minutes')
                                    <flux:input type="number" wire:model="drafts.{{ $row['wireKey'] }}.every" label="Intervall" min="1" :max="min(59, $definition->maxEvery ?? 59)" />
                                @elseif ($cadence === 'every_n_hours')
                                    <flux:input type="number" wire:model="drafts.{{ $row['wireKey'] }}.every" label="Intervall" min="1" />
                                @endif
                                @if ($cadence === 'every_n_minutes' && $definition->allowsOffset)
                                    <flux:input type="number" wire:model="drafts.{{ $row['wireKey'] }}.offset" label="Minuten-Offset" min="0" max="59" />
                                @endif
                                @if ($cadence === 'every_n_hours')
                                    <flux:input type="number" wire:model="drafts.{{ $row['wireKey'] }}.offset" label="Minute" min="0" max="59" />
                                @endif
                                @if (in_array($cadence, ['daily_at', 'weekly_on'], true))
                                    <flux:input type="time" wire:model="drafts.{{ $row['wireKey'] }}.at" label="Uhrzeit" />
                                @endif
                                @if ($cadence === 'weekly_on')
                                    <flux:select wire:model="drafts.{{ $row['wireKey'] }}.day_of_week" label="Wochentag">
                                        @foreach ($weekdays as $day)
                                            <flux:select.option value="{{ $day }}">{{ \Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency::weekdayLabel($day) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                                @foreach ($definition->parameters as $parameter)
                                    <flux:input
                                        type="number"
                                        wire:model="drafts.{{ $row['wireKey'] }}.parameters.{{ $parameter->key }}"
                                        :label="$parameter->label"
                                        :min="$parameter->min"
                                        :max="$parameter->max"
                                    />
                                @endforeach
                            </div>
                            <flux:button size="sm" variant="primary" wire:click="save('{{ $row['wireKey'] }}')">Speichern</flux:button>
                        @endif
                    @endif

                    @if ($definition->kind->value === 'notification')
                        <ul class="space-y-1 text-sm">
                            @foreach ($row['channels'] as $channel)
                                <li>
                                    {{ match ($channel['key']) {
                                        'inbox' => 'In-App',
                                        'mail' => 'E-Mail',
                                        'web_push' => 'Web-Push',
                                        'teams' => 'Teams',
                                        default => $channel['key'],
                                    } }}:
                                    @if ($channel['status'])
                                        {{ $channel['success_count'] }} erfolgreich,
                                        {{ $channel['failed_count'] }} fehlgeschlagen,
                                        zuletzt {{ $channel['at'] ?? '–' }}
                                        @if ($channel['exception']) — {{ $channel['exception'] }} @endif
                                    @else
                                        keine Zustellung. Der Kanal war bei den Empfängern nicht aktiv oder der Versand ist nicht angekommen.
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($row['lastOutput'])
                        <flux:callout heading="Ausgabe des letzten Laufs">
                            <pre class="whitespace-pre-wrap font-sans text-sm">{{ $row['lastOutput'] }}</pre>
                        </flux:callout>
                    @endif

                    @if ($row['lastException'])
                        <flux:callout variant="danger" heading="Letzter Fehler">{{ $row['lastException'] }}</flux:callout>
                    @endif
                </div>
            </flux:accordion.content>
        </flux:accordion.item>
    @endforeach
</flux:accordion>
