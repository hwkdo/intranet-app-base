<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;

interface ProvidesBackgroundActivitiesInterface
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function backgroundActivities(): array;
}
