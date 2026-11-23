<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

interface LightRagCitationLinker
{
    public function instance(): string;

    /**
     * Muster für einen Dateinamen im Antworttext. Treffer [0] ist der Name.
     */
    public function pattern(): string;

    /**
     * @param  list<string>  $fileNames
     * @return array<string, array{url: string, title: string}> Schlüssel ist der kleingeschriebene Dateiname ohne Pfad
     */
    public function resolveMany(array $fileNames): array;
}
