@props([
    'appIdentifier' => '',
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
    'wrapInCard' => true
])

<div class="w-full">
    @if($heading)
        <flux:heading>{{ $heading }}</flux:heading>
    @endif
    @if($subheading)
        <flux:subheading>{{ $subheading }}</flux:subheading>
    @endif

    <div class="flex items-start max-md:flex-col mt-5">
        <div class="mr-10 w-full pb-4 md:w-[220px]">
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
                <flux:card>
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
