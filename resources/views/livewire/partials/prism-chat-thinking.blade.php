<div x-show="hasThinking()" x-cloak>
    <button
        type="button"
        x-on:click="toggleThinking()"
        class="inline-flex items-center gap-1.5 rounded-full border border-[#d0e3f9] bg-[#d0e3f9]/70 px-2.5 py-1 text-xs font-medium text-[#073070] transition hover:bg-[#d0e3f9] dark:border-white/15 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
    >
        <flux:icon.light-bulb variant="micro" class="size-3.5" />
        Gedankengang
        <flux:icon.chevron-down variant="micro" class="size-3.5 transition-transform duration-200" x-bind:class="showThinking && 'rotate-180'" />
    </button>
    <div x-show="showThinking" x-collapse>
        <div class="mt-2 rounded-xl border-l-2 border-[#a0c7f2] bg-[#d0e3f9]/40 px-3 py-2 text-slate-600 dark:border-[#d0e3f9]/50 dark:bg-white/5 dark:text-white/80">
            <article wire:ignore class="prism-chat-markdown prism-chat-markdown--compact" x-html="thinkingHtml"></article>
        </div>
    </div>
</div>
