<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Services\AppPackageVersionService;

test('packageNameForIdentifier maps assets to composer package name', function () {
    expect(IntranetAppBase::packageNameForIdentifier('assets'))
        ->toBe('hwkdo/intranet-app-assets');
});

test('parseGithubRepositoryUrl extracts owner and repo', function () {
    expect(IntranetAppBase::parseGithubRepositoryUrl('https://github.com/hwkdo/intranet-app-assets.git'))
        ->toBe(['owner' => 'hwkdo', 'repo' => 'intranet-app-assets']);
});

test('parseGithubRepositoryFromPackageData falls back to package name slug', function () {
    expect(IntranetAppBase::parseGithubRepositoryFromPackageData([
        'name' => 'hwkdo/intranet-app-assets',
    ]))->toBe(['owner' => 'hwkdo', 'repo' => 'intranet-app-assets']);
});

test('resolve returns installed package from composer lock when present', function () {
    $lockPath = base_path('composer.lock');

    if (! file_exists($lockPath)) {
        skip('composer.lock not available');
    }

    $resolved = app(AppPackageVersionService::class)->resolve('assets');

    expect($resolved)->not->toBeNull()
        ->and($resolved->packageName)->toBe('hwkdo/intranet-app-assets')
        ->and($resolved->githubOwner)->toBe('hwkdo')
        ->and($resolved->githubRepo)->toBe('intranet-app-assets')
        ->and($resolved->version)->not->toBe('');
});
