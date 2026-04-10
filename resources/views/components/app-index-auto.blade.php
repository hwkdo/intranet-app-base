@props([
    'appIdentifier' => '',
    'appName' => '',
    'appDescription' => '',
    'welcomeTitle' => null,
    'welcomeDescription' => null,
    'navItems' => []
])

@php
    $welcomeTitle = $welcomeTitle ?? "Willkommen in der {$appName}";
    $welcomeDescription = $welcomeDescription ?? "Hier können Sie alle Aspekte der {$appName} verwalten.";

    // Use provided nav items or get from layout stack
    if (empty($navItems) && View::hasSection('nav-items')) {
        $navItems = json_decode(View::yieldContent('nav-items'), true) ?? [];
    }

    $linkItems = collect($navItems)->filter(function (array $item): bool {
        return ($item['type'] ?? '') !== 'separator' && isset($item['href']);
    });

    $usesWelcomeSection = $linkItems->contains(fn (array $item): bool => array_key_exists('welcomeSection', $item));

    if ($usesWelcomeSection) {
        $mainNavItems = $linkItems
            ->filter(fn (array $item): bool => ($item['welcomeSection'] ?? 'main') === 'main')
            ->values()
            ->toArray();
        $settingsItems = $linkItems
            ->filter(fn (array $item): bool => ($item['welcomeSection'] ?? '') === 'settings')
            ->values()
            ->toArray();
    } else {
        $mainNavItems = $linkItems
            ->filter(function (array $item): bool {
                return ! in_array($item['label'], ['Meine Einstellungen', 'Admin', 'Admin-Settings'], true)
                    && ! str_contains($item['href'], '/settings/')
                    && ! str_contains($item['href'], '/admin');
            })
            ->values()
            ->toArray();
        $settingsItems = $linkItems
            ->filter(function (array $item): bool {
                return in_array($item['label'], ['Meine Einstellungen', 'Admin', 'Admin-Settings'], true)
                    || str_contains($item['href'], '/settings/')
                    || str_contains($item['href'], '/admin');
            })
            ->values()
            ->toArray();
    }
@endphp

<flux:card class="glass-card">
    <flux:heading size="lg" class="mb-4">{{ $welcomeTitle }}</flux:heading>
    <flux:text class="mb-6">{{ $welcomeDescription }}</flux:text>

    @if(count($mainNavItems) > 0)
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($mainNavItems as $item)
                @if(!isset($item['permission']) || auth()->user()->can($item['permission']))
                    <flux:card class="glass-card">
                        <div class="flex items-center gap-3">
                            <flux:icon name="{{ $item['icon'] ?? 'document-text' }}" class="size-8 text-zinc-500 dark:text-zinc-400" />
                            <div>
                                <flux:heading size="sm">{{ $item['label'] }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500">{{ $item['description'] ?? $item['label'] . ' verwalten' }}</flux:text>
                            </div>
                        </div>
                        <flux:button
                            :href="$item['href']"
                            wire:navigate
                            variant="primary"
                            class="mt-4 w-full"
                        >
                            {{ $item['buttonText'] ?? $item['label'] . ' anzeigen' }}
                        </flux:button>
                    </flux:card>
                @endif
            @endforeach
        </div>
    @endif

    @if(count($settingsItems) > 0)
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach($settingsItems as $item)
                @if(!isset($item['permission']) || auth()->user()->can($item['permission']))
                    <flux:card class="glass-card">
                        <div class="flex items-center gap-3">
                            <flux:icon name="{{ $item['icon'] ?? 'cog-6-tooth' }}" class="size-8 text-zinc-500 dark:text-zinc-400" />
                            <div>
                                <flux:heading size="sm">{{ $item['label'] }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500">{{ $item['description'] ?? $item['label'] . ' verwalten' }}</flux:text>
                            </div>
                        </div>
                        <flux:button
                            :href="$item['href']"
                            wire:navigate
                            variant="primary"
                            class="mt-4 w-full"
                        >
                            {{ $item['buttonText'] ?? $item['label'] . ' öffnen' }}
                        </flux:button>
                    </flux:card>
                @endif
            @endforeach
        </div>
    @endif
</flux:card>
