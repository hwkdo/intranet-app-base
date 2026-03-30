@props([
    'title' => '',
    'description' => null,
])

<flux:card class="glass-card flex h-full min-h-0 flex-col overflow-hidden border border-zinc-200 dark:border-white/10 rounded-xl">
    <div class="mb-3 shrink-0">
        <flux:heading size="sm">{{ $title }}</flux:heading>
        @if($description)
            <flux:text size="sm" class="text-zinc-500 dark:text-white/80">{{ $description }}</flux:text>
        @endif
    </div>

    <div class="flex-1 space-y-2 overflow-y-auto pr-1">
        {{ $slot }}
    </div>
</flux:card>
