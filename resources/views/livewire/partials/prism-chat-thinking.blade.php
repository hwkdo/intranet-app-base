<div x-show="hasThinking()" x-cloak>
    <button
        type="button"
        x-on:click="toggleThinking()"
        class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50/90 px-2.5 py-1 text-xs font-medium text-violet-700 transition hover:bg-violet-100 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200 dark:hover:bg-violet-500/20"
    >
        <flux:icon.light-bulb variant="micro" class="size-3.5" />
        Gedankengang
        <flux:icon.chevron-down variant="micro" class="size-3.5 transition-transform duration-200" x-bind:class="showThinking && 'rotate-180'" />
    </button>
    <div x-show="showThinking" x-collapse>
        <div class="mt-2 rounded-xl border-l-2 border-violet-300 bg-violet-50/60 px-3 py-2 text-slate-600 dark:border-violet-400/40 dark:bg-violet-500/5 dark:text-slate-300">
            <article wire:ignore class="prism-chat-markdown prism-chat-markdown--compact" x-html="thinkingHtml"></article>
        </div>
    </div>
</div>
