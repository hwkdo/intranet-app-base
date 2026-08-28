<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;

interface ManualProviderInterface
{
    /**
     * @return list<ManualDefinition>
     */
    public function manuals(): array;
}
