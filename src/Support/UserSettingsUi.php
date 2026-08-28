<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Hwkdo\IntranetAppBase\Data\Attributes\HiddenFromSettings;
use ReflectionProperty;

final class UserSettingsUi
{
    public static function isVisibleProperty(ReflectionProperty $property): bool
    {
        return $property->getAttributes(HiddenFromSettings::class) === [];
    }
}
