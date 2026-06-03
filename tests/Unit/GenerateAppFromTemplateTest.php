<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Commands\GenerateAppFromTemplate;

it('maps hyphenated identifiers to underscore database table names', function () {
    expect(GenerateAppFromTemplate::settingsTableNameForIdentifier('bue-exports'))
        ->toBe('intranet_app_bue_exports_settings')
        ->and(GenerateAppFromTemplate::settingsTableNameForIdentifier('mein-arbeitsschutz'))
        ->toBe('intranet_app_mein_arbeitsschutz_settings')
        ->and(GenerateAppFromTemplate::settingsTableNameForIdentifier('abwesenheit'))
        ->toBe('intranet_app_abwesenheit_settings');
});
