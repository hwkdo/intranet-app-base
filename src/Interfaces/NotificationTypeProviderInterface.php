<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;

interface NotificationTypeProviderInterface
{
    /**
     * @return list<NotificationTypeDefinition>
     */
    public function notificationTypes(): array;
}
