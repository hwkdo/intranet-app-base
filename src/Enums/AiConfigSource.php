<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum AiConfigSource: string
{
    case AppOverride = 'app_override';
    case Base = 'base';
    case ConfigDefault = 'config_default';
}
