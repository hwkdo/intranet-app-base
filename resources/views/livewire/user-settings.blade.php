<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, state};

state([
    'userSettings' => [],
]);

mount(function () {
    $appIdentifier = $this->appIdentifier;
    
    if (!$appIdentifier) {
        return;
    }
    
    $settings = Auth::user()->settings->app->$appIdentifier;
    
    if ($settings) {
        $reflection = new \ReflectionClass($settings);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $key = $property->getName();
            $value = $property->getValue($settings);
            
            if ($value instanceof \UnitEnum) {
                $this->userSettings[$key] = $value instanceof \BackedEnum ? $value->value : $value->name;
            } elseif (is_array($value)) {
                $this->userSettings[$key] = $value;
            } else {
                $this->userSettings[$key] = $value;
            }
        }
    }
});

$settingsStructure = computed(function () {
    $appIdentifier = $this->appIdentifier;
    
    if (!$appIdentifier) {
        return [];
    }
    
    $settings = Auth::user()->settings->app->$appIdentifier;
    
    if (!$settings) {
        return [];
    }
    
    $structure = [];
    $userSettingsClass = $this->getUserSettingsClass($appIdentifier);
    
    if (!$userSettingsClass) {
        return [];
    }
    
    $reflection = new \ReflectionClass($userSettingsClass);
    $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
    
    foreach ($settings->toArray() as $key => $value) {
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
    $appIdentifier = $this->appIdentifier;
    
    if (!$appIdentifier) {
        return;
    }
    
    $user = Auth::user();
    $user->settings = $user->settings->updateAppSettings($appIdentifier, $this->userSettings);
    $user->save();
    
    Flux::toast(
        heading: 'Einstellungen gespeichert',
        text: 'Ihre Einstellungen wurden erfolgreich aktualisiert.',
        variant: 'success'
    );
};

$getUserSettingsClass = function (string $appIdentifier): ?string {
    $packages = $this->getIntranetAppPackages();
    
    foreach ($packages as $packageName => $packageData) {
        $appClass = $this->getAppClass($packageName, $packageData);
        
        if (!$appClass || !class_exists($appClass)) {
            continue;
        }
        
        if (!is_subclass_of($appClass, \Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface::class)) {
            continue;
        }
        
        // Check if this is the app we're looking for
        if ($appClass::identifier() === $appIdentifier) {
            return $appClass::userSettingsClass();
        }
    }
    
    return null;
};

$getIntranetAppPackages = function (): array {
    $packagesFile = base_path('bootstrap/cache/packages.php');
    
    if (!file_exists($packagesFile)) {
        return [];
    }
    
    $packages = require $packagesFile;
    
    return array_filter($packages, function ($key) {
        return str_starts_with($key, 'hwkdo/intranet-app-') &&
               !str_starts_with($key, 'hwkdo/intranet-app-base');
    }, ARRAY_FILTER_USE_KEY);
};

$getAppClass = function (string $packageName, array $packageData): ?string {
    // Convert package name to class name
    // e.g., "hwkdo/intranet-app-hwro" -> "Hwkdo\IntranetAppHwro\IntranetAppHwro"
    $parts = explode('/', $packageName);
    $vendor = ucfirst($parts[0]);
    $packagePart = str_replace('-', '', ucwords($parts[1], '-'));
    
    return "$vendor\\$packagePart\\$packagePart";
};

?>

<flux:card>
    <flux:heading size="lg" class="mb-4">Persönliche Einstellungen</flux:heading>
    <flux:text class="mb-6">
        Passen Sie Ihre persönlichen Einstellungen für diese App an.
    </flux:text>
    
    <div class="space-y-4">
        @foreach($this->settingsStructure as $field)
            @if($field['type'] === 'switch')
                <flux:switch 
                    wire:model.live="userSettings.{{ $field['key'] }}" 
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'number')
                <flux:input 
                    type="number"
                    wire:model="userSettings.{{ $field['key'] }}" 
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'text')
                <flux:input 
                    type="text"
                    wire:model="userSettings.{{ $field['key'] }}" 
                    :label="$field['label']"
                    :description="$field['description']"
                />
                @if(!$loop->last)
                    <flux:separator variant="subtle" />
                @endif
            @elseif($field['type'] === 'select')
                <flux:select 
                    wire:model="userSettings.{{ $field['key'] }}"
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
                        wire:model="userSettings.{{ $field['key'] }}"
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
