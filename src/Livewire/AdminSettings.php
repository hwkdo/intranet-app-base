<?php

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Livewire\Component;

class AdminSettings extends Component
{
    public string $appIdentifier;
    public string $settingsModelClass;
    public string $appSettingsClass;
    public array $appSettings = [];
    public ?int $settingsId = null;

    public function mount(string $appIdentifier, string $settingsModelClass, string $appSettingsClass): void
    {
        $this->appIdentifier = $appIdentifier;
        $this->settingsModelClass = $settingsModelClass;
        $this->appSettingsClass = $appSettingsClass;

        if (class_exists($settingsModelClass)) {
            $settings = $settingsModelClass::current();

            if ($settings && $settings->settings) {
                $this->settingsId = $settings->id;
                $this->appSettings = $settings->settings->toArray();
            } else {
                // If no settings exist, initialize with defaults from the Data class
                $this->appSettings = (new $appSettingsClass())->toArray();
            }
        } else {
            $this->appSettings = (new $appSettingsClass())->toArray();
        }
    }

    public function save(): void
    {
        if (!class_exists($this->settingsModelClass)) {
            Flux::toast(
                heading: 'Fehler',
                text: 'Einstellungen konnten nicht gespeichert werden: Modellklasse nicht gefunden.',
                variant: 'error'
            );
            return;
        }

        $settings = $this->settingsId ? $this->settingsModelClass::find($this->settingsId) : null;

        if ($settings) {
            $settings->settings = $this->appSettingsClass::from($this->appSettings);
            $settings->save();
        } else {
            // Create new settings entry
            $settings = $this->settingsModelClass::create([
                'version' => 1,
                'settings' => $this->appSettingsClass::from($this->appSettings)->toArray(),
            ]);
            $this->settingsId = $settings->id;
        }

        Flux::toast(
            heading: 'Einstellungen gespeichert',
            text: 'Die Administrator-Einstellungen wurden erfolgreich aktualisiert.',
            variant: 'success'
        );
    }

    public function getSettingsStructureProperty(): array
    {
        $settingsData = $this->appSettingsClass::from($this->appSettings);
        $structure = [];

        foreach ($settingsData->getPropertiesWithDescriptions() as $key => $field) {
            $type = $field['type'];
            $label = __(str_replace('_', ' ', ucfirst($key)));
            $description = $field['description'];
            $value = $field['value'];

            if (enum_exists($type)) {
                $enumClass = $type;
                $options = method_exists($enumClass, 'options')
                    ? $enumClass::options()
                    : collect($enumClass::cases())->mapWithKeys(fn ($case) => [$case->value => $case->name])->toArray();

                $structure[] = [
                    'key' => $key,
                    'type' => 'select',
                    'options' => $options,
                    'label' => $label,
                    'description' => $description,
                ];
            } elseif ($type === 'bool') {
                $structure[] = [
                    'key' => $key,
                    'type' => 'switch',
                    'label' => $label,
                    'description' => $description,
                ];
            } elseif ($type === 'int' || $type === 'float') {
                $structure[] = [
                    'key' => $key,
                    'type' => 'number',
                    'label' => $label,
                    'description' => $description,
                ];
            } elseif ($type === 'string') {
                $structure[] = [
                    'key' => $key,
                    'type' => 'text',
                    'label' => $label,
                    'description' => $description,
                ];
            } elseif ($type === 'array') {
                $structure[] = [
                    'key' => $key,
                    'type' => 'json',
                    'label' => $label,
                    'description' => $description,
                ];
            }
        }

        return $structure;
    }

    public function render()
    {
        return view('intranet-app-base::livewire.admin-settings');
    }
}
