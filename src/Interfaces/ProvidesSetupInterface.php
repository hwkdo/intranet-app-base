<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\SetupDefinition;

interface ProvidesSetupInterface
{
    /**
     * @return list<SetupDefinition>
     */
    public static function setups(): array;
}
