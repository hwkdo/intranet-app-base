<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\TourDefinition;

interface TourProviderInterface
{
    /**
     * @return list<TourDefinition>
     */
    public function tours(): array;
}
