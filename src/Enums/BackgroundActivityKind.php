<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum BackgroundActivityKind: string
{
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Mail = 'mail';
    case Notification = 'notification';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Geplant',
            self::Queued => 'Queue',
            self::Mail => 'Mail',
            self::Notification => 'Benachrichtigung',
        };
    }
}
