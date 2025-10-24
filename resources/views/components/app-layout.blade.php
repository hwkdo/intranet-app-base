@props([
    'appIdentifier' => '',
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

<div class="flex items-start max-md:flex-col">
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
        @if($heading)
            <flux:heading>{{ $heading }}</flux:heading>
        @endif
        @if($subheading)
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>

@push('nav-items')
    @json($navItems)
@endpush
