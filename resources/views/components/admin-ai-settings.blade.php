@props([
    'aiTextProviderOverride' => null,
    'aiTextModelOverride' => null,
    'aiImageProviderOverride' => null,
    'aiImageModelOverride' => null,
    'aiImageEditProviderOverride' => null,
    'aiImageEditModelOverride' => null,
    'aiClassificationProviderOverride' => null,
    'aiClassificationModelOverride' => null,
    'imageProvider' => null,
    'imageEditProvider' => null,
])

@php
    use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
    use Hwkdo\IntranetAppBase\Enums\AiProvider;

    $providers = AiProvider::cases();
    $baseAi = app(IntranetBaseAiConfigSourceInterface::class);
    $generateProviderKey = is_string($imageProvider) && $imageProvider !== ''
        ? $imageProvider
        : $baseAi->imageProvider()->value;
    $editProviderKey = is_string($imageEditProvider) && $imageEditProvider !== ''
        ? $imageEditProvider
        : $baseAi->imageEditProvider()->value;

    $modelsFor = function (string $providerKey, string $kind): array {
        $available = config('ai.providers.'.$providerKey.'.models.image.available', []);
        $options = [];

        if (! is_array($available)) {
            return [];
        }

        foreach ($available as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $entryKind = isset($entry['kind']) ? trim((string) $entry['kind']) : '';
            if ($entryKind !== '' && $entryKind !== $kind) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));
            $options[] = ['id' => $id, 'label' => $label !== '' ? $label : $id];
        }

        return $options;
    };

    $generateModels = $modelsFor($generateProviderKey, 'txt2img');
    $editModels = $modelsFor($editProviderKey, 'img2img');
@endphp

<flux:fieldset>
    <flux:legend>KI-Einstellungen (App-Override)</flux:legend>
    <flux:text class="mb-4 text-sm text-zinc-500">
        Leere Felder nutzen die globalen Einstellungen unter Manager → Base Settings. Generate und Edit haben getrennte Provider.
    </flux:text>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:select wire:model="{{ $aiTextProviderOverride }}" label="Text-Provider (Override)">
            <flux:select.option value="">— Base-Default —</flux:select.option>
            @foreach ($providers as $provider)
                <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="{{ $aiTextModelOverride }}" label="Text-Modell (Override)" placeholder="Leer = Base-Default" />

        <div class="space-y-4">
            <flux:select wire:model.live="{{ $aiImageProviderOverride }}" label="Generate-Provider (Override)" description="Für neue Bilder ohne Vorlage.">
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
        </div>

        <div class="space-y-4">
            <flux:select wire:model.live="{{ $aiImageEditProviderOverride }}" label="Edit-Provider (Override)" description="Wenn ein Bild mitgeschickt wird.">
                <flux:select.option value="">— Base-Default —</flux:select.option>
                @foreach ($providers as $provider)
                    <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
                @endforeach
            </flux:select>

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
        </div>

        <flux:select wire:model="{{ $aiClassificationProviderOverride }}" label="Klassifikation-Provider (Override)">
            <flux:select.option value="">— Base-Default —</flux:select.option>
            @foreach ($providers as $provider)
                <flux:select.option value="{{ $provider->value }}">{{ $provider->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="{{ $aiClassificationModelOverride }}" label="Klassifikation-Modell (Override)" placeholder="Leer = Base-Default" />
    </div>
</flux:fieldset>
