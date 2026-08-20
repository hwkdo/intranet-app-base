<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum NotificationChannelKey: string
{
    case Inbox = 'inbox';
    case Mail = 'mail';
    case WebPush = 'web_push';
    case Teams = 'teams';

    public function label(): string
    {
        return match ($this) {
            self::Inbox => 'In-App (Glocke)',
            self::Mail => 'E-Mail',
            self::WebPush => 'Web-Push',
            self::Teams => 'Microsoft Teams',
        };
    }

    /**
     * @return list<string>
     */
    public static function defaultAvailable(): array
    {
        return array_map(
            fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
