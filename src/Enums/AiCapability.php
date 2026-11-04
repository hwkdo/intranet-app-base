<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum AiCapability: string
{
    case Text = 'text';
    case Image = 'image';
    case Vision = 'vision';
    case Agent = 'agent';
    case Classification = 'classification';

    public function configDefaultKey(): string
    {
        return match ($this) {
            self::Image => 'default_for_images',
            self::Classification => 'default_for_classification',
            default => 'default',
        };
    }

    public function modelConfigSection(): string
    {
        return match ($this) {
            self::Image => 'image',
            self::Classification => 'classification',
            default => 'text',
        };
    }
}
