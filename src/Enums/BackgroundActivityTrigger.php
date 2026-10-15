<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum BackgroundActivityTrigger: string
{
    case Schedule = 'schedule';
    case Manual = 'manual';
    case Queue = 'queue';
    case Mail = 'mail';
    case Notification = 'notification';
}
