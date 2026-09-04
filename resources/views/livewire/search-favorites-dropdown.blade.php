<div>
    @if ($this->isVisible)
        <flux:dropdown position="bottom" align="end">
            <flux:button variant="subtle" square aria-label="Suchfavoriten" class="text-[#073070]! dark:text-white!">
                <flux:icon name="star" variant="mini" />
            </flux:button>

            <flux:menu class="w-96 max-w-[90vw]">
                <div class="px-3 py-2">
                    <flux:heading size="sm">Favoriten</flux:heading>
                </div>

                <flux:menu.separator />

                @forelse ($this->favorites as $favorite)
                    <div
                        class="flex items-center gap-1 pe-1"
                        wire:key="header-favorite-{{ $favorite->favoriteKey }}"
                    >
                        <a
                            href="{{ $favorite->url }}"
                            @if ($favorite->download) download @else wire:navigate @endif
                            class="flex min-w-0 flex-1 items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                        >
                            <flux:icon :name="$favorite->icon" variant="mini" class="mt-0.5 shrink-0 text-zinc-500" />
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $favorite->title }}</div>
                                @if ($favorite->subtitle)
                                    <div class="truncate text-xs text-zinc-500">{{ $favorite->subtitle }}</div>
                                @endif
                            </div>
                        </a>
                        <button
                            type="button"
                            class="shrink-0 rounded-md p-1.5 text-amber-500 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            wire:click="removeFavorite(@js($favorite->favoriteKey))"
                            aria-label="Favorit entfernen"
                        >
                            <flux:icon name="star" variant="solid" class="size-4" />
                        </button>
                    </div>
                @empty
                    <div class="px-3 py-4 text-sm text-zinc-500">
                        Noch keine Favoriten. Stern bei einem Suchtreffer tippen.
                    </div>
                @endforelse
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
