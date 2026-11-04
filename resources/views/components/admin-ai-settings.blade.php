@props([
    'aiTextProviderOverride' => null,
    'aiTextModelOverride' => null,
    'aiImageProviderOverride' => null,
    'aiImageModelOverride' => null,
    'aiImageEditModelOverride' => null,
    'aiClassificationProviderOverride' => null,
    'aiClassificationModelOverride' => null,
    'imageProvider' => null,
])

@php
    use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
    use Hwkdo\IntranetAppBase\Enums\AiProvider;

    $providers = AiProvider::cases();
    $imageProviderKey = is_string($imageProvider) && $imageProvider !== ''
        ? $imageProvider
        : app(IntranetBaseAiConfigSourceInterface::class)->imageProvider()->value;
    $available = config('ai.providers.'.$imageProviderKey.'.models.image.available', []);
    $generateModels = [];
    $editModels = [];

    if (is_array($available)) {
        foreach ($available as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $kind = isset($entry['kind']) ? trim((string) $entry['kind']) : '';
            $label = trim((string) ($entry['label'] ?? ''));
            $option = ['id' => $id, 'label' => $label !== '' ? $label : $id];

            if ($kind === 'img2img') {
                $editModels[] = $option;
            } elseif ($kind === 'txt2img') {
                $generateModels[] = $option;
            } else {
                $generateModels[] = $option;
                $editModels[] = $option;
            }
        }
    }
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

        <flux:select wire:model.live="{{ $aiImageProviderOverride }}" label="Bild-Provider (Override)">
            <flux:select.option value="">— Base-Default —</flux:select.option>
            @foreach ($providers as $provider)
                <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($generateModels !== [])
            <flux:select wire:model="{{ $aiImageModelOverride }}" label="Generate-Modell (Override)" variant="listbox">
                <flux:select.option value="">— Base-Default —</flux:select.option>
                @foreach ($generateModels as $option)
                    <flux:select.option value="{{ $option['id'] }}">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:input wire:model="{{ $aiImageModelOverride }}" label="Generate-Modell (Override)" placeholder="Leer = Base-Default" />
        @endif

        @if ($editModels !== [])
            <flux:select wire:model="{{ $aiImageEditModelOverride }}" label="Edit-Modell (Override)" variant="listbox">
                <flux:select.option value="">— Base-Default —</flux:select.option>
                @foreach ($editModels as $option)
                    <flux:select.option value="{{ $option['id'] }}">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:input wire:model="{{ $aiImageEditModelOverride }}" label="Edit-Modell (Override)" placeholder="Leer = Base-Default" />
        @endif

        <flux:select wire:model="{{ $aiClassificationProviderOverride }}" label="Klassifikation-Provider (Override)">
            <flux:select.option value="">— Base-Default —</flux:select.option>
            @foreach ($providers as $provider)
                <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="{{ $aiClassificationModelOverride }}" label="Klassifikation-Modell (Override)" placeholder="Leer = Base-Default" />
    </div>
</flux:fieldset>
