<div wire:poll.30s>
    @if (!($this->hideWhenEmpty && $this->totalCount === 0))
        <flux:card class="glass-card p-0! rounded-xl! overflow-hidden">
            <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-[rgb(208_227_249/0.8)] dark:border-white/10">
                <flux:icon name="clipboard-document-list" class="size-4 text-zinc-500 dark:text-zinc-300" />
                <flux:heading size="sm" class="font-semibold">Ihre Aufgaben</flux:heading>
            </div>
            <div class="p-4">
                @if ($this->totalCount === 0)
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <flux:icon name="check-circle" class="size-10 text-green-500 dark:text-green-400 mb-3" />
                        <flux:heading size="sm">Keine offenen Aufgaben</flux:heading>
                        <flux:text class="mt-1">Sie haben aktuell keine offenen Aufgaben.</flux:text>
                    </div>
                @else
                    <flux:accordion class="-mx-1">
                        @foreach ($this->groupedTasks as $appIdentifier => $tasks)
                            <flux:accordion.item
                                wire:key="ihre-aufgaben-gruppe-{{ $appIdentifier }}"
                                transition
                            >
                                <flux:accordion.heading>
                                    <div class="flex w-full min-w-0 items-center gap-2 pe-2">
                                        <flux:icon
                                            :name="$tasks->first()->appIcon"
                                            class="size-4 shrink-0 text-zinc-500 dark:text-zinc-300"
                                        />
                                        <span class="min-w-0 truncate text-sm font-medium text-zinc-800 dark:text-white">
                                            {{ $tasks->first()->appName }}
                                        </span>
                                        <flux:badge size="sm" variant="solid" color="red" class="ms-auto shrink-0">
                                            {{ $tasks->count() }}
                                        </flux:badge>
                                    </div>
                                </flux:accordion.heading>
                                <flux:accordion.content class="!text-zinc-900 dark:!text-zinc-100">
                                    <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-white/10">
                                        @foreach ($tasks as $task)
                                            <li wire:key="ihre-aufgabe-{{ $appIdentifier }}-{{ $loop->index }}">
                                                <a
                                                    href="{{ $task->url }}"
                                                    class="flex items-start gap-3 px-3 py-2.5 transition-colors hover:bg-zinc-50 dark:hover:bg-white/5"
                                                >
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                            {{ $task->title }}
                                                        </p>
                                                        @if ($task->description)
                                                            <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-300">
                                                                {{ $task->description }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                    @if ($task->badge)
                                                        <flux:badge size="sm">{{ $task->badge }}</flux:badge>
                                                    @endif
                                                    <flux:icon
                                                        name="chevron-right"
                                                        class="mt-0.5 size-4 shrink-0 text-zinc-400"
                                                    />
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </flux:accordion.content>
                            </flux:accordion.item>
                        @endforeach
                    </flux:accordion>
                @endif
            </div>
        </flux:card>
    @endif
</div>
