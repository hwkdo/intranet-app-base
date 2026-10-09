<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppBase\Contracts\DocumentParseConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DocumentParseSettings extends Component
{
    public string $appIdentifier;

    public string $settingsModelClass;

    public string $appSettingsClass;

    public string $engineKey = 'documentParseEngineOverride';

    public string $tierKey = 'documentParseTierOverride';

    public string $engine = '';

    public string $tier = '';

    public function mount(
        string $appIdentifier,
        string $settingsModelClass,
        string $appSettingsClass,
        string $engineKey = 'documentParseEngineOverride',
        string $tierKey = 'documentParseTierOverride',
    ): void {
        $this->appIdentifier = $appIdentifier;
        $this->settingsModelClass = $settingsModelClass;
        $this->appSettingsClass = $appSettingsClass;
        $this->engineKey = $engineKey;
        $this->tierKey = $tierKey;

        $settings = $this->currentSettings();
        $engine = $settings->{$this->engineKey} ?? null;
        $tier = $settings->{$this->tierKey} ?? null;

        $this->engine = $engine instanceof DocumentParseEngine ? $engine->value : '';
        $this->tier = is_string($tier) ? $tier : '';
    }

    public function save(): void
    {
        $this->validate([
            'engine' => ['nullable', Rule::in(array_merge([''], array_column(DocumentParseEngine::cases(), 'value')))],
            'tier' => ['nullable', Rule::in(['', 'fast', 'cost_effective', 'agentic', 'agentic_plus'])],
        ]);

        if (! class_exists($this->settingsModelClass) || ! method_exists($this->settingsModelClass, 'current')) {
            Flux::toast(heading: 'Fehler', text: 'Einstellungen konnten nicht gespeichert werden.', variant: 'danger');

            return;
        }

        $model = $this->settingsModelClass::current();
        if ($model === null && str_ends_with($this->settingsModelClass, 'Settings')) {
            $model = $this->settingsModelClass::create([
                'version' => 1,
                'settings' => new $this->appSettingsClass,
            ]);
        }

        if ($model === null) {
            Flux::toast(heading: 'Fehler', text: 'Es gibt noch keinen Einstellungsdatensatz.', variant: 'danger');

            return;
        }

        $current = $model->settings;
        $values = is_object($current) && method_exists($current, 'toArray')
            ? $current->toArray()
            : (is_array($current) ? $current : []);

        $values[$this->engineKey] = $this->engine === '' ? null : $this->engine;
        $values[$this->tierKey] = $this->tier === '' ? null : $this->tier;

        $model->settings = $this->appSettingsClass::from($values);
        $model->save();

        Flux::toast(heading: 'Gespeichert', text: 'Document-Parsing wurde aktualisiert.', variant: 'success');
    }

    #[Computed]
    public function baseSummary(): string
    {
        $base = app(IntranetBaseAiConfigSourceInterface::class);

        return $base->documentParseEngine()->label().' / '.$base->documentParseTier();
    }

    #[Computed]
    public function effectiveSummary(): string
    {
        $resolved = app(DocumentParseConfigResolverInterface::class)->resolve($this->appIdentifier);

        return $resolved->engine->label().' / '.$resolved->llamaParseTier;
    }

    private function currentSettings(): object
    {
        if (class_exists($this->settingsModelClass) && method_exists($this->settingsModelClass, 'current')) {
            $model = $this->settingsModelClass::current();
            $settings = $model?->settings;
            if (is_object($settings)) {
                return $settings;
            }
        }

        return new $this->appSettingsClass;
    }

    public function render(): mixed
    {
        return view('intranet-app-base::livewire.document-parse-settings');
    }
}
