<div wire:poll.30s>
    @if ($this->totalCount === 0)
        <div class="flex flex-col items-center justify-center py-8 text-center">
            <flux:icon name="check-circle" class="size-10 text-green-500 dark:text-green-400 mb-3" />
            <flux:heading size="sm">Keine offenen Aufgaben</flux:heading>
            <flux:text class="mt-1">Sie haben aktuell keine offenen Aufgaben.</flux:text>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->groupedTasks as $appIdentifier => $tasks)
                <div class="rounded-lg border border-zinc-200 dark:border-white/10 overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-zinc-50/80 dark:bg-white/5 border-b border-zinc-200 dark:border-white/10">
                        <flux:icon :name="$tasks->first()->appIcon" class="size-4 text-zinc-500 dark:text-zinc-400" />
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $tasks->first()->appName }}</span>
                        <flux:badge size="sm" variant="solid" color="red" class="ml-auto">
                            {{ $tasks->count() }}
                        </flux:badge>
                    </div>

                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($tasks as $task)
                            <li>
                                <a
                                    href="{{ $task->url }}"
                                    class="flex items-start gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-white/5 transition-colors"
                                >
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                            {{ $task->title }}
                                        </p>
                                        @if ($task->description)
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">
                                                {{ $task->description }}
                                            </p>
                                        @endif
                                    </div>
                                    @if ($task->badge)
                                        <flux:badge size="sm">{{ $task->badge }}</flux:badge>
                                    @endif
                                    <flux:icon name="chevron-right" class="size-4 text-zinc-400 shrink-0 mt-0.5" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
