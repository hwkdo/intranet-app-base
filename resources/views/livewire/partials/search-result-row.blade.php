@php
    /** @var \Hwkdo\IntranetAppBase\Data\SearchResult $result */
    $wireClickClose = $wireClickClose ?? false;
    $rowClick = $rowClick ?? null;
@endphp

<div
    class="flex items-start gap-1"
    wire:key="{{ $wireKey }}"
>
    <a
        href="{{ $result->url }}"
        @if ($result->download) download @else wire:navigate @endif
        @if ($wireClickClose) wire:click="closeModal" @endif
        @if ($rowClick) @click="{{ $rowClick }}" @endif
        class="flex min-w-0 flex-1 items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700"
    >
        <flux:icon :name="$result->icon" variant="mini" class="mt-0.5 shrink-0 text-zinc-500" />
        <div class="min-w-0">
            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $result->title }}</div>
            @if ($result->subtitle)
                <div class="truncate text-xs text-zinc-500">{{ $result->subtitle }}</div>
            @endif
        </div>
    </a>
    <button
        type="button"
        class="mt-0.5 shrink-0 rounded-md p-1.5 {{ $favorited ? 'text-amber-500' : 'text-zinc-400 hover:text-amber-500' }} hover:bg-zinc-100 dark:hover:bg-zinc-700"
        wire:click.stop="toggleFavorite(
            @js($result->favoriteKey),
            @js($result->title),
            @js($result->url),
            @js($result->icon),
            @js($result->appIdentifier),
            @js($result->appName),
            @js($result->subtitle),
            @js($result->sourceKey),
            @js($result->download)
        )"
        aria-label="{{ $favorited ? 'Favorit entfernen' : 'Als Favorit speichern' }}"
    >
        <flux:icon name="star" variant="{{ $favorited ? 'solid' : 'outline' }}" class="size-4" />
    </button>
</div>
