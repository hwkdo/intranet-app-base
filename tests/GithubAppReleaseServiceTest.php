<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Services\GithubAppReleaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

test('releasesForRepository maps github api response to release info', function () {
    Http::fake([
        'api.github.com/repos/hwkdo/intranet-app-assets/releases*' => Http::response([
            [
                'tag_name' => 'v1.7.8',
                'name' => 'Fix UI (legacy-assets). Find Legacy Assets including trashed',
                'body' => '',
                'html_url' => 'https://github.com/hwkdo/intranet-app-assets/releases/tag/v1.7.8',
                'published_at' => '2026-05-19T09:45:33Z',
            ],
            [
                'tag_name' => 'v1.7.7',
                'name' => 'Older release',
                'body' => 'Details',
                'html_url' => 'https://github.com/hwkdo/intranet-app-assets/releases/tag/v1.7.7',
                'published_at' => '2026-05-01T09:45:33Z',
            ],
        ]),
    ]);

    $releases = app(GithubAppReleaseService::class)->releasesForRepository('hwkdo', 'intranet-app-assets');

    expect($releases)->toHaveCount(2)
        ->and($releases->first()->tagName)->toBe('v1.7.8')
        ->and($releases->first()->displayTitle())->toBe('Fix UI (legacy-assets). Find Legacy Assets including trashed');
});

test('findReleaseForTag matches version with or without v prefix', function () {
    Http::fake([
        'api.github.com/repos/hwkdo/intranet-app-assets/releases*' => Http::response([
            [
                'tag_name' => 'v1.7.8',
                'name' => 'Fix UI',
                'body' => '',
                'html_url' => 'https://github.com/hwkdo/intranet-app-assets/releases/tag/v1.7.8',
                'published_at' => '2026-05-19T09:45:33Z',
            ],
        ]),
    ]);

    $service = app(GithubAppReleaseService::class);
    $releases = $service->releasesForRepository('hwkdo', 'intranet-app-assets');

    expect($service->findReleaseForTag($releases, '1.7.8')?->tagName)->toBe('v1.7.8')
        ->and($service->findReleaseForTag($releases, 'v1.7.8')?->tagName)->toBe('v1.7.8');
});

test('previousRelease returns the next older release', function () {
    Http::fake([
        'api.github.com/repos/hwkdo/intranet-app-assets/releases*' => Http::response([
            [
                'tag_name' => 'v1.7.8',
                'name' => 'Current',
                'body' => '',
                'html_url' => 'https://example.com/v1.7.8',
                'published_at' => '2026-05-19T09:45:33Z',
            ],
            [
                'tag_name' => 'v1.7.7',
                'name' => 'Previous',
                'body' => '',
                'html_url' => 'https://example.com/v1.7.7',
                'published_at' => '2026-05-01T09:45:33Z',
            ],
        ]),
    ]);

    $service = app(GithubAppReleaseService::class);
    $releases = $service->releasesForRepository('hwkdo', 'intranet-app-assets');
    $current = $service->findReleaseForTag($releases, 'v1.7.8');

    expect($current)->not->toBeNull();
    expect($service->previousRelease($releases, $current)?->tagName)->toBe('v1.7.7');
});

test('releasesForRepository falls back to git tags when releases api is empty', function () {
    Http::fake([
        'api.github.com/repos/hwkdo/intranet-app-assets/releases*' => Http::response([]),
        'api.github.com/repos/hwkdo/intranet-app-assets/tags*' => Http::response([
            [
                'name' => 'v1.7.8',
                'commit' => ['sha' => 'abc123'],
            ],
        ]),
        'api.github.com/repos/hwkdo/intranet-app-assets/commits/abc123' => Http::response([
            'commit' => [
                'message' => "Fix UI (legacy-assets). Find Legacy Assets including trashed\n",
                'author' => ['date' => '2026-05-19T09:45:33Z'],
                'committer' => ['date' => '2026-05-19T09:45:33Z'],
            ],
        ]),
    ]);

    $releases = app(GithubAppReleaseService::class)->releasesForRepository('hwkdo', 'intranet-app-assets');

    expect($releases)->toHaveCount(1)
        ->and($releases->first()->tagName)->toBe('v1.7.8')
        ->and($releases->first()->displayTitle())->toBe('Fix UI (legacy-assets). Find Legacy Assets including trashed');
});

test('releasesForRepository returns empty collection when releases and tags fail', function () {
    Http::fake([
        'api.github.com/repos/hwkdo/intranet-app-assets/releases*' => Http::response([], 500),
        'api.github.com/repos/hwkdo/intranet-app-assets/tags*' => Http::response([], 500),
    ]);

    $releases = app(GithubAppReleaseService::class)->releasesForRepository('hwkdo', 'intranet-app-assets');

    expect($releases)->toBeEmpty();
});
