<div>
    <flux:heading size="lg">Benachrichtigungen</flux:heading>
    <flux:subheading class="mb-6">
        Wählen Sie, welche Benachrichtigungen Sie über welche Kanäle erhalten möchten.
    </flux:subheading>

    <div class="space-y-4">
        <flux:card class="p-3">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="flex-1">
                    <flux:input
                        type="search"
                        wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Suchen (z. B. Fuhrpark, Bestellung, News)"
                        icon="magnifying-glass"
                        size="sm"
                    />
                </div>

                <flux:text class="text-sm text-zinc-500">
                    {{ $this->filteredGroupedTypes->flatten()->count() }} Treffer
                </flux:text>
            </div>

            @if(trim($this->searchTerm) !== '')
                <div class="mt-2">
                    <flux:button type="button" size="xs" variant="ghost" wire:click="$set('searchTerm', '')">
                        Suche zurücksetzen
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <flux:tab.group>
            <flux:tabs wire:model="activeTab" class="mb-4">
                <flux:tab name="apps">
                    <span class="inline-flex items-center gap-2">
                        <flux:icon icon="layout-grid" class="size-4" />
                        Apps
                    </span>
                </flux:tab>
                <flux:tab name="news">
                    <span class="inline-flex items-center gap-2">
                        <flux:icon icon="newspaper" class="size-4" />
                        News
                    </span>
                </flux:tab>
                <flux:tab name="settings">
                    <span class="inline-flex items-center gap-2">
                        <flux:icon icon="cog" class="size-4" />
                        Einstellungen
                    </span>
                </flux:tab>
            </flux:tabs>

            <flux:tab.panel name="apps">
                @if($this->appsGroupedTypes->isEmpty())
                    <flux:callout variant="warning" icon="layout-grid" class="p-4">
                        Keine App-Benachrichtigungstypen passend zu <code>{{ $this->searchTerm }}</code>.
                    </flux:callout>
                @else
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach($this->appsGroupedTypes as $appIdentifier => $types)
                            <flux:card class="p-3">
                                <flux:heading size="md" class="mb-3">
                                    {{ $types->first()->appName }}
                                </flux:heading>

                                <div class="space-y-3">
                                    @foreach($types as $definition)
                                        @php
                                            $pref = $preferences[$definition->key] ?? ['enabled' => true, 'channels' => []];
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
                                                        wire:model.live="preferences.{{ $definition->key }}.enabled"
                                                        label="Aktiv"
                                                    />
                                                @endunless
                                            </div>

                                            @if($definition->mandatory || ($pref['enabled'] ?? true))
                                                <div class="mt-3 flex flex-wrap gap-3">
                                                    @foreach($definition->resolvedAvailableChannels() as $channelKey)
                                                        @php
                                                            $checked = in_array($channelKey, $pref['channels'] ?? [], true);
                                                            $disabled = $this->channelDisabledForUser($channelKey);
                                                        @endphp
                                                        <flux:checkbox
                                                            wire:click="toggleChannel('{{ $definition->key }}', '{{ $channelKey }}')"
                                                            :checked="$checked"
                                                            :disabled="$disabled"
                                                            label="{{ $this->channelOptions[$channelKey] ?? $channelKey }}"
                                                        />
                                                    @endforeach
                                                </div>

                                                @if(in_array('web_push', $pref['channels'] ?? [], true) && ! $this->webPushConfigured)
                                                    <flux:callout variant="warning" class="mt-2" icon="exclamation-triangle">
                                                        Web-Push ist serverseitig noch nicht konfiguriert (VAPID-Schlüssel fehlen).
                                                    </flux:callout>
                                                @elseif(in_array('web_push', $pref['channels'] ?? [], true) && $this->pushSubscriptions->isEmpty())
                                                    <flux:callout variant="warning" class="mt-2" icon="bell-alert">
                                                        Web-Push ist gewählt, aber für dieses Konto ist kein Browser registriert.
                                                    </flux:callout>
                                                @endif

                                                @if(in_array('teams', $pref['channels'] ?? [], true) && ! $this->teamsAvailable)
                                                    <flux:callout variant="warning" class="mt-2" icon="chat-bubble-left-right">
                                                        Teams ist nicht verfügbar (Microsoft-Anmeldung oder Activity Feed fehlt).
                                                    </flux:callout>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            </flux:tab.panel>

            <flux:tab.panel name="news">
                @if($this->newsDefinitions->isEmpty())
                    <flux:callout variant="warning" icon="newspaper" class="p-4">
                        Keine News-Benachrichtigungstypen passend zu <code>{{ $this->searchTerm }}</code>.
                    </flux:callout>
                @else
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach($this->newsDefinitions as $definition)
                            @php
                                $pref = $preferences[$definition->key] ?? ['enabled' => true, 'channels' => []];
                            @endphp
                            <flux:card class="p-3">
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
                                            wire:model.live="preferences.{{ $definition->key }}.enabled"
                                            label="Aktiv"
                                        />
                                    @endunless
                                </div>

                                @if($definition->mandatory || ($pref['enabled'] ?? true))
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @foreach($definition->resolvedAvailableChannels() as $channelKey)
                                            @php
                                                $checked = in_array($channelKey, $pref['channels'] ?? [], true);
                                                $disabled = $this->channelDisabledForUser($channelKey);
                                            @endphp
                                            <flux:checkbox
                                                wire:click="toggleChannel('{{ $definition->key }}', '{{ $channelKey }}')"
                                                :checked="$checked"
                                                :disabled="$disabled"
                                                label="{{ $this->channelOptions[$channelKey] ?? $channelKey }}"
                                            />
                                        @endforeach
                                    </div>

                                    @if(in_array('web_push', $pref['channels'] ?? [], true) && ! $this->webPushConfigured)
                                        <flux:callout variant="warning" class="mt-2" icon="exclamation-triangle">
                                            Web-Push ist serverseitig noch nicht konfiguriert (VAPID-Schlüssel fehlen).
                                        </flux:callout>
                                    @elseif(in_array('web_push', $pref['channels'] ?? [], true) && $this->pushSubscriptions->isEmpty())
                                        <flux:callout variant="warning" class="mt-2" icon="bell-alert">
                                            Web-Push ist gewählt, aber für dieses Konto ist kein Browser registriert.
                                        </flux:callout>
                                    @endif

                                    @if(in_array('teams', $pref['channels'] ?? [], true) && ! $this->teamsAvailable)
                                        <flux:callout variant="warning" class="mt-2" icon="chat-bubble-left-right">
                                            Teams ist nicht verfügbar (Microsoft-Anmeldung oder Activity Feed fehlt).
                                        </flux:callout>
                                    @endif
                                @endif
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            </flux:tab.panel>

            <flux:tab.panel name="settings">
                <flux:card class="mb-4">
                    <flux:heading size="md" class="mb-2">Web-Push-Geräte</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">
                        Registrieren Sie diesen Browser, um Push-Benachrichtigungen auch außerhalb des Intranets zu erhalten.
                    </flux:text>

                    @if($this->vapidPublicKey)
                        <div
                            x-data="webPushManager(@js($this->vapidPublicKey))"
                            class="space-y-4"
                        >
                            <flux:button type="button" x-on:click="subscribe()" icon="bell">
                                In diesem Browser aktivieren
                            </flux:button>

                            @if($this->pushSubscriptions->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($this->pushSubscriptions as $subscription)
                                        <div class="flex items-center justify-between gap-3 rounded border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                            <flux:text class="truncate text-sm">
                                                {{ \Illuminate\Support\Str::limit($subscription->endpoint, 80) }}
                                            </flux:text>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="deletePushSubscription(@js($subscription->endpoint))"
                                                icon="trash"
                                            >
                                                Entfernen
                                            </flux:button>
                                        </div>
                                    @endforeach

                                    <flux:button
                                        variant="danger"
                                        size="sm"
                                        wire:click="deleteAllPushSubscriptions"
                                        wire:confirm="Alle Web-Push-Geräte wirklich entfernen?"
                                    >
                                        Alle Geräte entfernen
                                    </flux:button>
                                </div>
                            @else
                                <flux:text class="text-sm text-zinc-500">Noch kein Browser registriert.</flux:text>
                            @endif
                        </div>
                    @else
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            VAPID-Schlüssel fehlen. Bitte <code>php artisan webpush:vapid</code> ausführen.
                        </flux:callout>
                    @endif
                </flux:card>

                <flux:card>
                    <flux:heading size="md" class="mb-2">Testbenachrichtigung</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">
                        Senden Sie eine Testbenachrichtigung an sich selbst, um die Zustellung über einzelne Kanäle zu prüfen.
                    </flux:text>

                    <div class="flex flex-wrap gap-3">
                        @foreach(\Hwkdo\IntranetAppBase\Enums\NotificationChannelKey::cases() as $channel)
                            <flux:button
                                size="sm"
                                variant="outline"
                                wire:click="sendTestNotification('{{ $channel->value }}')"
                                :disabled="$this->channelDisabledForUser($channel->value)"
                                icon="paper-airplane"
                            >
                                {{ $channel->label() }}
                            </flux:button>
                        @endforeach
                    </div>
                </flux:card>
            </flux:tab.panel>
        </flux:tab.group>

        <div class="flex justify-end">
            <flux:button wire:click="save" variant="primary">Speichern</flux:button>
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('webPushManager', (vapidPublicKey) => ({
        async subscribe() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('Web-Push wird von diesem Browser nicht unterstützt.');
                return;
            }

            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                alert('Benachrichtigungen wurden nicht erlaubt.');
                return;
            }

            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
            });

            const json = subscription.toJSON();

            $wire.registerPushSubscription(
                json.endpoint,
                json.keys?.p256dh ?? null,
                json.keys?.auth ?? null,
                subscription.options?.applicationServerKey ? 'aesgcm' : null,
            );
        },

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }

            return outputArray;
        },
    }));
</script>
@endscript
