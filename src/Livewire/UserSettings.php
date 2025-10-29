<?php

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UserSettings extends Component
{
    public string $appIdentifier;
    public array $userSettings = [];

    public function mount(string $appIdentifier): void
    {
        $this->appIdentifier = $appIdentifier;

        $user = Auth::user();
        $settings = $user->settings->app->{$appIdentifier};

        if ($settings) {
            $this->userSettings = $settings->toArray();
        } else {
            // Initialize with defaults from the Data class
            $appClass = $this->getAppClass($appIdentifier);
            if ($appClass && method_exists($appClass, 'userSettingsClass')) {
                $userSettingsClass = $appClass::userSettingsClass();
                if ($userSettingsClass && class_exists($userSettingsClass)) {
                    $this->userSettings = (new $userSettingsClass())->toArray();
                }
            }
        }
    }

    public function save(): void
    {
        $user = Auth::user();
        $user->settings->app->updateApp($this->appIdentifier, $this->userSettings);
        $user->save();

        Flux::toast(
            heading: 'Einstellungen gespeichert',
            text: 'Ihre Einstellungen wurden erfolgreich aktualisiert.',
            variant: 'success'
        );
    }

    public function getSettingsStructureProperty(): array
    {
        $appClass = $this->getAppClass($this->appIdentifier);
        if (!$appClass || !method_exists($appClass, 'userSettingsClass')) {
            return [];
        }

        $userSettingsClass = $appClass::userSettingsClass();
        if (!$userSettingsClass || !class_exists($userSettingsClass)) {
            return [];
        }

        $settingsData = $userSettingsClass::from($this->userSettings);
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

    private function getAppClass(string $appIdentifier): ?string
    {
        $packagePart = \Illuminate\Support\Str::studly($appIdentifier);
        $appClass = "Hwkdo\\IntranetApp{$packagePart}\\IntranetApp{$packagePart}";

        return class_exists($appClass) ? $appClass : null;
    }

    public function render()
    {
        return view('intranet-app-base::livewire.user-settings');
    }
}
