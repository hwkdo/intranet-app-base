<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;

interface ProvidesNotificationsInterface
{
    /**
     * @return list<NotificationTypeDefinition>
     */
    public static function notificationTypes(): array;
}
