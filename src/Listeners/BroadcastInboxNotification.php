<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Listeners;

use Hwkdo\IntranetAppBase\Events\InboxNotificationReceived;
use Illuminate\Notifications\Events\NotificationSent;

class BroadcastInboxNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        $userId = method_exists($event->notifiable, 'getKey')
            ? $event->notifiable->getKey()
            : null;

        if (! $userId) {
            return;
        }

        $data = $event->response?->data ?? [];

        InboxNotificationReceived::dispatch(
            $userId,
            $data['title'] ?? 'Neue Benachrichtigung',
            $data['body'] ?? '',
            $data['url'] ?? null,
        );
    }
}
