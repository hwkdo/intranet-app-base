<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;

final class BackgroundActivityFrequency
{
    public function __construct(
        public readonly BackgroundActivityCadence $cadence,
        public readonly int $every = 1,
        public readonly int $offset = 0,
        public readonly ?string $at = null,
        public readonly ?int $dayOfWeek = null,
    ) {}

    public function toCron(): string
    {
        return match ($this->cadence) {
            BackgroundActivityCadence::EveryNMinutes => $this->everyNMinutesCron(),
            BackgroundActivityCadence::EveryNHours => $this->everyNHoursCron(),
            BackgroundActivityCadence::DailyAt => $this->clockCron().' * * *',
            BackgroundActivityCadence::WeeklyOn => $this->clockCron().' * * '.(int) ($this->dayOfWeek ?? 0),
        };
    }

    public function label(): string
    {
        return match ($this->cadence) {
            BackgroundActivityCadence::EveryNMinutes => $this->offset > 0
                ? "alle {$this->every} Minuten, ab Minute {$this->offset}"
                : ($this->every === 1 ? 'jede Minute' : "alle {$this->every} Minuten"),
            BackgroundActivityCadence::EveryNHours => $this->every === 1
                ? "stündlich, Minute {$this->offset}"
                : "alle {$this->every} Stunden, Minute {$this->offset}",
            BackgroundActivityCadence::DailyAt => 'täglich um '.($this->at ?? '00:00'),
            BackgroundActivityCadence::WeeklyOn => 'wöchentlich '.self::weekdayLabel((int) ($this->dayOfWeek ?? 0)).' um '.($this->at ?? '00:00'),
        };
    }

    /**
     * @param  array{cadence?: string, every?: int, offset?: int, at?: ?string, day_of_week?: ?int}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            cadence: BackgroundActivityCadence::from((string) ($payload['cadence'] ?? BackgroundActivityCadence::DailyAt->value)),
            every: (int) ($payload['every'] ?? 1),
            offset: (int) ($payload['offset'] ?? 0),
            at: isset($payload['at']) && $payload['at'] !== '' ? (string) $payload['at'] : null,
            dayOfWeek: isset($payload['day_of_week']) ? (int) $payload['day_of_week'] : null,
        );
    }

    /**
     * @return array{cadence: string, every: int, offset: int, at: ?string, day_of_week: ?int}
     */
    public function toArray(): array
    {
        return [
            'cadence' => $this->cadence->value,
            'every' => $this->every,
            'offset' => $this->offset,
            'at' => $this->at,
            'day_of_week' => $this->dayOfWeek,
        ];
    }

    public static function everyMinutes(int $minutes, int $offset = 0): self
    {
        return new self(BackgroundActivityCadence::EveryNMinutes, max(1, $minutes), $offset);
    }

    public static function everyHours(int $hours, int $minute = 0): self
    {
        return new self(BackgroundActivityCadence::EveryNHours, max(1, $hours), $minute);
    }

    public static function dailyAt(string $time): self
    {
        return new self(BackgroundActivityCadence::DailyAt, 1, 0, $time);
    }

    public static function weeklyOn(int $dayOfWeek, string $time): self
    {
        return new self(BackgroundActivityCadence::WeeklyOn, 1, 0, $time, $dayOfWeek);
    }

    public static function weekdayLabel(int $day): string
    {
        return match ($day) {
            0 => 'Sonntag',
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            default => 'Tag '.$day,
        };
    }

    private function everyNMinutesCron(): string
    {
        // 60 Minuten sind kein gültiger Minuten-Schritt. */59 liefe um :59 und :00.
        if ($this->every >= 60) {
            return '0 * * * *';
        }

        $every = max(1, $this->every);

        if ($this->offset <= 0) {
            return $every === 1 ? '* * * * *' : '*/'.$every.' * * * *';
        }

        $minutes = [];

        for ($minute = $this->offset; $minute < 60; $minute += $every) {
            $minutes[] = $minute;
        }

        return implode(',', $minutes).' * * * *';
    }

    private function everyNHoursCron(): string
    {
        $minute = max(0, min(59, $this->offset));
        $hours = max(1, min(24, $this->every));

        if ($hours === 1) {
            return $minute.' * * * *';
        }

        return $minute.' */'.$hours.' * * *';
    }

    private function clockCron(): string
    {
        [$hour, $minute] = self::splitTime($this->at ?? '00:00');

        return $minute.' '.$hour;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function splitTime(string $time): array
    {
        $parts = explode(':', $time);

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }
}
