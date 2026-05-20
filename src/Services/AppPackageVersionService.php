<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Composer\InstalledVersions;
use Hwkdo\IntranetAppBase\Data\InstalledAppPackage;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Illuminate\Support\Facades\File;

class AppPackageVersionService
{
    public function resolve(string $appIdentifier): ?InstalledAppPackage
    {
        $packageName = IntranetAppBase::packageNameForIdentifier($appIdentifier);
        $packageData = $this->findPackageData($packageName);

        if ($packageData === null) {
            return null;
        }

        $packageData['name'] = $packageName;
        $repository = IntranetAppBase::parseGithubRepositoryFromPackageData($packageData);

        if ($repository === null) {
            return null;
        }

        return new InstalledAppPackage(
            packageName: $packageName,
            identifier: $appIdentifier,
            version: (string) ($packageData['version'] ?? 'unknown'),
            reference: $packageData['reference'] ?? null,
            installedAt: $packageData['installed_at'] ?? null,
            githubOwner: $repository['owner'],
            githubRepo: $repository['repo'],
        );
    }

    /**
     * @return array{version: string, reference: ?string, installed_at: ?string, homepage: ?string, support: ?array<string, string>}|null
     */
    private function findPackageData(string $packageName): ?array
    {
        $fromLock = $this->findInComposerLock($packageName);

        if ($fromLock !== null) {
            return $fromLock;
        }

        if (! InstalledVersions::isInstalled($packageName)) {
            return null;
        }

        $reference = InstalledVersions::getReference($packageName);
        $homepage = $this->homepageFromInstalledJson($packageName);

        return [
            'version' => InstalledVersions::getPrettyVersion($packageName) ?? InstalledVersions::getVersion($packageName),
            'reference' => is_string($reference) ? $reference : null,
            'installed_at' => null,
            'homepage' => $homepage,
            'support' => null,
        ];
    }

    /**
     * @return array{version: string, reference: ?string, installed_at: ?string, homepage: ?string, support: ?array<string, string>}|null
     */
    private function findInComposerLock(string $packageName): ?array
    {
        $lockPath = base_path('composer.lock');

        if (! File::exists($lockPath)) {
            return null;
        }

        $lock = json_decode(File::get($lockPath), true);

        if (! is_array($lock)) {
            return null;
        }

        foreach (['packages', 'packages-dev'] as $section) {
            if (! isset($lock[$section]) || ! is_array($lock[$section])) {
                continue;
            }

            foreach ($lock[$section] as $package) {
                if (! is_array($package) || ($package['name'] ?? null) !== $packageName) {
                    continue;
                }

                $source = is_array($package['source'] ?? null) ? $package['source'] : [];
                $support = is_array($package['support'] ?? null) ? $package['support'] : null;

                return [
                    'version' => (string) ($package['version'] ?? 'unknown'),
                    'reference' => isset($source['reference']) ? (string) $source['reference'] : null,
                    'installed_at' => isset($package['time']) ? (string) $package['time'] : null,
                    'homepage' => isset($package['homepage']) ? (string) $package['homepage'] : null,
                    'support' => $support,
                ];
            }
        }

        return null;
    }

    private function homepageFromInstalledJson(string $packageName): ?string
    {
        $installedPath = base_path('vendor/composer/installed.json');

        if (! File::exists($installedPath)) {
            return null;
        }

        $installed = json_decode(File::get($installedPath), true);

        if (! is_array($installed)) {
            return null;
        }

        $packages = $installed['packages'] ?? $installed;

        if (! is_array($packages)) {
            return null;
        }

        foreach ($packages as $package) {
            if (! is_array($package) || ($package['name'] ?? null) !== $packageName) {
                continue;
            }

            return isset($package['homepage']) ? (string) $package['homepage'] : null;
        }

        return null;
    }
}
