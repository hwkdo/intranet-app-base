<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Interfaces;

interface ProvidesSearchInterface
{
    /**
     * @return list<class-string<SearchSourceInterface>>
     */
    public static function searchSources(): array;
}
