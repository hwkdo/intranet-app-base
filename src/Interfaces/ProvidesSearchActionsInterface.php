<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\SearchActionDefinition;

interface ProvidesSearchActionsInterface
{
    /**
     * @return list<SearchActionDefinition>
     */
    public static function searchActions(): array;
}
