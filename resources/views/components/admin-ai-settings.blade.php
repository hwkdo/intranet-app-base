@props([
    'aiTextProviderOverride' => null,
    'aiTextModelOverride' => null,
    'aiImageProviderOverride' => null,
    'aiImageModelOverride' => null,
])

@php
    use Hwkdo\IntranetAppBase\Enums\AiProvider;
    $providers = AiProvider::cases();
@endphp

<flux:fieldset>
    <flux:legend>KI-Einstellungen (App-Override)</flux:legend>
    <flux:text class="mb-4 text-sm text-zinc-500">
        Leere Felder nutzen die globalen Einstellungen unter Manager → Base Settings.
    </flux:text>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:select wire:model="{{ $aiTextProviderOverride }}" label="Text-Provider (Override)">
            <flux:select.option value="">— Base-Default —</flux:select.option>
            @foreach ($providers as $provider)
                <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="{{ $aiTextModelOverride }}" label="Text-Modell (Override)" placeholder="Leer = Base-Default" />

        <flux:select wire:model="{{ $aiImageProviderOverride }}" label="Bild-Provider (Override)">
            <flux:select.option value="">— Base-Default —</flux:select.option>
            @foreach ($providers as $provider)
                <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="{{ $aiImageModelOverride }}" label="Bild-Modell (Override)" placeholder="Leer = Base-Default" />
    </div>
</flux:fieldset>
