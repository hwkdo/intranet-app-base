@php
    $favoritedKeys = $this->favoritedKeys;
@endphp

<div
    class="relative w-full"
    x-data="{
        open: false,
        panelStyle: {},
        syncPanel() {
            const rect = this.$el.getBoundingClientRect();
            this.panelStyle = {
                position: 'fixed',
                top: `${rect.bottom + 8}px`,
                left: `${rect.left}px`,
                width: `${rect.width}px`,
                zIndex: '200',
            };
        },
        showPanel() {
            this.open = true;
            this.$nextTick(() => this.syncPanel());
        },
        hidePanel() {
            this.open = false;
        },
        maybeHidePanel() {
            setTimeout(() => {
                const active = document.activeElement;
                if (this.$el.contains(active)) {
                    return;
                }
                if (this.$refs.resultsPanel?.contains(active)) {
                    return;
                }
                this.hidePanel();
            }, 150);
        },
    }"
    @focusin="showPanel()"
    @focusout="maybeHidePanel()"
    @keydown.escape.window="hidePanel(); $wire.closeModal()"
    @keydown.window.meta.k.prevent="document.getElementById('global-search-input')?.focus(); showPanel()"
    @keydown.window.ctrl.k.prevent="document.getElementById('global-search-input')?.focus(); showPanel()"
    @resize.window="open && syncPanel()"
    @scroll.window.capture="open && syncPanel()"
>
    <flux:input
        id="global-search-input"
        wire:model.live.debounce.300ms="searchQuery"
        placeholder="Suche…"
        icon="magnifying-glass"
        kbd="⌘K"
        clearable
        class="w-full bg-white/95 shadow-sm dark:bg-zinc-800/95"
        aria-label="{{ __('Search') }}"
    />

    <template x-teleport="body">
        <div
            x-ref="resultsPanel"
            x-show="open && ! $wire.showModal"
            x-cloak
            x-bind:style="panelStyle"
            class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
            @mousedown.prevent
        >
            <div class="max-h-[min(28rem,70vh)] space-y-3 overflow-y-auto p-3">
                @if (strlen(trim($searchQuery)) > 0 && strlen(trim($searchQuery)) < $this->minChars)
                    <flux:text class="text-sm text-zinc-500">
                        Mindestens {{ $this->minChars }} Zeichen eingeben.
                    </flux:text>
                @elseif (strlen(trim($searchQuery)) >= $this->minChars)
                    <div wire:loading wire:target="searchQuery" class="space-y-2 py-1">
                        <flux:skeleton class="h-8 w-full" />
                        <flux:skeleton class="h-8 w-full" />
                    </div>

                    <div wire:loading.remove wire:target="searchQuery" class="space-y-3">
                        @forelse ($this->previewResponse->groupedResults as $appIdentifier => $results)
                            <div class="space-y-1" wire:key="preview-group-{{ $appIdentifier }}">
                                <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                    {{ $results->first()->appName }}
                                </flux:text>

                                @foreach ($results as $result)
                                    @include('intranet-app-base::livewire.partials.search-result-row', [
                                        'result' => $result,
                                        'favorited' => in_array($result->favoriteKey, $favoritedKeys, true),
                                        'wireKey' => 'preview-result-'.$result->favoriteKey,
                                        'rowClick' => 'hidePanel()',
                                    ])
                                @endforeach
                            </div>
                        @empty
                            <flux:callout variant="info" class="text-sm">
                                Keine Treffer gefunden.
                            </flux:callout>
                        @endforelse

                        @if ($this->previewResponse->totalCount > $this->previewLimit)
                            <flux:button
                                variant="ghost"
                                class="w-full justify-center"
                                wire:click="openModal"
                                @click="hidePanel()"
                            >
                                Alle Ergebnisse anzeigen ({{ $this->previewResponse->totalCount }})
                            </flux:button>
                        @endif
                    </div>
                @else
                    @if ($this->showFavoritesInEmptySearch)
                        @include('intranet-app-base::livewire.partials.search-favorites-list', [
                            'favorites' => $this->favorites,
                            'onNavigate' => 'hidePanel()',
                            'wireKeyPrefix' => 'empty-favorite',
                        ])
                    @else
                        <flux:text class="text-sm text-zinc-500">
                            Apps, Dokumente und Benutzer durchsuchen.
                        </flux:text>
                    @endif
                @endif
            </div>
        </div>
    </template>

    <flux:modal wire:model.self="showModal" class="md:max-w-lg space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Suche</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Treffer aus Apps, Dokumenten und dem Intranet.
            </flux:text>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="searchQuery"
            placeholder="Suche…"
            icon="magnifying-glass"
            clearable
            autofocus
        />

        @if (strlen(trim($searchQuery)) > 0 && strlen(trim($searchQuery)) < $this->minChars)
            <flux:text class="text-sm text-zinc-500">
                Mindestens {{ $this->minChars }} Zeichen eingeben.
            </flux:text>
        @elseif (strlen(trim($searchQuery)) >= $this->minChars)
            <div wire:loading wire:target="searchQuery" class="space-y-2 py-2">
                <flux:skeleton class="h-10 w-full" />
                <flux:skeleton class="h-10 w-full" />
                <flux:skeleton class="h-10 w-full" />
            </div>

            <div
                wire:loading.remove
                wire:target="searchQuery"
                class="max-h-[60vh] space-y-4 overflow-y-auto pe-1"
            >
                @forelse ($this->modalResponse->groupedResults as $appIdentifier => $results)
                    <div class="space-y-1" wire:key="modal-group-{{ $appIdentifier }}">
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ $results->first()->appName }}
                            <span class="font-normal normal-case tracking-normal text-zinc-400">({{ $results->count() }})</span>
                        </flux:text>

                        @foreach ($results as $result)
                            @include('intranet-app-base::livewire.partials.search-result-row', [
                                'result' => $result,
                                'favorited' => in_array($result->favoriteKey, $favoritedKeys, true),
                                'wireKey' => 'modal-result-'.$result->favoriteKey,
                                'rowClick' => null,
                                'wireClickClose' => true,
                            ])
                        @endforeach
                    </div>
                @empty
                    <flux:callout variant="info" class="text-sm">
                        Keine Treffer gefunden.
                    </flux:callout>
                @endforelse
            </div>
        @endif
    </flux:modal>
</div>
