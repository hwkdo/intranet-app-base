<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Composer\InstalledVersions;
use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Illuminate\Support\Facades\File;

class ManualAssetResolver
{
    public function imagesDirectory(ManualDefinition $definition): ?string
    {
        $theme = $definition->theme();
        $relative = "resources/manuals/{$theme}/images";

        if ($definition->group === 'base') {
            $path = base_path($relative);

            return File::isDirectory($path) ? $path : null;
        }

        $packageName = IntranetAppBase::packageNameForIdentifier($definition->appIdentifier);

        if (! InstalledVersions::isInstalled($packageName)) {
            return null;
        }

        $installPath = InstalledVersions::getInstallPath($packageName);

        if (! is_string($installPath)) {
            return null;
        }

        $path = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;

        return File::isDirectory($path) ? $path : null;
    }

    public function resolveFilePath(ManualDefinition $definition, string $filename): ?string
    {
        $filename = basename($filename);

        if ($filename === '' || str_contains($filename, '..')) {
            return null;
        }

        $directory = $this->imagesDirectory($definition);

        if ($directory === null) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! File::isFile($path)) {
            return null;
        }

        $realDirectory = realpath($directory);
        $realPath = realpath($path);

        if ($realDirectory === false || $realPath === false) {
            return null;
        }

        if (! str_starts_with($realPath, $realDirectory)) {
            return null;
        }

        return $realPath;
    }
}
