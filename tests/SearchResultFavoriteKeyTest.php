<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Data\SearchResult;

it('exposes entity id from favorite key', function (): void {
    $result = new SearchResult(
        title: 'Demo',
        url: 'https://example.com',
        appIdentifier: 'actions',
        appName: 'Aktionen',
        icon: 'bolt',
        favoriteKey: 'intranet.actions:fuhrpark.book',
        sourceKey: 'intranet.actions',
    );

    expect($result->entityId())->toBe('fuhrpark.book');
});

it('keeps nested entity ids for apps and hilfe', function (): void {
    $app = new SearchResult(
        title: 'Tickets',
        url: '/apps/tickets',
        appIdentifier: 'apps',
        appName: 'Apps',
        icon: 'ticket',
        favoriteKey: 'intranet.apps:package:tickets',
        sourceKey: 'intranet.apps',
    );

    $hilfe = new SearchResult(
        title: 'Einrichtung',
        url: '/hilfe/einrichtung',
        appIdentifier: 'hilfe',
        appName: 'Hilfe',
        icon: 'wrench-screwdriver',
        favoriteKey: 'intranet.hilfe:hub:setup',
        sourceKey: 'intranet.hilfe',
    );

    expect($app->entityId())->toBe('package:tickets')
        ->and($hilfe->entityId())->toBe('hub:setup');
});
