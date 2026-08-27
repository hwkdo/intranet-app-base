<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\SetupDefinition;

interface SetupProviderInterface
{
    /**
     * @return list<SetupDefinition>
     */
    public function setups(): array;
}
