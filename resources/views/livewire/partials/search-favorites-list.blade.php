@php
    /** @var \Illuminate\Support\Collection<int, \Hwkdo\IntranetAppBase\Data\SearchResult> $favorites */
    $onNavigate = $onNavigate ?? null;
    $wireKeyPrefix = $wireKeyPrefix ?? 'favorite';
@endphp

@if ($favorites->isEmpty())
    <div class="space-y-1">
        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
            Favoriten
        </flux:text>
        <flux:text class="text-sm text-zinc-500">
            Noch keine Favoriten. Stern bei einem Suchtreffer tippen.
        </flux:text>
    </div>
@else
    <div class="space-y-1">
        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
            Favoriten
        </flux:text>

        @foreach ($favorites as $favorite)
            <div
                class="flex items-start gap-1"
                wire:key="{{ $wireKeyPrefix }}-{{ $favorite->favoriteKey }}"
            >
                <a
                    href="{{ $favorite->url }}"
                    @if ($favorite->download) download @else wire:navigate @endif
                    @if ($onNavigate) @click="{{ $onNavigate }}" @endif
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
                    class="mt-0.5 shrink-0 rounded-md p-1.5 text-amber-500 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    wire:click.stop="toggleFavorite(
                        @js($favorite->favoriteKey),
                        @js($favorite->title),
                        @js($favorite->url),
                        @js($favorite->icon),
                        @js($favorite->appIdentifier),
                        @js($favorite->appName),
                        @js($favorite->subtitle),
                        @js($favorite->sourceKey),
                        @js($favorite->download)
                    )"
                    aria-label="Favorit entfernen"
                >
                    <flux:icon name="star" variant="solid" class="size-4" />
                </button>
            </div>
        @endforeach
    </div>
@endif
