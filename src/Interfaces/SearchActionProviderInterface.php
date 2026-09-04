<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\SearchActionDefinition;

interface SearchActionProviderInterface
{
    /**
     * @return list<SearchActionDefinition>
     */
    public function searchActions(): array;
}
