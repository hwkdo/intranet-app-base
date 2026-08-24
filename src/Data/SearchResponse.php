<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Illuminate\Support\Collection;

class SearchResponse
{
    /**
     * @param  Collection<int, SearchResult>  $results
     * @param  Collection<string, Collection<int, SearchResult>>  $groupedResults
     */
    public function __construct(
        public readonly Collection $results,
        public readonly Collection $groupedResults,
        public readonly int $totalCount,
    ) {}
}
