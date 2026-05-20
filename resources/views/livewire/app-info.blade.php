<div class="space-y-6">
    @if($loadError)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ $loadError }}
        </flux:callout>
    @endif

    @if($this->installedPackage)
        @php($installed = $this->installedPackage)
        <flux:card>
            <flux:heading size="lg">Installierte Version</flux:heading>
            <div class="mt-3 space-y-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge color="lime">{{ $installed->version }}</flux:badge>
                    @if($installed->isDevelopmentVersion())
                        <flux:badge color="amber">Entwicklungsversion</flux:badge>
                    @endif
                </div>
                @if($installed->reference)
                    <flux:text class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                        Commit: {{ \Illuminate\Support\Str::limit($installed->reference, 12, '') }}
                    </flux:text>
                @endif
                @if($installed->installedAt)
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        Installiert: {{ \Carbon\Carbon::parse($installed->installedAt)->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                    </flux:text>
                @endif
                <div>
                    <flux:link href="{{ $installed->githubHomepageUrl() }}" target="_blank" external>
                        Repository auf GitHub
                    </flux:link>
                </div>
            </div>
        </flux:card>

        @if($this->currentRelease)
            @php($current = $this->currentRelease)
            <flux:card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Aktuelles Release ({{ $current->tagName }})</flux:heading>
                        <flux:subheading>{{ $current->displayTitle() }}</flux:subheading>
                    </div>
                    <flux:link href="{{ $current->htmlUrl }}" target="_blank" external>
                        Auf GitHub ansehen
                    </flux:link>
                </div>
                @if($current->hasBody())
                    <div class="prose prose-sm dark:prose-invert mt-4 max-w-none">
                        <x-markdown>
                            {{ $current->body }}
                        </x-markdown>
                    </div>
                @endif
            </flux:card>
        @elseif(! $installed->isDevelopmentVersion() && $this->releases->isEmpty())
            <flux:callout variant="subtle" icon="information-circle">
                GitHub-Releases konnten nicht geladen werden.
            </flux:callout>
        @elseif(! $installed->isDevelopmentVersion())
            <flux:callout variant="subtle" icon="information-circle">
                Für die installierte Version {{ $installed->version }} wurde kein GitHub-Release gefunden.
            </flux:callout>
        @endif

        @if($this->previousRelease)
            @php($previous = $this->previousRelease)
            <flux:card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Vorheriges Release ({{ $previous->tagName }})</flux:heading>
                        <flux:subheading>{{ $previous->displayTitle() }}</flux:subheading>
                    </div>
                    <flux:link href="{{ $previous->htmlUrl }}" target="_blank" external>
                        Auf GitHub ansehen
                    </flux:link>
                </div>
                @if($previous->hasBody())
                    <div class="prose prose-sm dark:prose-invert mt-4 max-w-none">
                        <x-markdown>
                            {{ $previous->body }}
                        </x-markdown>
                    </div>
                @endif
            </flux:card>
        @endif

        @if($this->releases->isNotEmpty())
            <flux:card>
                <flux:heading size="lg" class="mb-4">Alle Versionen</flux:heading>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->releases as $release)
                        <div
                            @class([
                                'py-4 first:pt-0 last:pb-0',
                                'bg-lime-500/5 -mx-4 px-4 rounded-lg' => $this->currentRelease && $release->tagName === $this->currentRelease->tagName,
                            ])
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <flux:heading size="sm">{{ $release->tagName }}</flux:heading>
                                    <flux:text>{{ $release->displayTitle() }}</flux:text>
                                    @if($release->publishedAt)
                                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $release->publishedAt->timezone(config('app.timezone'))->format('d.m.Y') }}
                                        </flux:text>
                                    @endif
                                </div>
                                <flux:link href="{{ $release->htmlUrl }}" target="_blank" external class="text-sm">
                                    Release
                                </flux:link>
                            </div>
                            @if($release->hasBody())
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-sm text-zinc-600 dark:text-zinc-400">Release-Notizen</summary>
                                    <div class="prose prose-sm dark:prose-invert mt-2 max-w-none">
                                        <x-markdown>
                                            {{ $release->body }}
                                        </x-markdown>
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    @endif
</div>
