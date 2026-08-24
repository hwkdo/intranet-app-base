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
                        <div class="space-y-1">
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                {{ $results->first()->appName }}
                            </flux:text>

                            @foreach ($results as $result)
                                <a
                                    href="{{ $result->url }}"
                                    wire:navigate
                                    class="flex items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800"
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

    <flux:modal wire:model="showModal" variant="bare" class="w-full max-w-[30rem] my-[12vh] max-h-screen overflow-y-hidden">
        <flux:command class="inline-flex max-h-[76vh] flex-col border-none shadow-lg">
            <flux:command.input
                placeholder="Suchen…"
                closable
                wire:model.live.debounce.300ms="searchQuery"
            />

            <flux:command.items>
                @if (strlen(trim($searchQuery)) >= $this->minChars)
                    @forelse ($this->modalResponse->results as $result)
                        <flux:command.item
                            :href="$result->url"
                            wire:navigate
                            :icon="$result->icon"
                        >
                            <div class="min-w-0">
                                <div class="truncate">{{ $result->title }}</div>
                                @if ($result->subtitle)
                                    <div class="truncate text-xs text-zinc-500">{{ $result->subtitle }}</div>
                                @endif
                            </div>
                        </flux:command.item>
                    @empty
                        <div class="px-3 py-4 text-sm text-zinc-500">Keine Treffer gefunden.</div>
                    @endforelse
                @else
                    <div class="px-3 py-4 text-sm text-zinc-500">
                        Mindestens {{ $this->minChars }} Zeichen eingeben.
                    </div>
                @endif
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
