@props([
    'label',
])

@php
    $message = \Hwkdo\IntranetAppBase\Support\AiUsage::DENIED_MESSAGE;
@endphp

<flux:tooltip toggleable>
    <div
        class="mt-4 w-full cursor-not-allowed"
        tabindex="0"
        role="button"
        aria-disabled="true"
        data-ai-usage-locked
        {{ $attributes }}
    >
        <flux:button
            type="button"
            variant="primary"
            class="w-full pointer-events-none"
            disabled
        >
            {{ $label }}
        </flux:button>
    </div>

    <flux:tooltip.content class="max-w-[16rem]">
        {{ $message }}
    </flux:tooltip.content>
</flux:tooltip>
