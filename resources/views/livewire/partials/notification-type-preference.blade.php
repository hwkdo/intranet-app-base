@php
    /** @var \Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition $definition */
    $wireKey = \Hwkdo\IntranetAppBase\Livewire\NotificationSettings::toWireKey($definition->key);
    $pref = $preferences[$wireKey] ?? ['enabled' => true, 'channels' => []];
    $showCentralPushHint = $showCentralPushHint ?? false;
@endphp

<div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <flux:heading size="sm">{{ $definition->label }}</flux:heading>
            @if($definition->description)
                <flux:text class="mt-1 text-sm text-zinc-500">{{ $definition->description }}</flux:text>
            @endif
            @if($definition->mandatory)
                <flux:badge size="sm" color="amber" class="mt-2">Pflicht</flux:badge>
            @endif
        </div>

        @unless($definition->mandatory)
            <flux:switch
                wire:model.live="preferences.{{ $wireKey }}.enabled"
                label="Aktiv"
            />
        @endunless
    </div>

    @if($definition->mandatory || ($pref['enabled'] ?? true))
        <div class="mt-3 flex flex-wrap gap-3">
            @foreach($definition->resolvedAvailableChannels() as $channelKey)
                @php
                    $checked = in_array($channelKey, $pref['channels'] ?? [], true);
                    $unavailable = $this->channelDisabledForUser($channelKey);
                    $disabled = $unavailable && ! $checked;
                    $disabledReason = $unavailable ? $this->channelDisabledReason($channelKey) : null;
                    $channelLabel = $this->channelOptions[$channelKey] ?? $channelKey;
                @endphp
                @if($disabledReason)
                    <flux:tooltip position="top">
                        <span class="inline-flex {{ $disabled ? 'cursor-help' : '' }}">
                            <flux:checkbox
                                wire:click="toggleChannel('{{ $definition->key }}', '{{ $channelKey }}')"
                                :checked="$checked"
                                :disabled="$disabled"
                                label="{{ $channelLabel }}"
                            />
                        </span>
                        <flux:tooltip.content class="max-w-xs">
                            {{ $disabledReason }}
                        </flux:tooltip.content>
                    </flux:tooltip>
                @else
                    <flux:checkbox
                        wire:click="toggleChannel('{{ $definition->key }}', '{{ $channelKey }}')"
                        :checked="$checked"
                        :disabled="false"
                        label="{{ $channelLabel }}"
                    />
                @endif
            @endforeach
        </div>

        @if(in_array('web_push', $pref['channels'] ?? [], true) && ! $this->webPushConfigured)
            <flux:callout variant="warning" class="mt-2" icon="exclamation-triangle">
                Web-Push ist serverseitig noch nicht konfiguriert (VAPID-Schlüssel fehlen).
            </flux:callout>
        @elseif(in_array('web_push', $pref['channels'] ?? [], true) && $this->pushSubscriptions->isEmpty())
            <flux:callout variant="warning" class="mt-2" icon="bell-alert">
                Web-Push ist gewählt, aber für dieses Konto ist kein Browser registriert.
                @if($showCentralPushHint)
                    Geräte können unter
                    <a href="{{ route('settings.notifications', ['tab' => 'settings']) }}" class="underline">Benachrichtigungen</a>
                    registriert werden.
                @endif
            </flux:callout>
        @endif

        @if(in_array('teams', $pref['channels'] ?? [], true) && ! $this->teamsAvailable)
            <flux:callout variant="warning" class="mt-2" icon="chat-bubble-left-right">
                Teams ist noch nicht bereit (aktive Bot-Konversation fehlt).
                @unless($this->isAppScoped())
                    Bitte unter
                    <a href="{{ route('settings.notifications', ['tab' => 'settings']) }}" class="underline">Einstellungen</a>
                    „Teams einrichten“ ausführen.
                @endunless
            </flux:callout>
        @endif
    @endif
</div>
