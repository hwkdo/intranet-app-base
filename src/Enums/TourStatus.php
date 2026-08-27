<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum TourStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Dismissed = 'dismissed';
    case RemindLater = 'remind_later';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::Completed => 'Erledigt',
            self::Dismissed => 'Übersprungen',
            self::RemindLater => 'Später',
        };
    }

    public function isStored(): bool
    {
        return $this !== self::Pending;
    }
}
