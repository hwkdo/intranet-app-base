@props([
    'appIdentifier' => '',
    'appName' => '',
    'appDescription' => '',
    'welcomeTitle' => null,
    'welcomeDescription' => null
])

@php
    $welcomeTitle = $welcomeTitle ?? "Willkommen in der {$appName}";
    $welcomeDescription = $welcomeDescription ?? "Hier können Sie alle Aspekte der {$appName} verwalten.";
    
    // Get nav items from the layout stack
    $navItems = [];
    if (View::hasSection('nav-items')) {
        $navItems = json_decode(View::yieldContent('nav-items'), true) ?? [];
    }
    
    // Filter nav items to exclude settings/admin for the main cards
    $mainNavItems = collect($navItems)->filter(function($item) {
        return !in_array($item['label'], ['Meine Einstellungen', 'Admin']) && 
               !str_contains($item['href'], '/settings/') && 
               !str_contains($item['href'], '/admin');
    })->values()->toArray();
    
    // Get settings items separately
    $settingsItems = collect($navItems)->filter(function($item) {
        return in_array($item['label'], ['Meine Einstellungen', 'Admin']) || 
               str_contains($item['href'], '/settings/') || 
               str_contains($item['href'], '/admin');
    })->values()->toArray();
@endphp

<flux:card>
    <flux:heading size="lg" class="mb-4">{{ $welcomeTitle }}</flux:heading>
    <flux:text class="mb-6">{{ $welcomeDescription }}</flux:text>
    
    @if(count($mainNavItems) > 0)
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($mainNavItems as $item)
                <flux:card>
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
            @endforeach
        </div>
    @endif
    
    @if(count($settingsItems) > 0)
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach($settingsItems as $item)
                @if(!isset($item['permission']) || auth()->user()->can($item['permission']))
                    <flux:card>
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
