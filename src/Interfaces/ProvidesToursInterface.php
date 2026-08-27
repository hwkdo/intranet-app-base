<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\TourDefinition;

interface ProvidesToursInterface
{
    /**
     * @return list<TourDefinition>
     */
    public static function tours(): array;
}
