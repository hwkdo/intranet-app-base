<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Data\NotificationPayload;
use Illuminate\Contracts\Auth\Authenticatable;

interface IntranetNotificationGatewayInterface
{
    public function notify(Authenticatable $user, string $typeKey, NotificationPayload $payload): void;

    /**
     * @param  iterable<int, Authenticatable>  $users
     */
    public function notifyMany(iterable $users, string $typeKey, NotificationPayload $payload): void;
}
