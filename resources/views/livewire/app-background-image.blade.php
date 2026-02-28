<?php

use Flux\Flux;
use Hwkdo\IntranetAppBase\Models\AppBackground;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $appIdentifier;

    #[Validate('image|max:10240', message: 'Die Datei muss ein Bild sein und darf maximal 10 MB groß sein.')]
    public $image = null;

    public bool $hasCustomImage = false;
    public ?string $currentImageUrl = null;

    public function mount(): void
    {
        $record = AppBackground::where('app_identifier', $this->appIdentifier)->first();

        $this->hasCustomImage = $record && $record->hasBackground();
        $this->currentImageUrl = $this->hasCustomImage ? $record->getFirstMediaUrl('background') : null;
    }

    public function saveImage(): void
    {
        $this->validate();

        $record = AppBackground::forApp($this->appIdentifier);

        $record->addMedia($this->image->getRealPath())
            ->usingFileName(
                pathinfo($this->image->getClientOriginalName(), PATHINFO_FILENAME)
                .'.'.$this->image->getClientOriginalExtension()
            )
            ->toMediaCollection('background');

        $this->hasCustomImage = true;
        $this->currentImageUrl = $record->fresh()->getFirstMediaUrl('background');
        $this->image = null;

        Flux::toast(
            heading: 'Hintergrundbild gespeichert',
            text: 'Das Hintergrundbild wurde erfolgreich aktualisiert.',
            variant: 'success'
        );

        $this->dispatch('background-image-updated');
    }

    public function removeImage(): void
    {
        $record = AppBackground::where('app_identifier', $this->appIdentifier)->first();

        if ($record) {
            $record->clearMediaCollection('background');
        }

        $this->hasCustomImage = false;
        $this->currentImageUrl = null;

        Flux::toast(
            heading: 'Hintergrundbild entfernt',
            text: 'Das Hintergrundbild der Hauptanwendung wird wieder verwendet.',
            variant: 'success'
        );

        $this->dispatch('background-image-updated');
    }
}; ?>

<flux:card class="glass-card">
    <flux:heading size="lg" class="mb-1">Hintergrundbild</flux:heading>
    <flux:text class="mb-6">Laden Sie ein individuelles Hintergrundbild für diese App hoch. Ohne eigenes Bild wird das Hintergrundbild der Hauptanwendung verwendet.</flux:text>

    <div class="space-y-6">
        @if($hasCustomImage && $currentImageUrl)
            <div>
                <flux:text class="mb-2 text-sm font-medium">Aktuelles Hintergrundbild</flux:text>
                <div class="relative overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" style="max-height: 220px;">
                    <img src="{{ $currentImageUrl }}" alt="Aktuelles Hintergrundbild" class="w-full object-cover" style="max-height: 220px;" />
                </div>
            </div>
        @else
            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:icon.photo class="size-5 shrink-0 text-zinc-400" />
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Kein individuelles Hintergrundbild gesetzt — das Hintergrundbild der Hauptanwendung wird verwendet.
                </flux:text>
            </div>
        @endif

        <flux:separator variant="subtle" />

        <div>
            <flux:input
                type="file"
                wire:model="image"
                accept="image/*"
                label="Neues Bild hochladen"
                description="Unterstützte Formate: JPG, PNG, WebP, GIF. Maximale Größe: 10 MB."
            />
            @error('image')
                <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
            @enderror

            @if($image)
                <div class="mt-3">
                    <flux:text class="mb-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">Vorschau</flux:text>
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" style="max-height: 160px;">
                        <img src="{{ $image->temporaryUrl() }}" alt="Vorschau" class="w-full object-cover" style="max-height: 160px;" />
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between gap-3">
            @if($hasCustomImage)
                <flux:button wire:click="removeImage" variant="ghost" icon="trash">
                    Zurücksetzen auf Hauptanwendung
                </flux:button>
            @else
                <div></div>
            @endif

            <flux:button
                wire:click="saveImage"
                variant="primary"
                icon="arrow-up-tray"
                :disabled="!$image"
                wire:loading.attr="disabled"
                wire:target="saveImage,image"
            >
                <span wire:loading.remove wire:target="saveImage">Bild speichern</span>
                <span wire:loading wire:target="saveImage">Wird gespeichert…</span>
            </flux:button>
        </div>
    </div>
</flux:card>
