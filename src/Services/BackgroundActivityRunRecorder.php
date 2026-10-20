<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityRunStatus;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityTrigger;
use Hwkdo\IntranetAppBase\Support\BackgroundActivityChannels;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackgroundActivityRunRecorder
{
    /** @var array<string, float> */
    private array $commandStartedAt = [];

    public function __construct(
        private readonly BackgroundActivityCatalog $catalog,
        private readonly BackgroundActivityRunStore $runs,
    ) {}

    public function onCommandStarting(CommandStarting $event): void
    {
        if ($this->catalog->findByCommandName($event->command) === null) {
            return;
        }

        $this->commandStartedAt[$event->command] = microtime(true);
    }

    public function onCommandFinished(CommandFinished $event): void
    {
        $definition = $this->catalog->findByCommandName($event->command);

        if ($definition === null) {
            return;
        }

        $started = $this->commandStartedAt[$event->command] ?? null;
        unset($this->commandStartedAt[$event->command]);
        $duration = $started !== null ? (int) round((microtime(true) - $started) * 1000) : null;
        $scheduled = app()->runningConsoleCommand('schedule:run') || app()->runningConsoleCommand('schedule:work');
        $output = app(BackgroundActivityOutput::class)->pull();
        $failed = ($event->exitCode ?? 0) !== 0;

        $this->runs->record(
            activityKey: $definition->key,
            status: $failed ? BackgroundActivityRunStatus::Failed : BackgroundActivityRunStatus::Success,
            trigger: $scheduled ? BackgroundActivityTrigger::Schedule : BackgroundActivityTrigger::Manual,
            durationMs: $duration,
            exception: $failed && $output === null ? 'Exit code '.$event->exitCode : null,
            startedAt: $started !== null ? now()->subMilliseconds($duration ?? 0) : null,
            output: $output,
        );
    }

    public function onJobProcessed(JobProcessed $event): void
    {
        $this->recordQueueJob($event->job, BackgroundActivityRunStatus::Success, null);
    }

    public function onJobFailed(JobFailed $event): void
    {
        $this->recordQueueJob($event->job, BackgroundActivityRunStatus::Failed, $event->exception->getMessage());
    }

    public function onNotificationSent(NotificationSent $event): void
    {
        $typeKey = $this->notificationTypeKey($event->notification);

        if ($typeKey === null) {
            return;
        }

        $definition = $this->catalog->findByNotificationTypeKey($typeKey);

        if ($definition === null) {
            return;
        }

        $channel = BackgroundActivityChannels::fromLaravel($event->channel);

        if ($channel === null) {
            return;
        }

        $this->runs->record(
            activityKey: $definition->key,
            status: BackgroundActivityRunStatus::Success,
            trigger: BackgroundActivityTrigger::Notification,
            channel: $channel,
        );
    }

    private function recordQueueJob(object $queueJob, BackgroundActivityRunStatus $status, ?string $exception): void
    {
        $command = $this->unserializeCommand($queueJob);

        if ($command instanceof SendQueuedMailable) {
            $this->recordMailable($command, $status, $exception);

            return;
        }

        if ($command instanceof SendQueuedNotifications) {
            if ($status === BackgroundActivityRunStatus::Failed) {
                $this->recordFailedNotification($command, $exception);
            }

            return;
        }

        if (! is_object($command)) {
            return;
        }

        $definition = $this->catalog->findByJob($command::class);

        if ($definition === null) {
            return;
        }

        $this->runs->record(
            activityKey: $definition->key,
            status: $status,
            trigger: BackgroundActivityTrigger::Queue,
            exception: $exception,
        );
    }

    private function recordMailable(SendQueuedMailable $command, BackgroundActivityRunStatus $status, ?string $exception): void
    {
        $mailable = $command->mailable ?? null;

        if (! is_object($mailable)) {
            return;
        }

        $definition = $this->catalog->findByMailable($mailable::class);
        $key = $definition?->key;

        if ($key === null) {
            $key = 'mail.uncatalogued.'.str_replace('\\', '.', $mailable::class);
            Log::warning('Unkatalogisierte Mailable', ['class' => $mailable::class]);
        }

        $this->runs->record(
            activityKey: $key,
            status: $status,
            trigger: BackgroundActivityTrigger::Mail,
            exception: $exception,
            meta: ['mailable' => $mailable::class],
        );
    }

    private function recordFailedNotification(SendQueuedNotifications $command, ?string $exception): void
    {
        $typeKey = $this->notificationTypeKey($command->notification ?? null);

        if ($typeKey === null) {
            return;
        }

        $definition = $this->catalog->findByNotificationTypeKey($typeKey);

        if ($definition === null) {
            return;
        }

        $channels = is_array($command->channels ?? null) ? $command->channels : [];

        foreach ($channels as $laravelChannel) {
            $channel = BackgroundActivityChannels::fromLaravel((string) $laravelChannel);

            if ($channel === null) {
                continue;
            }

            $this->runs->record(
                activityKey: $definition->key,
                status: BackgroundActivityRunStatus::Failed,
                trigger: BackgroundActivityTrigger::Notification,
                channel: $channel,
                exception: $exception,
            );
        }
    }

    private function notificationTypeKey(mixed $notification): ?string
    {
        if (is_object($notification) && method_exists($notification, 'typeKey')) {
            $key = $notification->typeKey();

            return is_string($key) ? $key : null;
        }

        return null;
    }

    private function unserializeCommand(object $queueJob): ?object
    {
        if (! method_exists($queueJob, 'payload')) {
            return null;
        }

        try {
            $payload = $queueJob->payload();
            $serialized = $payload['data']['command'] ?? null;

            if (! is_string($serialized)) {
                return null;
            }

            $command = unserialize($serialized, ['allowed_classes' => true]);

            return is_object($command) ? $command : null;
        } catch (Throwable) {
            return null;
        }
    }
}
