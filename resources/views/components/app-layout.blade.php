@props([
    'appIdentifier' => '',
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
    'wrapInCard' => true
])

@php
    $canUseAi = \Hwkdo\IntranetAppBase\Support\AiUsage::allowed();
@endphp

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
                    @if(($navItem['type'] ?? '') === 'separator')
                        @if(!isset($navItem['permission']) || auth()->user()->can($navItem['permission']))
                            <flux:separator :text="$navItem['label']" class="my-3" />
                        @endif
                    @elseif(!isset($navItem['permission']) || auth()->user()->can($navItem['permission']))
                        @if(! empty($navItem['requiresAiUsage']) && ! $canUseAi)
                            <x-intranet-app-base::ai-usage-locked-nav-item :label="$navItem['label']" />
                        @else
                            <flux:navlist.item
                                :href="$navItem['href']"
                                wire:navigate
                            >
                                {{ $navItem['label'] }}
                            </flux:navlist.item>
                        @endif
                    @endif
                @endforeach
            </flux:navlist>
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 min-w-0 self-stretch max-md:pt-6">
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
