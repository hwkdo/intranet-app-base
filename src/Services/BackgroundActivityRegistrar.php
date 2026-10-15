<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityKind;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Throwable;

class BackgroundActivityRegistrar
{
    private bool $registered = false;

    public function __construct(
        private readonly BackgroundActivityCatalog $catalog,
        private readonly BackgroundActivityConfigStore $configs,
    ) {}

    public function register(Schedule $schedule): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        foreach ($this->catalog->all() as $definition) {
            if ($definition->kind !== BackgroundActivityKind::Scheduled) {
                continue;
            }

            try {
                $this->registerDefinition($schedule, $definition);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function reset(): void
    {
        $this->registered = false;
    }

    private function registerDefinition(Schedule $schedule, BackgroundActivityDefinition $definition): void
    {
        $frequency = $this->configs->frequency($definition);

        if ($frequency === null) {
            return;
        }

        $event = $this->makeEvent($schedule, $definition);

        if ($event === null) {
            return;
        }

        $event->cron($frequency->toCron());

        if (! $event instanceof CallbackEvent) {
            $event->description($definition->key);
        }

        if ($definition->withoutOverlappingMinutes > 0) {
            $event->withoutOverlapping($definition->withoutOverlappingMinutes);
        }

        if ($definition->runInBackground && $definition->command !== null) {
            $event->runInBackground();
        }

        if ($definition->environments !== null && $definition->environments !== []) {
            $event->environments($definition->environments);
        }

        if ($definition->appendOutputTo !== null) {
            $event->appendOutputTo($definition->appendOutputTo);
        }

        $event->when(fn (): bool => $this->configs->isEnabled($definition));
    }

    private function makeEvent(Schedule $schedule, BackgroundActivityDefinition $definition): ?object
    {
        if ($definition->command !== null) {
            return $schedule->command($definition->command);
        }

        if ($definition->job !== null) {
            return $schedule->job($definition->job);
        }

        if ($definition->invokable !== null && class_exists($definition->invokable)) {
            $event = $schedule->call(app($definition->invokable));
            $event->description($definition->invokable);

            return $event;
        }

        return null;
    }
}
