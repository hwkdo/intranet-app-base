<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Hwkdo\IntranetAppBase\Enums\NotificationChannelKey;

final class BackgroundActivityChannels
{
    public static function fromLaravel(string $channel): ?string
    {
        if ($channel === 'database') {
            return NotificationChannelKey::Inbox->value;
        }

        if ($channel === 'mail') {
            return NotificationChannelKey::Mail->value;
        }

        if (str_ends_with($channel, 'WebPushChannel')) {
            return NotificationChannelKey::WebPush->value;
        }

        if (str_ends_with($channel, 'TeamsChannel')) {
            return NotificationChannelKey::Teams->value;
        }

        return null;
    }
}
