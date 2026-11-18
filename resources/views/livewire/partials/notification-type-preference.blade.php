@php
    /** @var \Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition $definition */
    $wireKey = \Hwkdo\IntranetAppBase\Livewire\NotificationSettings::toWireKey($definition->key);
    $pref = $preferences[$wireKey] ?? ['enabled' => true, 'channels' => []];
    $showCentralPushHint = $showCentralPushHint ?? false;
    $layout = $layout ?? 'card';
    $compact = $compact ?? false;
    $isRow = $layout === 'row';
    $displayLabel = $definition->label;

    if ($compact && str_starts_with($displayLabel, 'Neue News: ')) {
        $displayLabel = mb_substr($displayLabel, mb_strlen('Neue News: '));
    }

    $description = $definition->description;

    if ($compact && ! $definition->mandatory) {
        $description = null;
    }
@endphp

<div @class([
    'rounded-lg border border-zinc-200 p-3 dark:border-zinc-700' => ! $isRow,
    'py-4' => $isRow,
]) wire:key="notification-pref-{{ $wireKey }}">
    <div @class([
        'flex flex-wrap items-start justify-between gap-2' => ! $isRow,
        'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-8' => $isRow,
    ])>
        <div class="min-w-0 flex-1">
            @if ($isRow)
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium text-[#073070] dark:text-white">{{ $displayLabel }}</p>
                    @if($definition->mandatory)
                        <flux:badge size="sm" color="amber">Pflicht</flux:badge>
                    @endif
                </div>
                @if(filled($description))
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-white/70">{{ $description }}</p>
                @endif
            @else
                <flux:heading size="sm">{{ $displayLabel }}</flux:heading>
                @if(filled($description))
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ $description }}</flux:text>
                @endif
                @if($definition->mandatory)
                    <flux:badge size="sm" color="amber" class="mt-2">Pflicht</flux:badge>
                @endif
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
        <div @class([
            'mt-3 flex flex-wrap gap-3' => ! $isRow,
            'mt-3 flex flex-wrap gap-x-4 gap-y-2' => $isRow,
        ])>
            @foreach($definition->resolvedAvailableChannels() as $channelKey)
                @php
                    $checked = in_array($channelKey, $pref['channels'] ?? [], true);
                    $unavailable = $this->channelDisabledForUser($channelKey);
                    $required = $definition->channelIsRequired($channelKey);
                    $disabled = $required ? $checked : ($unavailable && ! $checked);
                    $disabledReason = $required
                        ? 'Dieser Kanal ist verpflichtend und kann nicht abgewählt werden.'
                        : ($unavailable ? $this->channelDisabledReason($channelKey) : null);
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
