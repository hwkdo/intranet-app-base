<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum BackgroundActivityCadence: string
{
    case EveryNMinutes = 'every_n_minutes';
    case EveryNHours = 'every_n_hours';
    case DailyAt = 'daily_at';
    case WeeklyOn = 'weekly_on';

    public function label(): string
    {
        return match ($this) {
            self::EveryNMinutes => 'Alle N Minuten',
            self::EveryNHours => 'Alle N Stunden',
            self::DailyAt => 'Täglich um',
            self::WeeklyOn => 'Wöchentlich',
        };
    }
}
