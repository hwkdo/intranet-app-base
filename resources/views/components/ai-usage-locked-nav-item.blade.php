@props([
    'label',
])

@php
    $message = \Hwkdo\IntranetAppBase\Support\AiUsage::DENIED_MESSAGE;
@endphp

<flux:tooltip toggleable position="right">
    <button
        type="button"
        {{ $attributes->class([
            'h-10 lg:h-8 relative flex items-center gap-3 rounded-lg',
            'py-0 text-start w-full px-3 my-px',
            'text-zinc-400 dark:text-white/40',
            'opacity-60 cursor-not-allowed',
        ]) }}
        aria-disabled="true"
        data-flux-navlist-item
        data-ai-usage-locked
    >
        <div class="flex-1 text-sm font-medium leading-none whitespace-nowrap">{{ $label }}</div>
    </button>

    <flux:tooltip.content class="max-w-[16rem]">
        {{ $message }}
    </flux:tooltip.content>
</flux:tooltip>
