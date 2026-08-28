<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\Services\ManualCatalog;
use Hwkdo\IntranetAppBase\Support\ManualAssetResolver;

if (! function_exists('manual_asset')) {
    function manual_asset(string $manualKey, string $filename): string
    {
        $definition = app(ManualCatalog::class)->find($manualKey);

        if (! $definition instanceof ManualDefinition) {
            return '';
        }

        $safeFilename = basename($filename);

        if ($safeFilename === '' || str_contains($safeFilename, '..')) {
            return '';
        }

        if (app(ManualAssetResolver::class)->resolveFilePath($definition, $safeFilename) === null) {
            return '';
        }

        return route('intranet.manuals.asset', [
            'manualKey' => $manualKey,
            'filename' => $safeFilename,
        ]);
    }
}
