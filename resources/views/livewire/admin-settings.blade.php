<?php

use Flux\Flux;
use function Livewire\Volt\{computed, mount, state};

state([
    'appSettings' => [],
    'settingsId' => null,
]);

mount(function () {
    $settingsModelClass = $this->settingsModelClass;
    $appSettingsClass = $this->appSettingsClass;
    
    if (!$settingsModelClass || !$appSettingsClass) {
        return;
    }
    
    $settings = $settingsModelClass::current();
    
    if ($settings && $settings->settings) {
        $this->settingsId = $settings->id;
        $reflection = new \ReflectionClass($settings->settings);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $key = $property->getName();
            $value = $property->getValue($settings->settings);
            
            if ($value instanceof \UnitEnum) {
                $this->appSettings[$key] = $value instanceof \BackedEnum ? $value->value : $value->name;
            } elseif (is_array($value)) {
                $this->appSettings[$key] = $value;
            } else {
                $this->appSettings[$key] = $value;
            }
        }
    }
});

$settingsStructure = computed(function () {
    $settingsModelClass = $this->settingsModelClass;
    $appSettingsClass = $this->appSettingsClass;
    
    if (!$settingsModelClass || !$appSettingsClass) {
        return [];
    }
    
    $settings = $settingsModelClass::current();
    
    if (!$settings || !$settings->settings) {
        return [];
    }
    
    $structure = [];
    $reflection = new \ReflectionClass($appSettingsClass);
    $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
    
    foreach ($settings->settings->toArray() as $key => $value) {
        $property = collect($properties)->first(fn ($p) => $p->getName() === $key);
        $propertyType = $property?->getType();
        
        // Beschreibung aus PHP Attributen abrufen
        $description = '';
        if ($property) {
            $attributes = $property->getAttributes(\Hwkdo\IntranetAppBase\Data\Attributes\Description::class);
            if (!empty($attributes)) {
                $description = $attributes[0]->newInstance()->description;
            }
        }
        
        // Prüfe ob das Property ein Enum ist
        if ($propertyType && !$propertyType->isBuiltin()) {
            $typeName = $propertyType instanceof \ReflectionNamedType ? $propertyType->getName() : null;
            
            if ($typeName && enum_exists($typeName)) {
                $enumClass = $typeName;
                $options = method_exists($enumClass, 'options')
                    ? $enumClass::options()
                    : collect($enumClass::cases())->mapWithKeys(fn ($case) => [$case->value => $case->name])->toArray();
                
                $structure[] = [
                    'key' => $key,
                    'type' => 'select',
                    'options' => $options,
                    'label' => __(str_replace('_', ' ', ucfirst($key))),
                    'description' => $description,
                ];
                
                continue;
            }
        }
        
        // Fallback zu normalen Typen
        if (is_bool($value)) {
            $structure[] = [
                'key' => $key,
                'type' => 'switch',
                'label' => __(str_replace('_', ' ', ucfirst($key))),
                'description' => $description,
            ];
        } elseif (is_numeric($value)) {
            $structure[] = [
                'key' => $key,
                'type' => 'number',
                'label' => __(str_replace('_', ' ', ucfirst($key))),
                'description' => $description,
            ];
        } elseif (is_string($value)) {
            $structure[] = [
                'key' => $key,
                'type' => 'text',
                'label' => __(str_replace('_', ' ', ucfirst($key))),
                'description' => $description,
            ];
        } elseif (is_array($value)) {
            $structure[] = [
                'key' => $key,
                'type' => 'array',
                'label' => __(str_replace('_', ' ', ucfirst($key))),
                'description' => $description,
            ];
        }
    }
    
    return $structure;
});

$save = function () {
    $settingsModelClass = $this->settingsModelClass;
    $appSettingsClass = $this->appSettingsClass;
    
    if (!$settingsModelClass || !$appSettingsClass) {
        return;
    }
    
    $settings = $settingsModelClass::find($this->settingsId);
    
    if ($settings) {
        $settings->settings = $appSettingsClass::from($this->appSettings);
        $settings->save();
        
        Flux::toast(
            heading: 'Einstellungen gespeichert',
            text: 'Die Admin-Einstellungen wurden erfolgreich aktualisiert.',
            variant: 'success'
        );
    }
};

?>

<flux:card>
    <flux:heading size="lg" class="mb-4">Administrator-Einstellungen</flux:heading>
    <flux:text class="mb-6">
        Verwalten Sie die globalen Einstellungen für diese App.
    </flux:text>
    
    <div class="space-y-4">
        @foreach($this->settingsStructure as $field)
            @if($field['type'] === 'switch')
                <flux:switch 
                    wire:model.live="appSettings.{{ $field['key'] }}" 
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'number')
                <flux:input 
                    type="number"
                    wire:model="appSettings.{{ $field['key'] }}" 
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'text')
                <flux:input 
                    type="text"
                    wire:model="appSettings.{{ $field['key'] }}" 
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'select')
                <flux:select 
                    wire:model="appSettings.{{ $field['key'] }}"
                    variant="listbox"
                    :label="$field['label']"
                    :description="$field['description']"
                >
                    @foreach($field['options'] as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'array')
                <div class="space-y-2">
                    <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $field['label'] }}
                    </flux:text>
                    @if($field['description'])
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $field['description'] }}
                        </flux:text>
                    @endif
                    <flux:textarea 
                        wire:model="appSettings.{{ $field['key'] }}"
                        placeholder="JSON Array (z.B. [&quot;item1&quot;, &quot;item2&quot;])"
                        rows="3"
                    />
                </div>
                @if(!$loop->last)
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
