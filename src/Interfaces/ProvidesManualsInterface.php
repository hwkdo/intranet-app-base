<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;

interface ProvidesManualsInterface
{
    /**
     * @return list<ManualDefinition>
     */
    public static function manuals(): array;
}
