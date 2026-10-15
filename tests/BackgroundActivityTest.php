<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppBase\Interfaces\BackgroundActivityProviderInterface;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityCatalog;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityRegistrar;
use Illuminate\Console\Scheduling\Schedule;

test('frequency cron matches the previous scheduler expressions', function (BackgroundActivityFrequency $frequency, string $cron): void {
    expect($frequency->toCron())->toBe($cron);
})->with([
    'every minute' => [BackgroundActivityFrequency::everyMinutes(1), '* * * * *'],
    'every 15' => [BackgroundActivityFrequency::everyMinutes(15), '*/15 * * * *'],
    'offset 10' => [BackgroundActivityFrequency::everyMinutes(15, 10), '10,25,40,55 * * * *'],
    'offset 7' => [BackgroundActivityFrequency::everyMinutes(15, 7), '7,22,37,52 * * * *'],
    'hourly' => [BackgroundActivityFrequency::everyHours(1), '0 * * * *'],
    'every 4 hours' => [BackgroundActivityFrequency::everyHours(4), '0 */4 * * *'],
    'every 24 hours' => [BackgroundActivityFrequency::everyHours(24), '0 */24 * * *'],
    'daily 03:32' => [BackgroundActivityFrequency::dailyAt('03:32'), '32 3 * * *'],
    'daily 00:01' => [BackgroundActivityFrequency::dailyAt('00:01'), '1 0 * * *'],
    'weekly saturday' => [BackgroundActivityFrequency::weeklyOn(6, '04:12'), '12 4 * * 6'],
]);

test('catalog merges a host provider', function (): void {
    config([
        'intranet-app-base.background_activity_providers' => [BackgroundActivityTestProvider::class],
    ]);

    $catalog = new BackgroundActivityCatalog(fn (): array => []);

    expect($catalog->find('base.test-activity'))->not->toBeNull()
        ->and($catalog->findByCommandName('background-activity:test')?->key)->toBe('base.test-activity');
});

test('registrar adds exactly one scheduled event', function (): void {
    config([
        'intranet-app-base.background_activity_providers' => [BackgroundActivityTestProvider::class],
    ]);

    $this->app->forgetInstance(BackgroundActivityCatalog::class);
    $this->app->forgetInstance(BackgroundActivityRegistrar::class);
    $this->app->forgetInstance(Schedule::class);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'background-activity:test'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('*/15 * * * *');
});

final class BackgroundActivityTestProvider implements BackgroundActivityProviderInterface
{
    public function backgroundActivities(): array
    {
        return [
            BackgroundActivityDefinition::scheduled(
                key: 'base.test-activity',
                label: 'Test',
                appIdentifier: 'base',
                appName: 'Intranet',
                command: 'background-activity:test',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                allowedCadences: [BackgroundActivityCadence::EveryNMinutes],
                minEvery: 5,
                maxEvery: 60,
            ),
        ];
    }
}
