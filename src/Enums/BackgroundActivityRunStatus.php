<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum BackgroundActivityRunStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
