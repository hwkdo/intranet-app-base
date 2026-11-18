<div>
    @if($this->isAppScoped())
        <div class="max-w-2xl">
            <flux:card class="glass-card">
                @if($this->appsGroupedTypes->isEmpty())
                    <flux:callout variant="warning" icon="bell">
                        <flux:callout.heading>Keine Benachrichtigungstypen</flux:callout.heading>
                        <flux:callout.text>
                            Für diese App sind keine konfigurierbaren Benachrichtigungstypen hinterlegt.
                        </flux:callout.text>
                        <div class="mt-3">
                            <flux:button
                                href="{{ route('settings.notifications') }}"
                                size="sm"
                                variant="ghost"
                                icon="arrow-top-right-on-square"
                            >
                                Alle Benachrichtigungen
                            </flux:button>
                        </div>
                    </flux:callout>
                @else
                    <div class="space-y-3">
                        @foreach($this->appsGroupedTypes as $scopedAppIdentifier => $types)
                            @foreach($types as $definition)
                                @include('intranet-app-base::livewire.partials.notification-type-preference', [
                                    'definition' => $definition,
                                    'showCentralPushHint' => true,
                                ])
                            @endforeach
                        @endforeach
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-zinc-200/80 pt-4 dark:border-zinc-700/80">
                        <flux:button wire:click="save" variant="primary">
                            Speichern
                        </flux:button>
                        <flux:button
                            href="{{ route('settings.notifications') }}"
                            size="sm"
                            variant="ghost"
                            icon="arrow-top-right-on-square"
                        >
                            Alle Benachrichtigungen
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        </div>
    @else
        <div class="space-y-4">
            @php
                $notificationTabs = [
                    ['name' => 'apps', 'label' => 'Apps', 'icon' => 'squares-2x2'],
                    ['name' => 'news', 'label' => 'News', 'icon' => 'newspaper'],
                    ['name' => 'settings', 'label' => 'Einstellungen', 'icon' => 'cog'],
                ];
            @endphp

            <div class="glass-card sticky top-3 z-20 p-3 shadow-lg shadow-[#073070]/10" style="--glass-opacity: 0.92">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <flux:input
                            type="search"
                            wire:model.live.debounce.300ms="searchTerm"
                            placeholder="Suchen (z. B. Fuhrpark, Bestellung, News)"
                            icon="magnifying-glass"
                            aria-label="Benachrichtigungen durchsuchen"
                            clearable
                        />
                    </div>

                    <span class="w-fit rounded-full bg-[#073070] px-3 py-1 text-xs font-semibold text-white">
                        {{ $this->filteredGroupedTypes->flatten()->count() }} Treffer
                    </span>
                </div>

                <div class="mt-2.5 flex flex-wrap gap-1.5 border-t border-[#d0e3f9] pt-2.5 dark:border-white/10" role="tablist" aria-label="Bereiche">
                    @foreach ($notificationTabs as $tab)
                        <button
                            type="button"
                            id="notification-tab-{{ $tab['name'] }}"
                            wire:click="$set('activeTab', '{{ $tab['name'] }}')"
                            wire:key="notification-tab-{{ $tab['name'] }}"
                            role="tab"
                            aria-controls="notification-panel-{{ $tab['name'] }}"
                            aria-selected="{{ $activeTab === $tab['name'] ? 'true' : 'false' }}"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500',
                                'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $activeTab === $tab['name'],
                                'bg-white/80 text-[#073070] ring-1 ring-[#d0e3f9] hover:bg-[#073070] hover:text-white dark:bg-white/10 dark:text-white dark:ring-white/15 dark:hover:bg-white dark:hover:text-[#073070]' => $activeTab !== $tab['name'],
                            ])
                        >
                            <flux:icon :icon="$tab['icon']" variant="micro" class="size-4" />
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($activeTab === 'apps')
                <div id="notification-panel-apps" role="tabpanel" aria-labelledby="notification-tab-apps">
                    @if($this->appsGroupedTypes->isEmpty())
                        <flux:callout variant="warning" icon="layout-grid" class="p-4">
                            Keine App-Benachrichtigungstypen passend zu <code>{{ $this->searchTerm }}</code>.
                        </flux:callout>
                    @else
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            @foreach($this->appsGroupedTypes as $appIdentifier => $types)
                                <flux:card class="glass-card p-3">
                                    <flux:heading size="md" class="mb-3">
                                        {{ $types->first()->appName }}
                                    </flux:heading>

                                    <div class="space-y-3">
                                        @foreach($types as $definition)
                                            @include('intranet-app-base::livewire.partials.notification-type-preference', [
                                                'definition' => $definition,
                                            ])
                                        @endforeach
                                    </div>
                                </flux:card>
                            @endforeach
                        </div>
                    @endif
                </div>
            @elseif ($activeTab === 'news')
                <div id="notification-panel-news" role="tabpanel" aria-labelledby="notification-tab-news">
                    @if($this->newsDefinitions->isEmpty())
                        <div class="glass-card flex flex-col items-center gap-3 px-6 py-14 text-center">
                            <span class="grid size-12 place-items-center rounded-2xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                <flux:icon icon="newspaper" class="size-6" />
                            </span>
                            <p class="text-base font-semibold text-[#073070] dark:text-white">
                                {{ trim($this->searchTerm) !== '' ? 'Keine Treffer' : 'Keine News-Kategorien' }}
                            </p>
                            <p class="max-w-md text-sm text-slate-600 dark:text-white/70">
                                @if(trim($this->searchTerm) !== '')
                                    Für „{{ $this->searchTerm }}“ wurde keine News-Kategorie gefunden.
                                @else
                                    Derzeit sind keine News-Kategorien für Benachrichtigungen hinterlegt.
                                @endif
                            </p>
                        </div>
                    @else
                        <section class="glass-card overflow-hidden">
                            <div class="flex items-center gap-3 border-b border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon icon="newspaper" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-[#073070] dark:text-white">News-Kategorien</h3>
                                    <p class="text-xs text-slate-600 dark:text-white/70">Neue Beiträge nach Kategorie und Kanal.</p>
                                </div>
                            </div>

                            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                                @foreach($this->newsDefinitions as $definition)
                                    @include('intranet-app-base::livewire.partials.notification-type-preference', [
                                        'definition' => $definition,
                                        'layout' => 'row',
                                        'compact' => true,
                                    ])
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @else
                <div id="notification-panel-settings" role="tabpanel" aria-labelledby="notification-tab-settings" class="space-y-4">
                    <flux:card class="glass-card">
                        <flux:heading size="md" class="mb-2">Microsoft Teams</flux:heading>
                        <flux:text class="mb-4 text-sm text-zinc-500">
                            Benachrichtigungen über Teams laufen als 1:1-Bot-Konversation. Dafür muss der Teams-Bot für Ihr Konto installiert und aktiv sein.
                        </flux:text>

                        @if(! $this->teamsBotEnabled)
                            <flux:callout variant="warning" icon="exclamation-triangle">
                                Der Teams-Bot ist serverseitig deaktiviert.
                            </flux:callout>
                        @elseif(! $this->teamsHasMicrosoftLogin)
                            <flux:callout variant="warning" icon="exclamation-triangle">
                                Bitte melden Sie sich einmal mit Microsoft an, damit Teams eingerichtet werden kann.
                            </flux:callout>
                        @elseif($this->teamsAvailable)
                            <flux:callout variant="success" icon="check-circle">
                                Teams-Bot ist aktiv. Sie können den Kanal „Teams“ bei den Benachrichtigungstypen einschalten.
                            </flux:callout>
                        @else
                            <flux:callout variant="warning" icon="chat-bubble-left-right" class="mb-4">
                                Teams ist noch nicht eingerichtet. Klicken Sie auf „Teams einrichten“, um den Bot für Ihr Konto zu installieren.
                            </flux:callout>
                            <flux:button
                                type="button"
                                wire:click="setupTeamsBot"
                                wire:loading.attr="disabled"
                                icon="chat-bubble-left-right"
                            >
                                Teams einrichten
                            </flux:button>
                        @endif
                    </flux:card>

                    <flux:card class="glass-card">
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

                    <flux:card class="glass-card">
                        <flux:heading size="md" class="mb-2">Testbenachrichtigung</flux:heading>
                        <flux:text class="mb-4 text-sm text-zinc-500">
                            Senden Sie eine Testbenachrichtigung an sich selbst, um die Zustellung über einzelne Kanäle zu prüfen.
                        </flux:text>

                        <div class="flex flex-wrap gap-3">
                            @foreach(\Hwkdo\IntranetAppBase\Enums\NotificationChannelKey::cases() as $channel)
                                @php
                                    $testDisabled = $this->channelDisabledForUser($channel->value);
                                    $testDisabledReason = $testDisabled
                                        ? $this->channelDisabledReason($channel->value)
                                        : null;
                                @endphp
                                @if($testDisabled && $testDisabledReason)
                                    <flux:tooltip position="top">
                                        <span class="inline-flex cursor-help">
                                            <flux:button
                                                size="sm"
                                                variant="outline"
                                                :disabled="true"
                                                icon="paper-airplane"
                                            >
                                                {{ $channel->label() }}
                                            </flux:button>
                                        </span>
                                        <flux:tooltip.content class="max-w-xs">
                                            {{ $testDisabledReason }}
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        wire:click="sendTestNotification('{{ $channel->value }}')"
                                        icon="paper-airplane"
                                    >
                                        {{ $channel->label() }}
                                    </flux:button>
                                @endif
                            @endforeach
                        </div>
                    </flux:card>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button wire:click="save" variant="primary">Speichern</flux:button>
            </div>
        </div>
    @endif
</div>

@unless($this->isAppScoped())
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
@endunless
