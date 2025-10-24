<?php

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Livewire\Wireable;
use Spatie\LaravelData\Concerns\WireableData;
use Spatie\LaravelData\Data;

abstract class BaseUserSettings extends Data implements Wireable
{
    use WireableData;

    /**
     * Gibt die Beschreibung für eine bestimmte Eigenschaft zurück
     */
    public function getDescriptionFor(string $property): ?string
    {
        $reflection = new \ReflectionClass($this);
        
        if (!$reflection->hasProperty($property)) {
            return null;
        }
        
        $propertyReflection = $reflection->getProperty($property);
        $attributes = $propertyReflection->getAttributes(Description::class);
        
        if (empty($attributes)) {
            return null;
        }
        
        return $attributes[0]->newInstance()->description;
    }

    /**
     * Gibt alle Eigenschaften mit ihren Beschreibungen zurück
     */
    public function getPropertiesWithDescriptions(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = [];
        
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Description::class);
            $description = null;
            
            if (!empty($attributes)) {
                $description = $attributes[0]->newInstance()->description;
            }
            
            $properties[$property->getName()] = [
                'value' => $property->getValue($this),
                'type' => $property->getType()?->getName(),
                'description' => $description,
            ];
        }
        
        return $properties;
    }
}
