@props([
    'title',
    'href' => null,
])

<div class="flex shrink-0 items-center border-b border-[#d0e3f9] px-2 py-1.5 dark:border-white/10">
    <span class="size-8 shrink-0" aria-hidden="true"></span>
    <flux:heading size="sm" class="min-w-0 flex-1 truncate text-center">{{ $title }}</flux:heading>
    @if (filled($href))
        <flux:button
            variant="ghost"
            size="sm"
            square
            icon="arrows-pointing-out"
            :href="$href"
            wire:navigate
            tooltip="Vollansicht"
            aria-label="Vollansicht"
            class="shrink-0 text-[#073070]! dark:text-white!"
        />
    @else
        <span class="size-8 shrink-0" aria-hidden="true"></span>
    @endif
</div>
