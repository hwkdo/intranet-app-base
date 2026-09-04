<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="subtle" square aria-label="{{ __('Search') }}" class="text-[#073070]! dark:text-white!">
            <flux:icon.magnifying-glass variant="mini" />
        </flux:button>

        <flux:popover class="flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3 p-3">
            <flux:input
                wire:model.live.debounce.300ms="searchQuery"
                placeholder="Suchen…"
                icon="magnifying-glass"
                kbd="⌘K"
            />

            @if (strlen(trim($searchQuery)) > 0 && strlen(trim($searchQuery)) < $this->minChars)
                <flux:text class="text-sm text-zinc-500">
                    Mindestens {{ $this->minChars }} Zeichen eingeben.
                </flux:text>
            @elseif (strlen(trim($searchQuery)) >= $this->minChars)
                <div wire:loading wire:target="searchQuery" class="py-2">
                    <flux:skeleton class="h-8 w-full" />
                    <flux:skeleton class="mt-2 h-8 w-full" />
                </div>

                <div wire:loading.remove wire:target="searchQuery" class="space-y-3">
                    @forelse ($this->previewResponse->groupedResults as $appIdentifier => $results)
                        <div class="space-y-1" wire:key="preview-group-{{ $appIdentifier }}">
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                {{ $results->first()->appName }}
                            </flux:text>

                            @foreach ($results as $result)
                                <a
                                    href="{{ $result->url }}"
                                    @if ($result->download) download @else wire:navigate @endif
                                    class="flex items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    wire:key="preview-result-{{ $result->sourceKey }}-{{ $result->url }}"
                                >
                                    <flux:icon :name="$result->icon" variant="mini" class="mt-0.5 shrink-0 text-zinc-500" />
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">{{ $result->title }}</div>
                                        @if ($result->subtitle)
                                            <div class="truncate text-xs text-zinc-500">{{ $result->subtitle }}</div>
                                        @endif
                                    </div>
                                </a>
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
                        >
                            Alle Ergebnisse anzeigen ({{ $this->previewResponse->totalCount }})
                        </flux:button>
                    @endif
                </div>
            @else
                <flux:text class="text-sm text-zinc-500">
                    Apps, Dokumente und Benutzer durchsuchen.
                </flux:text>
            @endif
        </flux:popover>
    </flux:dropdown>

    <flux:modal wire:model.self="showModal" class="md:max-w-lg space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Suche</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Treffer aus Apps, Dokumenten und dem Intranet.
            </flux:text>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="searchQuery"
            placeholder="Suchen…"
            icon="magnifying-glass"
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
                            <a
                                href="{{ $result->url }}"
                                @if ($result->download) download @else wire:navigate @endif
                                class="flex items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                wire:key="modal-result-{{ $result->sourceKey }}-{{ $result->url }}"
                                wire:click="closeModal"
                            >
                                <flux:icon :name="$result->icon" variant="mini" class="mt-0.5 shrink-0 text-zinc-500" />
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium">{{ $result->title }}</div>
                                    @if ($result->subtitle)
                                        <div class="truncate text-xs text-zinc-500">{{ $result->subtitle }}</div>
                                    @endif
                                </div>
                            </a>
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
