<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Contracts\IntranetNotificationGatewayInterface;
use Hwkdo\IntranetAppBase\Data\NotificationPayload;
use Hwkdo\IntranetAppBase\Notifications\GenericIntranetNotification;
use Hwkdo\IntranetAppBase\Support\ActiveNotifiable;
use Illuminate\Contracts\Auth\Authenticatable;

class IntranetNotificationGateway implements IntranetNotificationGatewayInterface
{
    public function notify(Authenticatable $user, string $typeKey, NotificationPayload $payload): void
    {
        if (! method_exists($user, 'notify')) {
            return;
        }

        if (! ActiveNotifiable::matches($user)) {
            return;
        }

        $user->notify(new GenericIntranetNotification($typeKey, $payload));
    }

    public function notifyMany(iterable $users, string $typeKey, NotificationPayload $payload): void
    {
        foreach ($users as $user) {
            $this->notify($user, $typeKey, $payload);
        }
    }
}
