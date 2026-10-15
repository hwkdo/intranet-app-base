<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;

interface BackgroundActivityProviderInterface
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public function backgroundActivities(): array;
}
