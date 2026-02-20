@props([
    'appIdentifier' => '',
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
    'wrapInCard' => true
])

<div class="w-full">
    @if($heading || $subheading)
        <div class="glass-card mb-5 px-4 py-3">
            @if($heading)
                <flux:heading>{{ $heading }}</flux:heading>
            @endif
            @if($subheading)
                <flux:subheading>{{ $subheading }}</flux:subheading>
            @endif
        </div>
    @endif

    <div class="flex items-start max-md:flex-col">
        <div class="glass-card mr-10 w-full pb-4 md:w-[220px] p-2">
            <flux:navlist>
                @foreach($navItems as $navItem)
                    @if(!isset($navItem['permission']) || auth()->user()->can($navItem['permission']))
                        <flux:navlist.item 
                            :href="$navItem['href']" 
                            wire:navigate
                        >
                            {{ $navItem['label'] }}
                        </flux:navlist.item>
                    @endif
                @endforeach
            </flux:navlist>
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            @if($wrapInCard)
                <flux:card class="glass-card">
                    {{ $slot }}
                </flux:card>
            @else
                {{ $slot }}
            @endif
        </div>
    </div>
</div>

@push('nav-items')
    @json($navItems)
@endpush
