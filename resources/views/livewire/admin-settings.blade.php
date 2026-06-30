<flux:card class="glass-card">
    <flux:heading size="lg" class="mb-4">Administrator-Einstellungen</flux:heading>
    <flux:text class="mb-6">
        Verwalten Sie die globalen Einstellungen für diese App.
    </flux:text>

    <div class="space-y-4">
        @foreach ($this->settingsStructure as $field)
            @if ($field['type'] === 'switch')
                <flux:switch
                    wire:model.live="appSettings.{{ $field['key'] }}"
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if (! $loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif ($field['type'] === 'number')
                <flux:input
                    type="number"
                    wire:model="appSettings.{{ $field['key'] }}"
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if (! $loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif ($field['type'] === 'text')
                <flux:input
                    type="text"
                    wire:model="appSettings.{{ $field['key'] }}"
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if (! $loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif ($field['type'] === 'select')
                <flux:select
                    wire:model="appSettings.{{ $field['key'] }}"
                    variant="listbox"
                    :label="$field['label']"
                    :description="$field['description']"
                >
                    @foreach ($field['options'] as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if (! $loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif ($field['type'] === 'array' || $field['type'] === 'json')
                <div class="space-y-2">
                    <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $field['label'] }}
                    </flux:text>
                    @if ($field['description'])
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $field['description'] }}
                        </flux:text>
                    @endif
                    <flux:textarea
                        wire:model="appSettings.{{ $field['key'] }}"
                        placeholder="JSON (z.B. {&quot;default&quot;: [&quot;OU=...,DC=...&quot;], &quot;schulung&quot;: []})"
                        rows="5"
                    />
                </div>
                @if (! $loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @endif
        @endforeach
    </div>

    <div class="mt-6 flex justify-end">
        <flux:button wire:click="save" variant="primary">
            Einstellungen speichern
        </flux:button>
    </div>
</flux:card>
